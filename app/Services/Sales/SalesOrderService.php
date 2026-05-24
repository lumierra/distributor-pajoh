<?php

namespace App\Services\Sales;

use App\Exceptions\InsufficientStockException;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\ProductUnit;
use App\Models\SalesOrder;
use App\Models\SoItem;
use App\Models\User;
use App\Services\Customer\CustomerOutstandingService;
use App\Services\Inventory\ReservationService;
use App\Services\Numbering\NumberingService;
use App\Services\Setting\SettingManager;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SalesOrderService
{
    public function __construct(
        private readonly NumberingService $numbering,
        private readonly SettingManager $settings,
        private readonly CustomerOutstandingService $outstanding,
        private readonly ReservationService $reservation,
    ) {}

    /**
     * Buat draft SO. Sales boleh buat untuk customer-nya (admin: bebas).
     *
     * @param  array<string, mixed>  $headerData
     * @param  array<int, array<string, mixed>>  $itemsData
     */
    public function createDraft(array $headerData, array $itemsData, User $by): SalesOrder
    {
        return DB::transaction(function () use ($headerData, $itemsData, $by): SalesOrder {
            /** @var Customer $customer */
            $customer = Customer::query()->findOrFail($headerData['customer_id']);

            $this->assertCustomerOk($customer);

            $soDate = $headerData['so_date'] instanceof CarbonInterface
                ? $headerData['so_date']
                : Carbon::parse($headerData['so_date']);

            $paymentTerm = $headerData['payment_term_days'] ?? (int) $customer->payment_term_days;
            $dueDate = $soDate->copy()->addDays((int) $paymentTerm);

            $so = new SalesOrder([
                'customer_id' => $customer->id,
                'sales_id' => $headerData['sales_id'] ?? $by->id,
                'visit_id' => $headerData['visit_id'] ?? null,
                'so_date' => $soDate,
                'eta_date' => $headerData['eta_date'] ?? null,
                'payment_term_days' => $paymentTerm,
                'due_date' => $dueDate,
                'status' => SalesOrder::STATUS_DRAFT,
                'fiscal_year' => (int) $soDate->format('Y'),
                'header_discount_type' => $headerData['header_discount_type'] ?? null,
                'header_discount_value' => $headerData['header_discount_value'] ?? 0,
                'notes' => $headerData['notes'] ?? null,
                'created_by' => $by->id,
            ]);
            $so->so_number = $this->numbering->next('so');
            $so->save();

            $this->syncItems($so, $customer, $itemsData);
            $this->recomputeTotals($so);

            return $so->refresh();
        });
    }

    /**
     * Update draft SO header + items.
     *
     * @param  array<string, mixed>  $headerData
     * @param  array<int, array<string, mixed>>  $itemsData
     */
    public function updateDraft(SalesOrder $so, array $headerData, array $itemsData, User $by): SalesOrder
    {
        if (! $so->canBeEdited()) {
            throw ValidationException::withMessages(['status' => 'SO sudah tidak bisa diedit.']);
        }

        return DB::transaction(function () use ($so, $headerData, $itemsData, $by): SalesOrder {
            /** @var Customer $customer */
            $customer = Customer::query()->findOrFail($headerData['customer_id']);
            $this->assertCustomerOk($customer);

            $soDate = $headerData['so_date'] instanceof CarbonInterface
                ? $headerData['so_date']
                : Carbon::parse($headerData['so_date']);

            $paymentTerm = $headerData['payment_term_days'] ?? (int) $customer->payment_term_days;
            $dueDate = $soDate->copy()->addDays((int) $paymentTerm);

            $so->fill([
                'customer_id' => $customer->id,
                'so_date' => $soDate,
                'eta_date' => $headerData['eta_date'] ?? null,
                'payment_term_days' => $paymentTerm,
                'due_date' => $dueDate,
                'header_discount_type' => $headerData['header_discount_type'] ?? null,
                'header_discount_value' => $headerData['header_discount_value'] ?? 0,
                'notes' => $headerData['notes'] ?? null,
                'fiscal_year' => (int) $soDate->format('Y'),
                'updated_by' => $by->id,
            ]);
            $so->save();

            $this->syncItems($so, $customer, $itemsData);
            $this->recomputeTotals($so);

            return $so->refresh();
        });
    }

    /**
     * Submit SO untuk review. Cek stok (kalau allow_negative=false) & credit
     * (kalau over limit → pending_credit_review, otherwise submitted).
     */
    public function submit(SalesOrder $so, User $by): SalesOrder
    {
        if (! $so->canBeSubmitted()) {
            throw ValidationException::withMessages(['status' => 'SO tidak bisa di-submit (status atau items kosong).']);
        }

        $so->load(['items.productUnit', 'customer']);

        return DB::transaction(function () use ($so, $by): SalesOrder {
            // 1. Stock check (kalau setting block negative)
            $allowNegative = (bool) $this->settings->get('inventory.allow_negative_stock', false);
            if (! $allowNegative) {
                try {
                    $this->reservation->assertStockAvailable($so);
                } catch (InsufficientStockException $e) {
                    throw ValidationException::withMessages([
                        'stock' => "Stok produk #{$e->productId} tidak cukup: minta {$e->requested}, tersedia {$e->available}.",
                    ]);
                }
            }

            // 2. Credit check
            $outstanding = $this->outstanding->getTotal($so->customer);
            $totalAfterSo = $outstanding + (float) $so->total;
            $creditLimit = (float) $so->customer->credit_limit;
            $isOver = $creditLimit > 0 && $totalAfterSo > $creditLimit;

            $so->update([
                'status' => $isOver ? SalesOrder::STATUS_PENDING_CREDIT_REVIEW : SalesOrder::STATUS_SUBMITTED,
                'submitted_at' => now(),
                'submitted_by' => $by->id,
                'credit_review_required' => $isOver,
                'credit_outstanding_snapshot' => $outstanding,
            ]);

            return $so->refresh();
        });
    }

    /**
     * Approve normal SO (status=submitted). Create reservations.
     */
    public function approve(SalesOrder $so, User $by): SalesOrder
    {
        if ($so->status !== SalesOrder::STATUS_SUBMITTED) {
            throw ValidationException::withMessages(['status' => 'SO tidak bisa di-approve pada status saat ini. Gunakan approveOverride untuk pending_credit_review.']);
        }

        return $this->doApprove($so, $by, override: false);
    }

    /**
     * Approve override (superadmin) untuk SO over-credit (pending_credit_review).
     */
    public function approveOverride(SalesOrder $so, string $reason, User $by): SalesOrder
    {
        if ($so->status !== SalesOrder::STATUS_PENDING_CREDIT_REVIEW) {
            throw ValidationException::withMessages(['status' => 'SO tidak butuh override credit.']);
        }

        return $this->doApprove($so, $by, override: true, overrideReason: $reason);
    }

    private function doApprove(SalesOrder $so, User $by, bool $override, ?string $overrideReason = null): SalesOrder
    {
        return DB::transaction(function () use ($so, $by, $override, $overrideReason): SalesOrder {
            // Re-check stock saat approve (race condition guard)
            $allowNegative = (bool) $this->settings->get('inventory.allow_negative_stock', false);
            if (! $allowNegative) {
                try {
                    $this->reservation->assertStockAvailable($so);
                } catch (InsufficientStockException $e) {
                    throw ValidationException::withMessages([
                        'stock' => "Stok berubah sejak submit. Produk #{$e->productId} tidak cukup: minta {$e->requested}, tersedia {$e->available}.",
                    ]);
                }
            }

            // Buat snapshot customer
            $snapshot = $this->buildCustomerSnapshot($so->customer()->first());

            $update = [
                'status' => SalesOrder::STATUS_APPROVED,
                'approved_at' => now(),
                'approved_by' => $by->id,
                'customer_snapshot' => $snapshot,
            ];

            if ($override) {
                $update['credit_override_approved_by'] = $by->id;
                $update['credit_override_approved_at'] = now();
                $update['credit_override_reason'] = $overrideReason;
            }

            $so->update($update);

            // Reservation
            $this->reservation->reserveForSo($so->refresh());

            return $so->refresh();
        });
    }

    public function reject(SalesOrder $so, string $reason, User $by): SalesOrder
    {
        if (! $so->canBeRejected()) {
            throw ValidationException::withMessages(['status' => 'SO tidak bisa di-reject pada status saat ini.']);
        }

        $so->update([
            'status' => SalesOrder::STATUS_REJECTED,
            'rejected_at' => now(),
            'rejected_by' => $by->id,
            'rejection_reason' => $reason,
        ]);

        return $so->refresh();
    }

    public function cancel(SalesOrder $so, string $reason, User $by): SalesOrder
    {
        if (! $so->canBeCancelled()) {
            throw ValidationException::withMessages(['status' => 'SO tidak bisa di-cancel pada status saat ini.']);
        }

        return DB::transaction(function () use ($so, $reason, $by): SalesOrder {
            // Kalau sudah approved, release reservations
            if ($so->status === SalesOrder::STATUS_APPROVED) {
                $this->reservation->releaseForSo($so, "SO cancelled: {$reason}");
            }

            $so->update([
                'status' => SalesOrder::STATUS_CANCELLED,
                'cancelled_at' => now(),
                'cancelled_by' => $by->id,
                'cancel_reason' => $reason,
            ]);

            return $so->refresh();
        });
    }

    /**
     * Update SO status setelah DO posted (delivered).
     *
     * - Semua items qty_delivered >= qty → status `delivered`.
     * - Sebagian → `partially_delivered`.
     * Dipanggil dari DeliveryOrderService::markDelivered().
     */
    public function updateStatusAfterDo(SalesOrder $so): SalesOrder
    {
        $so->load('items');

        if (in_array($so->status, [
            SalesOrder::STATUS_CANCELLED,
            SalesOrder::STATUS_REJECTED,
        ], true)) {
            return $so;
        }

        $allDelivered = $so->items->every(
            fn ($item) => (int) $item->qty_delivered >= (int) $item->qty,
        );
        $anyDelivered = $so->items->contains(fn ($item) => (int) $item->qty_delivered > 0);

        if ($allDelivered) {
            $so->update(['status' => SalesOrder::STATUS_DELIVERED]);
        } elseif ($anyDelivered) {
            $so->update(['status' => SalesOrder::STATUS_PARTIALLY_DELIVERED]);
        }

        return $so->refresh();
    }

    public function recomputeTotals(SalesOrder $so): void
    {
        $subtotal = (float) $so->items()->sum('line_subtotal');

        $headerDiscAmount = 0.0;
        if ($so->header_discount_type === 'rp') {
            $headerDiscAmount = min($subtotal, (float) $so->header_discount_value);
        } elseif ($so->header_discount_type === 'percent') {
            $headerDiscAmount = round($subtotal * (float) $so->header_discount_value / 100, 2);
        }

        $so->update([
            'subtotal' => $subtotal,
            'header_discount_amount' => $headerDiscAmount,
            'total' => max(0, $subtotal - $headerDiscAmount),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function buildCustomerSnapshot(Customer $c): array
    {
        return [
            'id' => $c->id,
            'code' => $c->code,
            'name' => $c->name,
            'owner_name' => $c->owner_name,
            'phone' => $c->phone,
            'whatsapp' => $c->whatsapp,
            'email' => $c->email,
            'address' => $c->address,
            'city' => $c->city,
            'province' => $c->province,
            'npwp' => $c->npwp,
            'price_tier_id' => $c->price_tier_id,
            'credit_limit' => (float) $c->credit_limit,
            'payment_term_days' => (int) $c->payment_term_days,
            'snapshotted_at' => now()->toIso8601String(),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $itemsData
     */
    private function syncItems(SalesOrder $so, Customer $customer, array $itemsData): void
    {
        $so->items()->delete();

        foreach ($itemsData as $idx => $row) {
            /** @var Product $product */
            $product = Product::query()->findOrFail($row['product_id']);
            /** @var ProductUnit $unit */
            $unit = ProductUnit::query()->where('product_id', $product->id)->findOrFail($row['product_unit_id']);

            // Resolve unit_price dari product_prices × tier customer.
            // Sales TIDAK boleh override harga (rule); fallback ke 0 kalau
            // belum ter-set.
            $isBonus = (bool) ($row['is_bonus'] ?? false);
            $unitPrice = $isBonus ? 0.0 : $this->resolvePrice($product->id, $unit->id, (int) $customer->price_tier_id);

            $z1 = (float) ($row['discount_z1_pct'] ?? 0);
            $z2 = (float) ($row['discount_z2_pct'] ?? 0);
            $netPrice = SoItem::computeUnitNetPrice($unitPrice, $z1, $z2, $isBonus);

            SoItem::create([
                'sales_order_id' => $so->id,
                'product_id' => $product->id,
                'product_unit_id' => $unit->id,
                'product_name_snapshot' => $product->name,
                'product_sku_snapshot' => $product->sku,
                'product_unit_name_snapshot' => $unit->name,
                'qty' => (int) $row['qty'],
                'unit_price' => $unitPrice,
                'discount_z1_pct' => $z1,
                'discount_z2_pct' => $z2,
                'unit_net_price' => $netPrice,
                'line_subtotal' => round($netPrice * (int) $row['qty'], 2),
                'is_bonus' => $isBonus,
                'notes' => $row['notes'] ?? null,
                'sort_order' => $idx,
            ]);
        }
    }

    private function resolvePrice(int $productId, int $unitId, int $tierId): float
    {
        $price = ProductPrice::query()
            ->where('product_id', $productId)
            ->where('product_unit_id', $unitId)
            ->where('price_tier_id', $tierId)
            ->value('price');

        return (float) ($price ?? 0);
    }

    private function assertCustomerOk(Customer $customer): void
    {
        if (! $customer->is_active) {
            throw ValidationException::withMessages([
                'customer_id' => 'Customer tidak aktif.',
            ]);
        }

        if ($customer->hasTag('problem_outlet')) {
            throw ValidationException::withMessages([
                'customer_id' => 'Customer ditandai problem_outlet — SO baru di-block. Hubungi admin untuk clear flag.',
            ]);
        }
    }
}
