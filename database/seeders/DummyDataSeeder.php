<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\CustomerType;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductGroup;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\SupplierCategory;
use App\Models\Unit;
use App\Models\User;
use App\Services\Product\ProductService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Data contoh (bukan untuk produksi) supaya alur Sales/Produk/Produk Group
 * bisa langsung dicoba: beberapa supplier, customer, sales, produk dgn
 * satuan + paket harga, dan 1 product group yang meng-assign produk ke sales.
 *
 * Jalankan manual: php artisan db:seed --class=DummyDataSeeder
 */
class DummyDataSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->whereHas('role', fn ($q) => $q->where('code', Role::CODE_SUPERADMIN))->first();
        $this->actingUserId = $admin?->id;

        $suppliers = $this->seedSuppliers();
        $customers = $this->seedCustomers();
        $salesUsers = $this->seedSalesUsers();
        $products = $this->seedProducts($suppliers);
        $this->seedProductGroup($products, $salesUsers);

        $this->command?->info('Dummy data selesai: '
            .count($suppliers).' supplier, '
            .count($customers).' customer, '
            .count($salesUsers).' sales, '
            .count($products).' produk.');
    }

    private ?int $actingUserId = null;

    /**
     * @return array<int, Supplier>
     */
    private function seedSuppliers(): array
    {
        $categoryId = fn (string $code) => SupplierCategory::query()->where('code', $code)->value('id');

        $rows = [
            ['code' => 'SUP-0003', 'name' => 'PT. Garudafood Putra Putri Jaya', 'legal_form' => 'PT', 'category' => 'SNACK', 'city' => 'Jakarta', 'phone' => '021-5551001'],
            ['code' => 'SUP-0004', 'name' => 'PT. Unilever Indonesia', 'legal_form' => 'PT', 'category' => 'PERSONAL', 'city' => 'Jakarta', 'phone' => '021-5551002'],
        ];

        $suppliers = Supplier::query()->whereIn('code', ['SUP-0001', 'SUP-0002'])->get()->all();

        foreach ($rows as $row) {
            $suppliers[] = Supplier::query()->firstOrCreate(
                ['code' => $row['code']],
                [
                    'name' => $row['name'],
                    'legal_form' => $row['legal_form'],
                    'supplier_category_id' => $categoryId($row['category']),
                    'phone' => $row['phone'],
                    'email' => strtolower(str($row['name'])->slug()).'@example.test',
                    'address' => 'Jl. Contoh No. 1',
                    'city' => $row['city'],
                    'province' => 'DKI Jakarta',
                    'payment_term_days' => 30,
                    'default_lead_time_days' => 3,
                    'is_active' => true,
                    'created_by' => $this->actingUserId,
                    'updated_by' => $this->actingUserId,
                ],
            );
        }

        return $suppliers;
    }

    /**
     * @return array<int, Customer>
     */
    private function seedCustomers(): array
    {
        $typeId = fn (string $code) => CustomerType::query()->where('code', $code)->value('id');

        $rows = [
            ['code' => 'CUST-0001', 'name' => 'Toko Sumber Rejeki', 'type' => 'WARUNG', 'city' => 'Langsa', 'credit_limit' => 5_000_000, 'term' => 7],
            ['code' => 'CUST-0002', 'name' => 'Kelontong Berkah Jaya', 'type' => 'KELONTONG', 'city' => 'Langsa', 'credit_limit' => 8_000_000, 'term' => 14],
            ['code' => 'CUST-0003', 'name' => 'Mini Market Cahaya', 'type' => 'MINI_MARKET', 'city' => 'Kuala Simpang', 'credit_limit' => 15_000_000, 'term' => 14],
            ['code' => 'CUST-0004', 'name' => 'Grosir Sejahtera', 'type' => 'GROSIR', 'city' => 'Kuala Simpang', 'credit_limit' => 25_000_000, 'term' => 30],
            ['code' => 'CUST-0005', 'name' => 'Warung Bu Siti', 'type' => 'WARUNG', 'city' => 'Langsa', 'credit_limit' => 3_000_000, 'term' => 7],
        ];

        return array_map(function ($row) use ($typeId) {
            return Customer::query()->firstOrCreate(
                ['code' => $row['code']],
                [
                    'name' => $row['name'],
                    'customer_type_id' => $typeId($row['type']),
                    'phone' => '0852'.random_int(10000000, 99999999),
                    'address' => 'Jl. Contoh Raya',
                    'city' => $row['city'],
                    'province' => 'Aceh',
                    'credit_limit' => $row['credit_limit'],
                    'payment_term_days' => $row['term'],
                    'is_active' => true,
                    'created_by' => $this->actingUserId,
                    'updated_by' => $this->actingUserId,
                ],
            );
        }, $rows);
    }

    /**
     * @return array<int, User>
     */
    private function seedSalesUsers(): array
    {
        $salesRoleId = Role::query()->where('code', Role::CODE_SALES)->value('id');

        $rows = [
            ['username' => 'sales.andi', 'name' => 'Andi Saputra'],
            ['username' => 'sales.budi', 'name' => 'Budi Santoso'],
        ];

        return array_map(function ($row) use ($salesRoleId) {
            return User::query()->firstOrCreate(
                ['username' => $row['username']],
                [
                    'name' => $row['name'],
                    'email' => $row['username'].'@pajoh.test',
                    'password' => Hash::make('password'),
                    'role_id' => $salesRoleId,
                    'is_active' => true,
                    'force_password_change' => true,
                    'created_by' => $this->actingUserId,
                    'updated_by' => $this->actingUserId,
                ],
            );
        }, $rows);
    }

    /**
     * @param  array<int, Supplier>  $suppliers
     * @return array<int, Product>
     */
    private function seedProducts(array $suppliers): array
    {
        if (Product::query()->exists()) {
            return Product::query()->limit(10)->get()->all();
        }

        $unitId = fn (string $name) => Unit::query()->where('name', $name)->value('id');
        $categoryId = fn (string $code) => ProductCategory::query()->where('code', $code)->value('id');
        $supplierByCode = collect($suppliers)->keyBy('code');

        $service = app(ProductService::class);

        $specs = [
            [
                'sku' => 'IDF-001',
                'name' => 'Indomie Goreng',
                'supplier' => 'SUP-0001',
                'category' => 'MIE',
                'units' => [
                    ['unit' => 'PCS', 'qty_to_base' => 1],
                    ['unit' => 'KARDUS', 'qty_to_base' => 40],
                ],
                'packages' => [
                    [
                        'name' => 'Harga Reguler',
                        'items' => [
                            ['unit_index' => 0, 'cost_price' => 2_800, 'sell_price' => 3_200],
                            ['unit_index' => 1, 'cost_price' => 108_000, 'sell_price' => 124_000],
                        ],
                    ],
                    [
                        'name' => 'Harga Grosir',
                        'items' => [
                            ['unit_index' => 1, 'cost_price' => 105_000, 'sell_price' => 118_000],
                        ],
                    ],
                ],
            ],
            [
                'sku' => 'IDF-002',
                'name' => 'Indomie Ayam Bawang',
                'supplier' => 'SUP-0001',
                'category' => 'MIE',
                'units' => [
                    ['unit' => 'PCS', 'qty_to_base' => 1],
                    ['unit' => 'KARDUS', 'qty_to_base' => 40],
                ],
                'packages' => [
                    [
                        'name' => 'Harga Reguler',
                        'items' => [
                            ['unit_index' => 0, 'cost_price' => 2_750, 'sell_price' => 3_150],
                            ['unit_index' => 1, 'cost_price' => 106_000, 'sell_price' => 121_000],
                        ],
                    ],
                ],
            ],
            [
                'sku' => 'MYR-001',
                'name' => 'Biskuit Roma Kelapa',
                'supplier' => 'SUP-0002',
                'category' => 'SNK',
                'units' => [
                    ['unit' => 'PCS', 'qty_to_base' => 1],
                    ['unit' => 'LUSIN', 'qty_to_base' => 12],
                ],
                'packages' => [
                    [
                        'name' => 'Harga Reguler',
                        'items' => [
                            ['unit_index' => 0, 'cost_price' => 6_500, 'sell_price' => 7_500],
                            ['unit_index' => 1, 'cost_price' => 75_000, 'sell_price' => 87_000],
                        ],
                    ],
                    [
                        'name' => 'Harga Grosir',
                        'items' => [
                            ['unit_index' => 1, 'cost_price' => 72_000, 'sell_price' => 82_000],
                        ],
                    ],
                ],
            ],
            [
                'sku' => 'MYR-002',
                'name' => 'Kopiko Kopi Susu',
                'supplier' => 'SUP-0002',
                'category' => 'SNK',
                'units' => [
                    ['unit' => 'PCS', 'qty_to_base' => 1],
                    ['unit' => 'BOX', 'qty_to_base' => 24],
                ],
                'packages' => [
                    [
                        'name' => 'Harga Reguler',
                        'items' => [
                            ['unit_index' => 0, 'cost_price' => 500, 'sell_price' => 750],
                            ['unit_index' => 1, 'cost_price' => 11_000, 'sell_price' => 16_500],
                        ],
                    ],
                ],
            ],
            [
                'sku' => 'GRD-001',
                'name' => 'Chitato Sapi Panggang',
                'supplier' => 'SUP-0003',
                'category' => 'SNK',
                'units' => [
                    ['unit' => 'PCS', 'qty_to_base' => 1],
                    ['unit' => 'KARDUS', 'qty_to_base' => 20],
                ],
                'packages' => [
                    [
                        'name' => 'Harga Reguler',
                        'items' => [
                            ['unit_index' => 0, 'cost_price' => 8_500, 'sell_price' => 10_000],
                            ['unit_index' => 1, 'cost_price' => 165_000, 'sell_price' => 195_000],
                        ],
                    ],
                    [
                        'name' => 'Harga Grosir',
                        'items' => [
                            ['unit_index' => 1, 'cost_price' => 160_000, 'sell_price' => 185_000],
                        ],
                    ],
                ],
            ],
            [
                'sku' => 'ULV-001',
                'name' => 'Sabun Lifebuoy 85gr',
                'supplier' => 'SUP-0004',
                'category' => 'PRC',
                'units' => [
                    ['unit' => 'PCS', 'qty_to_base' => 1],
                    ['unit' => 'LUSIN', 'qty_to_base' => 12],
                ],
                'packages' => [
                    [
                        'name' => 'Harga Reguler',
                        'items' => [
                            ['unit_index' => 0, 'cost_price' => 3_200, 'sell_price' => 3_800],
                            ['unit_index' => 1, 'cost_price' => 37_000, 'sell_price' => 44_000],
                        ],
                    ],
                ],
            ],
        ];

        $products = [];
        foreach ($specs as $spec) {
            $supplier = $supplierByCode->get($spec['supplier']);
            if (! $supplier) {
                continue;
            }

            $units = array_map(fn ($u) => [
                'unit_id' => $unitId($u['unit']),
                'qty_to_base' => $u['qty_to_base'],
                'barcode' => null,
            ], $spec['units']);

            $products[] = $service->create(
                [
                    'supplier_id' => $supplier->id,
                    'sku' => $spec['sku'],
                    'name' => $spec['name'],
                    'category_id' => $categoryId($spec['category']),
                    'description' => null,
                    'is_active' => true,
                    'created_by' => $this->actingUserId,
                    'updated_by' => $this->actingUserId,
                ],
                $units,
                $spec['packages'],
            );
        }

        return $products;
    }

    /**
     * @param  array<int, Product>  $products
     * @param  array<int, User>  $salesUsers
     */
    private function seedProductGroup(array $products, array $salesUsers): void
    {
        if (empty($products) || empty($salesUsers)) {
            return;
        }

        $group = ProductGroup::query()->firstOrCreate(
            ['code' => 'GRP_REGULER'],
            [
                'name' => 'Grup Reguler',
                'description' => 'Produk dgn paket harga reguler untuk sales lapangan.',
                'is_active' => true,
                'sort_order' => 1,
                'created_by' => $this->actingUserId,
                'updated_by' => $this->actingUserId,
            ],
        );

        if ($group->products()->exists()) {
            return;
        }

        $sync = [];
        foreach ($products as $product) {
            $regularPackageId = $product->pricePackages()
                ->where('name', 'Harga Reguler')
                ->value('id') ?? $product->pricePackages()->value('id');

            $sync[$product->id] = ['price_package_id' => $regularPackageId];
        }
        $group->products()->sync($sync);

        $group->salesUsers()->sync(collect($salesUsers)->mapWithKeys(fn ($u) => [
            $u->id => ['monthly_limit' => null, 'created_by' => $this->actingUserId],
        ])->all());
    }
}
