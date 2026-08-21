<?php

namespace App\Services\Inventory;

use App\Models\StockBalance;
use App\Models\StockLedger;
use App\Models\StockOpname;
use App\Models\User;
use App\Services\Numbering\NumberingService;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Orchestrator stock opname (perhitungan fisik).
 *
 * Alur: createDraft() auto-generate item dari SEMUA batch berstok (snapshot
 * system_qty). update() menyimpan hasil hitung fisik (counted_qty). post()
 * menghitung ulang variance terhadap stok TERKINI (bukan snapshot lama) lalu
 * menulis ledger opname_in/out — stok sistem jadi = hasil hitung fisik.
 */
class StockOpnameService
{
    public function __construct(
        private readonly NumberingService $numbering,
        private readonly StockLedgerWriter $ledger,
    ) {}

    /**
     * Buat sesi opname & auto-generate item dari semua batch berstok.
     *
     * @param  array<string, mixed>  $headerData
     */
    public function createDraft(array $headerData, User $by): StockOpname
    {
        return DB::transaction(function () use ($headerData, $by): StockOpname {
            $date = $headerData['opname_date'] instanceof CarbonInterface
                ? $headerData['opname_date']
                : Carbon::parse($headerData['opname_date']);

            $opname = new StockOpname([
                'opname_date' => $date,
                'notes' => $headerData['notes'] ?? null,
                'status' => StockOpname::STATUS_DRAFT,
                'fiscal_year' => (int) $date->format('Y'),
                'created_by' => $by->id,
            ]);
            $opname->opname_number = $this->numbering->next('opname');
            $opname->save();

            $this->generateItems($opname);

            return $opname->refresh();
        });
    }

    /**
     * Simpan hasil hitung fisik per item. $countsByItemId: [item_id => counted_qty|null].
     *
     * @param  array<string, mixed>  $headerData
     * @param  array<int, int|null>  $countsByItemId
     */
    public function update(StockOpname $opname, array $headerData, array $countsByItemId, User $by): StockOpname
    {
        if (! $opname->canBeEdited()) {
            throw ValidationException::withMessages(['status' => 'Opname tidak bisa diedit pada status saat ini.']);
        }

        return DB::transaction(function () use ($opname, $headerData, $countsByItemId, $by): StockOpname {
            $date = $headerData['opname_date'] instanceof CarbonInterface
                ? $headerData['opname_date']
                : Carbon::parse($headerData['opname_date']);

            $opname->update([
                'opname_date' => $date,
                'notes' => $headerData['notes'] ?? null,
                'fiscal_year' => (int) $date->format('Y'),
                'updated_by' => $by->id,
            ]);

            foreach ($opname->items as $item) {
                if (array_key_exists($item->id, $countsByItemId)) {
                    $counted = $countsByItemId[$item->id];
                    $item->update([
                        'counted_qty' => $counted === null || $counted === '' ? null : max(0, (int) $counted),
                    ]);
                }
            }

            return $opname->refresh();
        });
    }

    /**
     * Posting: hitung ulang variance = counted − stok TERKINI, tulis ledger
     * opname_in/out. Baris yang belum dihitung (counted null) dilewati.
     */
    public function post(StockOpname $opname, User $by): StockOpname
    {
        if (! $opname->canBePosted()) {
            throw ValidationException::withMessages(['status' => 'Opname tidak bisa di-posting (status atau item kosong).']);
        }

        $opname->load('items.product:id,base_unit_id');

        $counted = $opname->items->filter(fn ($i) => $i->counted_qty !== null);
        if ($counted->isEmpty()) {
            throw ValidationException::withMessages(['items' => 'Belum ada item yang dihitung (counted_qty).']);
        }

        return DB::transaction(function () use ($opname, $by): StockOpname {
            foreach ($opname->items as $item) {
                if ($item->counted_qty === null) {
                    $item->update(['variance' => 0]);

                    continue;
                }

                $baseUnitId = $item->product?->base_unit_id;
                if ($baseUnitId === null) {
                    throw ValidationException::withMessages([
                        'items' => "Produk {$item->product_sku_snapshot} tidak punya base unit.",
                    ]);
                }

                // Variance dihitung terhadap stok TERKINI (lock), bukan snapshot.
                $currentQty = (int) (StockBalance::query()
                    ->where('product_id', $item->product_id)
                    ->where('batch_id', $item->batch_id)
                    ->lockForUpdate()
                    ->value('qty_on_hand') ?? 0);

                $variance = (int) $item->counted_qty - $currentQty;
                $item->update(['variance' => $variance]);

                if ($variance === 0) {
                    continue;
                }

                $payload = [
                    'product_id' => $item->product_id,
                    'batch_id' => $item->batch_id,
                    'product_unit_id' => $baseUnitId,
                    'ref_type' => 'stock_opname',
                    'ref_id' => $opname->id,
                    'notes' => $item->notes,
                ];

                if ($variance > 0) {
                    $this->ledger->writeIn([
                        ...$payload,
                        'type' => StockLedger::TYPE_OPNAME_IN,
                        'qty_in' => $variance,
                    ], $by);
                } else {
                    $this->ledger->writeOut([
                        ...$payload,
                        'type' => StockLedger::TYPE_OPNAME_OUT,
                        'qty_out' => abs($variance),
                    ], $by);
                }
            }

            $opname->update([
                'status' => StockOpname::STATUS_POSTED,
                'posted_by' => $by->id,
                'posted_at' => now(),
            ]);

            return $opname->refresh();
        });
    }

    public function cancel(StockOpname $opname, string $reason, User $by): StockOpname
    {
        if (! $opname->canBeCancelled()) {
            throw ValidationException::withMessages(['status' => 'Hanya opname draft yang bisa dibatalkan.']);
        }

        $opname->update([
            'status' => StockOpname::STATUS_CANCELLED,
            'cancelled_by' => $by->id,
            'cancelled_at' => now(),
            'cancel_reason' => $reason,
        ]);

        return $opname->refresh();
    }

    /**
     * Auto-generate item dari semua batch yang punya stok (qty_on_hand > 0),
     * beserta snapshot identitas & system_qty.
     */
    private function generateItems(StockOpname $opname): void
    {
        $balances = StockBalance::query()
            ->where('qty_on_hand', '>', 0)
            ->with(['product:id,name,sku', 'batch:id,batch_code'])
            ->get();

        $sort = 0;
        foreach ($balances as $balance) {
            $product = $balance->product;
            $batch = $balance->batch;
            if ($product === null || $batch === null) {
                continue;
            }

            $opname->items()->create([
                'product_id' => $product->id,
                'batch_id' => $batch->id,
                'product_name_snapshot' => $product->name,
                'product_sku_snapshot' => $product->sku,
                'batch_code_snapshot' => $batch->batch_code,
                'system_qty_snapshot' => (int) $balance->qty_on_hand,
                'counted_qty' => null,
                'variance' => 0,
                'sort_order' => $sort++,
            ]);
        }
    }
}
