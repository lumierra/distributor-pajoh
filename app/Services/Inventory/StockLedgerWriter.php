<?php

namespace App\Services\Inventory;

use App\Exceptions\InsufficientStockException;
use App\Models\StockBalance;
use App\Models\StockLedger;
use App\Models\User;
use App\Services\Setting\SettingManager;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Atomic writer untuk stock_ledger + stock_balances.
 *
 * Source-of-truth: ledger (immutable). Balances di-update di transaksi yang
 * sama — kalau gagal, semua rollback.
 */
class StockLedgerWriter
{
    public function __construct(private readonly SettingManager $settings) {}

    /**
     * Tulis IN movement (purchase_in, bonus_in, adjustment_in, return_in,
     * opname_in).
     *
     * @param  array{
     *   product_id: int,
     *   batch_id: int,
     *   product_unit_id: int,
     *   type: string,
     *   qty_in: int,
     *   cost_price?: float,
     *   is_bonus_pool?: bool,
     *   ref_type: string,
     *   ref_id: int,
     *   notes?: string|null,
     * }  $data
     */
    public function writeIn(array $data, ?User $by = null): StockLedger
    {
        $this->validateType($data['type']);

        if ((int) $data['qty_in'] <= 0) {
            throw new InvalidArgumentException('qty_in harus > 0 untuk IN movement.');
        }

        return DB::transaction(function () use ($data, $by): StockLedger {
            $ledger = StockLedger::create([
                'product_id' => $data['product_id'],
                'batch_id' => $data['batch_id'],
                'product_unit_id' => $data['product_unit_id'],
                'type' => $data['type'],
                'is_bonus_pool' => (bool) ($data['is_bonus_pool'] ?? false),
                'qty_in' => (int) $data['qty_in'],
                'qty_out' => 0,
                'cost_price' => (float) ($data['cost_price'] ?? 0),
                'ref_type' => $data['ref_type'],
                'ref_id' => (int) $data['ref_id'],
                'notes' => $data['notes'] ?? null,
                'created_by' => $by?->id,
                'created_at' => now(),
            ]);

            $this->applyToBalance($ledger);

            return $ledger;
        });
    }

    /**
     * Tulis OUT movement (sale_out, adjustment_out, return_out_to_supplier,
     * opname_out, write_off).
     *
     * @param  array{
     *   product_id: int,
     *   batch_id: int,
     *   product_unit_id: int,
     *   type: string,
     *   qty_out: int,
     *   cost_price?: float,
     *   is_bonus_pool?: bool,
     *   ref_type: string,
     *   ref_id: int,
     *   notes?: string|null,
     * }  $data
     */
    public function writeOut(array $data, ?User $by = null): StockLedger
    {
        $this->validateType($data['type']);

        if ((int) $data['qty_out'] <= 0) {
            throw new InvalidArgumentException('qty_out harus > 0 untuk OUT movement.');
        }

        return DB::transaction(function () use ($data, $by): StockLedger {
            // Row-lock balance untuk hindari race condition.
            $balance = StockBalance::query()
                ->where('product_id', $data['product_id'])
                ->where('batch_id', $data['batch_id'])
                ->lockForUpdate()
                ->first();

            $allowNegative = (bool) $this->settings->get('inventory.allow_negative_stock', false);
            $available = $balance?->qty_on_hand ?? 0;

            if (! $allowNegative && $available < (int) $data['qty_out']) {
                throw new InsufficientStockException(
                    productId: (int) $data['product_id'],
                    requested: (int) $data['qty_out'],
                    available: (int) $available,
                );
            }

            $ledger = StockLedger::create([
                'product_id' => $data['product_id'],
                'batch_id' => $data['batch_id'],
                'product_unit_id' => $data['product_unit_id'],
                'type' => $data['type'],
                'is_bonus_pool' => (bool) ($data['is_bonus_pool'] ?? false),
                'qty_in' => 0,
                'qty_out' => (int) $data['qty_out'],
                'cost_price' => (float) ($data['cost_price'] ?? 0),
                'ref_type' => $data['ref_type'],
                'ref_id' => (int) $data['ref_id'],
                'notes' => $data['notes'] ?? null,
                'created_by' => $by?->id,
                'created_at' => now(),
            ]);

            $this->applyToBalance($ledger);

            return $ledger;
        });
    }

    /**
     * Apply ledger row ke stock_balances. Upsert atomic.
     */
    public function applyToBalance(StockLedger $ledger): StockBalance
    {
        $delta = $ledger->qty_in - $ledger->qty_out;
        $bonusDelta = $ledger->is_bonus_pool ? $delta : 0;

        $balance = StockBalance::query()
            ->where('product_id', $ledger->product_id)
            ->where('batch_id', $ledger->batch_id)
            ->lockForUpdate()
            ->first();

        if ($balance === null) {
            $balance = StockBalance::create([
                'product_id' => $ledger->product_id,
                'batch_id' => $ledger->batch_id,
                'qty_on_hand' => $delta,
                'qty_bonus_pool' => $bonusDelta,
                'qty_reserved' => 0,
                'last_movement_at' => $ledger->created_at,
            ]);
        } else {
            $balance->qty_on_hand += $delta;
            $balance->qty_bonus_pool += $bonusDelta;
            $balance->last_movement_at = $ledger->created_at;
            $balance->save();
        }

        // Invalidate caches per product
        Cache::forget("product:{$ledger->product_id}:stock");

        return $balance;
    }

    /**
     * Verifikasi konsistensi: SUM(ledger) per (product, batch) == balance.
     *
     * @return array<int, array{product_id:int, batch_id:int, ledger_sum:int, balance:int}>
     */
    public function findInconsistencies(): array
    {
        $rows = DB::table('stock_ledger')
            ->select(
                'product_id',
                'batch_id',
                DB::raw('SUM(qty_in) - SUM(qty_out) AS ledger_sum'),
            )
            ->groupBy('product_id', 'batch_id')
            ->get();

        $issues = [];
        foreach ($rows as $row) {
            $balance = StockBalance::query()
                ->where('product_id', $row->product_id)
                ->where('batch_id', $row->batch_id)
                ->value('qty_on_hand') ?? 0;

            if ((int) $row->ledger_sum !== (int) $balance) {
                $issues[] = [
                    'product_id' => (int) $row->product_id,
                    'batch_id' => (int) $row->batch_id,
                    'ledger_sum' => (int) $row->ledger_sum,
                    'balance' => (int) $balance,
                ];
            }
        }

        return $issues;
    }

    private function validateType(string $type): void
    {
        if (! in_array($type, StockLedger::TYPES, true)) {
            throw new InvalidArgumentException("Tipe ledger tidak valid: {$type}");
        }
    }
}
