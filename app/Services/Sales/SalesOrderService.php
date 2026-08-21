<?php

namespace App\Services\Sales;

use App\Exceptions\InsufficientStockException;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Role;
use App\Models\SalesOrder;
use App\Models\SoItem;
use App\Models\User;
use App\Services\Customer\CustomerCreditLimitService;
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
        private readonly CustomerCreditLimitService $creditLimits,
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
                'cashback' => $headerData['cashback'] ?? 0,
                'notes' => $headerData['notes'] ?? null,
                'created_by' => $by->id,
            ]);
            $so->so_number = $this->numbering->next('so');
            $so->save();

            $this->syncItems($so, $customer, $itemsData);
            $this->recomputeTotals($so);
            $this->assertSalesProductGroupRules($so->fresh());

            return $so->refresh();
        });
    }

    /**
     * Update draft SO header + items.
     *
     * @param  array<string, mixed>  $headerData
     * @param  array<int, array<string, mixed>>  $itemsData
     */
    /**
     * Edit SO. Fleksibel sesuai status (lihat SalesOrder::canBeEdited):
     *  - draft / submitted / pending_credit_review: cukup tulis ulang item &
     *    total. Stok belum dipotong, jadi tak ada penyesuaian stok.
     *  - approved (belum ada DO): stok fisik SUDAH dipotong saat approve. Maka:
     *    kembalikan dulu stok item lama (returnForSo), tulis ulang item, lalu
     *    potong ulang sesuai item baru (consumeForSo). Status TETAP approved.
     *
     * Nama tetap `updateDraft` demi kompat pemanggil; efektif "update SO".
     */
    public function updateDraft(SalesOrder $so, array $headerData, array $itemsData, User $by): SalesOrder
    {
        if (! $so->canBeEdited()) {
            throw ValidationException::withMessages([
                'status' => $so->status === SalesOrder::STATUS_APPROVED
                    ? 'SO sudah punya Surat Jalan / sebagian terkirim. Sesuaikan lewat retur / credit note.'
                    : 'SO sudah tidak bisa diedit pada status ini.',
            ]);
        }

        $isApproved = $so->status === SalesOrder::STATUS_APPROVED;

        return DB::transaction(function () use ($so, $headerData, $itemsData, $by, $isApproved): SalesOrder {
            /** @var Customer $customer */
            $customer = Customer::query()->findOrFail($headerData['customer_id']);
            $this->assertCustomerOk($customer);

            $soDate = $headerData['so_date'] instanceof CarbonInterface
                ? $headerData['so_date']
                : Carbon::parse($headerData['so_date']);

            $paymentTerm = $headerData['payment_term_days'] ?? (int) $customer->payment_term_days;
            $dueDate = $soDate->copy()->addDays((int) $paymentTerm);

            // Untuk SO approved: kembalikan dulu stok yang sudah dipotong (item
            // lama) sebelum item ditulis ulang. Aman karena belum ada DO
            // (dijamin canBeEdited) → semua catatan batch consumed_by_do_id null.
            if ($isApproved) {
                $this->reservation->returnForSo($so, 'Edit SO approved: reset stok sebelum re-sync.', $by);
            }

            $so->fill([
                'customer_id' => $customer->id,
                'so_date' => $soDate,
                'eta_date' => $headerData['eta_date'] ?? null,
                'payment_term_days' => $paymentTerm,
                'due_date' => $dueDate,
                'header_discount_type' => $headerData['header_discount_type'] ?? null,
                'header_discount_value' => $headerData['header_discount_value'] ?? 0,
                'cashback' => $headerData['cashback'] ?? 0,
                'notes' => $headerData['notes'] ?? null,
                'fiscal_year' => (int) $soDate->format('Y'),
                'updated_by' => $by->id,
            ]);
            $so->save();

            $this->syncItems($so, $customer, $itemsData);
            $this->recomputeTotals($so);
            $this->assertSalesProductGroupRules($so->fresh());

            // Untuk SO approved: potong ulang stok sesuai item baru. Cek stok
            // dulu supaya kalau tak cukup → rollback (termasuk return di atas).
            if ($isApproved) {
                $allowNegative = (bool) $this->settings->get('inventory.allow_negative_stock', false);
                if (! $allowNegative) {
                    try {
                        $this->reservation->assertStockAvailable($so->refresh());
                    } catch (InsufficientStockException $e) {
                        throw ValidationException::withMessages([
                            'stock' => "Stok tidak cukup untuk item baru. Produk #{$e->productId}: minta {$e->requested}, tersedia {$e->available}.",
                        ]);
                    }
                }
                $this->reservation->consumeForSo($so->refresh(), $by);
            }

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

            // 2. Credit check per supplier (hard block — sales tidak bisa request
            //    produk yang akan melebihi credit limit per supplier).
            $incomingPerSupplier = [];
            foreach ($so->items as $item) {
                if ($item->is_bonus || ! $item->supplier_id) {
                    continue;
                }
                $incomingPerSupplier[(int) $item->supplier_id] =
                    ($incomingPerSupplier[(int) $item->supplier_id] ?? 0.0)
                    + (float) $item->line_subtotal;
            }
            $this->creditLimits->assertCanCharge($so->customer, $incomingPerSupplier, 'credit_limit');

            // 3. Legacy global outstanding snapshot (informational only).
            $outstanding = $this->outstanding->getTotal($so->customer);

            $so->update([
                'status' => SalesOrder::STATUS_SUBMITTED,
                'submitted_at' => now(),
                'submitted_by' => $by->id,
                'credit_review_required' => false,
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

            // Potong stok fisik SEKARANG (model baru: stok keluar saat approve,
            // bukan saat DO delivered). Menulis sale_out per batch & mencatat
            // batch di so_reservations untuk penyusunan DO.
            $this->reservation->consumeForSo($so->refresh(), $by);

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

        // Blokir kalau ada faktur SO ini yang sudah ada pembayaran (sebagian/
        // lunas). Harus lewat retur / credit note, bukan cancel SO.
        $hasPaidInvoice = DB::table('invoices')
            ->where('sales_order_id', $so->id)
            ->where('paid_amount', '>', 0)
            ->exists();
        if ($hasPaidInvoice) {
            throw ValidationException::withMessages([
                'status' => 'SO tidak bisa dibatalkan karena fakturnya sudah menerima pembayaran. Gunakan retur / credit note.',
            ]);
        }

        return DB::transaction(function () use ($so, $reason, $by): SalesOrder {
            // Kalau sudah approved, stok fisik sudah dipotong saat approve →
            // kembalikan stok untuk batch yang BELUM dikirim lewat DO.
            if ($so->status === SalesOrder::STATUS_APPROVED) {
                $this->reservation->returnForSo($so, "SO cancelled: {$reason}", $by);
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

        // Cashback: potongan rupiah tingkat SO, di luar header discount. Dibatasi
        // agar total tidak negatif.
        $cashback = min(
            max(0.0, (float) $so->cashback),
            max(0.0, $subtotal - $headerDiscAmount),
        );

        $so->update([
            'subtotal' => $subtotal,
            'header_discount_amount' => $headerDiscAmount,
            'cashback' => $cashback,
            'total' => max(0, $subtotal - $headerDiscAmount - $cashback),
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

            // 1 produk = 1 supplier: supplier line diambil otomatis dari produk,
            // bukan dipilih sales.
            $supplierId = (int) $product->supplier_id;

            $isBonus = (bool) ($row['is_bonus'] ?? false);
            $unitPrice = $isBonus ? 0.0 : $this->resolveSellPrice($product, $unit->id, $so->sales_id, $customer->id);

            // Diskon per-item: tipe percent|rp, value per unit. Bonus → tanpa diskon.
            $discountType = $isBonus ? null : ($row['discount_type'] ?? null);
            $discountValue = $isBonus ? 0.0 : (float) ($row['discount_value'] ?? 0);
            if (! in_array($discountType, SoItem::DISCOUNT_TYPES, true)) {
                $discountType = null;
                $discountValue = 0.0;
            }

            $netPrice = SoItem::computeUnitNetPrice($unitPrice, $discountType, $discountValue, $isBonus);

            SoItem::create([
                'sales_order_id' => $so->id,
                'product_id' => $product->id,
                'supplier_id' => $supplierId,
                'product_unit_id' => $unit->id,
                'product_name_snapshot' => $product->name,
                'product_sku_snapshot' => $product->sku,
                'product_unit_name_snapshot' => $unit->name,
                'qty' => (int) $row['qty'],
                'unit_price' => $unitPrice,
                'discount_z1_pct' => 0,
                'discount_z2_pct' => 0,
                'discount_type' => $discountType,
                'discount_value' => $discountValue,
                'unit_net_price' => $netPrice,
                'line_subtotal' => round($netPrice * (int) $row['qty'], 2),
                'is_bonus' => $isBonus,
                'notes' => $row['notes'] ?? null,
                'sort_order' => $idx,
            ]);
        }
    }

    /**
     * Sell price untuk (produk × satuan). Paket harga dipilih dengan urutan
     * prioritas (yang di-atas menang):
     *  1. Paket yang di-assign ke customer ini untuk produk ini (per-produk).
     *  2. Paket dari Product Group aktif milik sales yang memuat produk ini
     *     (pivot product_group_items.price_package_id).
     *  3. Paket pertama produk (urut sort_order).
     * Ambil sell_price baris paket untuk satuan tsb; fallback 0.
     *
     * Publik agar controller bisa memakai resolver yang SAMA saat menampilkan
     * harga di form create (preview) supaya konsisten dengan harga tersimpan.
     */
    public function resolveSellPrice(Product $product, int $unitId, ?int $salesId, ?int $customerId = null): float
    {
        // Kumpulkan kandidat paket sesuai prioritas, coba satu per satu:
        // paket pertama yang PUNYA harga untuk satuan ini yang dipakai.
        $candidates = [];

        if ($customerId) {
            // (1) assignment per-produk untuk customer ini.
            $customerPackageId = DB::table('customer_product_price_packages')
                ->where('customer_id', $customerId)
                ->where('product_id', $product->id)
                ->value('price_package_id');
            if ($customerPackageId !== null) {
                $candidates[] = (int) $customerPackageId;
            }
        }

        // (2) paket dari sales group.
        if ($salesId) {
            $groupPackageId = DB::table('product_group_items')
                ->join('sales_product_groups', 'sales_product_groups.product_group_id', '=', 'product_group_items.product_group_id')
                ->join('product_groups', 'product_groups.id', '=', 'product_group_items.product_group_id')
                ->where('sales_product_groups.user_id', $salesId)
                ->where('product_group_items.product_id', $product->id)
                ->where('product_groups.is_active', true)
                ->whereNull('product_groups.deleted_at')
                ->whereNotNull('product_group_items.price_package_id')
                ->value('product_group_items.price_package_id');
            if ($groupPackageId !== null) {
                $candidates[] = (int) $groupPackageId;
            }
        }

        // (3) paket pertama produk.
        if ($firstPackageId = $product->pricePackages()->orderBy('sort_order')->value('id')) {
            $candidates[] = (int) $firstPackageId;
        }

        foreach ($candidates as $packageId) {
            $price = DB::table('product_price_package_items')
                ->where('price_package_id', $packageId)
                ->where('product_unit_id', $unitId)
                ->value('sell_price');
            if ($price !== null) {
                return (float) $price;
            }
        }

        return 0.0;
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

    /**
     * Saat actor adalah sales: tiap produk yg dijual wajib berada dalam Product Group
     * yang ter-assign ke sales tsb, dan total penjualan bulan berjalan per group tidak
     * boleh melebihi `monthly_limit` (kalau di-set). Admin/kasir/superadmin di-bypass.
     *
     * Backwards-compat: kalau sales belum ter-assign group sama sekali, enforcement
     * dilewat (opt-in saat admin sudah set Product Group untuk sales tsb).
     */
    private function assertSalesProductGroupRules(SalesOrder $so): void
    {
        $sales = User::query()->with('role')->find($so->sales_id);
        if (! $sales || $sales->role?->code !== Role::CODE_SALES) {
            return;
        }

        $sales->load(['productGroups' => function ($q): void {
            $q->where('product_groups.is_active', true)->with('products:id');
        }]);

        if ($sales->productGroups->isEmpty()) {
            return;
        }

        $productIdsInSo = $so->items()->pluck('product_id')->unique()->all();
        if (empty($productIdsInSo)) {
            return;
        }

        // 1) Setiap produk di SO harus ada di salah satu group yg ter-assign.
        $allowedProductIds = [];
        foreach ($sales->productGroups as $group) {
            foreach ($group->products as $p) {
                $allowedProductIds[$p->id] = true;
            }
        }

        $forbidden = array_values(array_filter(
            $productIdsInSo,
            fn (int $pid): bool => ! isset($allowedProductIds[$pid]),
        ));

        if (! empty($forbidden)) {
            $names = Product::query()->whereIn('id', $forbidden)->pluck('name')->implode(', ');
            throw ValidationException::withMessages([
                'items' => "Produk berikut tidak ada dalam Product Group sales {$sales->name}: {$names}.",
            ]);
        }

        // 2) Cek monthly_limit per group untuk bulan SO ini.
        $soDate = $so->so_date instanceof CarbonInterface ? $so->so_date : Carbon::parse($so->so_date);
        $monthStart = $soDate->copy()->startOfMonth();
        $monthEnd = $soDate->copy()->endOfMonth();

        foreach ($sales->productGroups as $group) {
            $limit = $group->pivot->monthly_limit ?? null;
            if ($limit === null) {
                continue;
            }

            $groupProductIds = $group->products->pluck('id')->all();
            if (empty($groupProductIds)) {
                continue;
            }

            // Total semua SO bulan ini yang lines-nya menyentuh produk group, exclude SO sendiri.
            $mtd = (float) SoItem::query()
                ->whereIn('product_id', $groupProductIds)
                ->whereHas('salesOrder', function ($q) use ($sales, $monthStart, $monthEnd, $so): void {
                    $q->where('sales_id', $sales->id)
                        ->whereBetween('so_date', [$monthStart, $monthEnd])
                        ->whereNotIn('status', [SalesOrder::STATUS_CANCELLED, SalesOrder::STATUS_REJECTED])
                        ->where('id', '!=', $so->id);
                })
                ->sum('line_subtotal');

            $thisSoTotal = (float) SoItem::query()
                ->where('sales_order_id', $so->id)
                ->whereIn('product_id', $groupProductIds)
                ->sum('line_subtotal');

            if (($mtd + $thisSoTotal) > (float) $limit) {
                $fmt = fn (float $v): string => 'Rp '.number_format($v, 0, ',', '.');
                throw ValidationException::withMessages([
                    'items' => "Limit bulanan group {$group->name} terlampaui: total {$fmt($mtd + $thisSoTotal)} > limit {$fmt((float) $limit)}.",
                ]);
            }
        }
    }
}
