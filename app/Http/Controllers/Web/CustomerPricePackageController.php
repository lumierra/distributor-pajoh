<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerProductPricePackage;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CustomerPricePackageController extends Controller
{
    /**
     * Hanya produk yang SUDAH di-assign paket untuk customer ini (+ daftar paket
     * produk tsb agar bisa diganti di dropdown). Tidak menarik semua produk.
     * Dipanggil saat modal Paket Harga dibuka.
     */
    public function index(Customer $customer): JsonResponse
    {
        $this->authorize('view', $customer);

        $assignments = CustomerProductPricePackage::query()
            ->where('customer_id', $customer->id)
            ->with(['product:id,sku,name', 'product.pricePackages:id,product_id,name,sort_order'])
            ->get();

        $rows = $assignments
            ->filter(fn (CustomerProductPricePackage $a) => $a->product !== null)
            ->map(fn (CustomerProductPricePackage $a): array => $this->rowFor($a->product, $a->price_package_id))
            ->values();

        return response()->json([
            'rows' => $rows,
        ]);
    }

    /**
     * Cari produk (yang punya paket harga) untuk ditambahkan ke assignment.
     * Return maks 20 produk beserta paket-paketnya. Query 'q' opsional.
     */
    public function search(Request $request, Customer $customer): JsonResponse
    {
        $this->authorize('view', $customer);

        $q = trim((string) $request->input('q', ''));

        $products = Product::query()
            ->where('is_active', true)
            ->whereHas('pricePackages')
            ->when($q !== '', function ($query) use ($q): void {
                $query->where(function ($sub) use ($q): void {
                    $sub->where('name', 'like', "%{$q}%")
                        ->orWhere('sku', 'like', "%{$q}%");
                });
            })
            ->with(['pricePackages:id,product_id,name,sort_order'])
            ->orderBy('name')
            ->limit(20)
            ->get(['id', 'sku', 'name']);

        $rows = $products->map(fn (Product $p): array => $this->rowFor($p, null));

        return response()->json([
            'rows' => $rows,
        ]);
    }

    /**
     * Sync (reconcile) assignment paket harga customer ini dengan daftar yang dikirim.
     * Input: rows => [{product_id, price_package_id}, ...] — daftar LENGKAP yang aktif.
     * - Row valid → upsert.
     * - Row dengan price_package_id null/empty → tidak disimpan (di-skip).
     * - Assignment lama yang produknya TIDAK ada di daftar → dihapus (dikembalikan
     *   ke harga standar). Ini menangani produk yang di-remove dari UI.
     */
    public function sync(Request $request, Customer $customer): RedirectResponse
    {
        $this->authorize('update', $customer);

        $data = $request->validate([
            'rows' => ['present', 'array'],
            'rows.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'rows.*.price_package_id' => ['nullable', 'integer', 'exists:product_price_packages,id'],
        ]);

        DB::transaction(function () use ($data, $customer, $request): void {
            $keptProductIds = [];

            foreach ($data['rows'] as $row) {
                $productId = (int) $row['product_id'];
                $packageId = $row['price_package_id'] ?? null;

                if ($packageId === null || $packageId === '') {
                    continue;
                }

                // Pastikan paket memang milik produk ini (cegah cross-product).
                $belongs = DB::table('product_price_packages')
                    ->where('id', $packageId)
                    ->where('product_id', $productId)
                    ->exists();
                if (! $belongs) {
                    continue;
                }

                CustomerProductPricePackage::updateOrCreate(
                    ['customer_id' => $customer->id, 'product_id' => $productId],
                    [
                        'price_package_id' => (int) $packageId,
                        'created_by' => $request->user()?->id,
                    ],
                );
                $keptProductIds[] = $productId;
            }

            // Hapus assignment lama yang tak lagi ada di daftar.
            CustomerProductPricePackage::query()
                ->where('customer_id', $customer->id)
                ->whereNotIn('product_id', $keptProductIds ?: [0])
                ->delete();
        });

        return back()->with('flash.success', 'Paket harga per produk diperbarui.');
    }

    /**
     * Bentuk 1 baris untuk frontend: identitas produk + paket tersedia + paket terpilih.
     */
    private function rowFor(Product $product, ?int $selectedPackageId): array
    {
        return [
            'product_id' => $product->id,
            'product_sku' => $product->sku,
            'product_name' => $product->name,
            'packages' => $product->pricePackages
                ->map(fn ($pkg): array => ['id' => $pkg->id, 'name' => $pkg->name])
                ->values(),
            'price_package_id' => $selectedPackageId,
        ];
    }
}
