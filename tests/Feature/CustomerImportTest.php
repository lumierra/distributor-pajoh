<?php

use App\Exports\Customer\CustomerTemplateExport;
use App\Models\Customer;
use App\Models\CustomerType;
use App\Models\PriceTier;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\CustomerTypeSeeder;
use Database\Seeders\MenuSeeder;
use Database\Seeders\PriceTierSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Facades\Excel;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(SettingSeeder::class);
    $this->seed(RoleSeeder::class);
    $this->seed(MenuSeeder::class);
    $this->seed(RolePermissionSeeder::class);
    $this->seed(CustomerTypeSeeder::class);
    $this->seed(PriceTierSeeder::class);
});

function ciUser(string $roleCode): User
{
    $uniq = uniqid('', true);

    return User::create([
        'name' => 'U-'.$roleCode.'-'.$uniq,
        'username' => 'u_'.$roleCode.'_'.str_replace('.', '', $uniq),
        'password' => 'secret1234',
        'role_id' => Role::ofCode($roleCode)->value('id'),
        'is_active' => true,
    ]);
}

function ciUploadXlsx(array $rows): UploadedFile
{
    // rows: array of associative arrays dengan keys = header
    $headers = [
        'name', 'owner_name', 'customer_type_code', 'price_tier_code',
        'whatsapp', 'area', 'assigned_sales_username',
        'credit_limit', 'payment_term_days', 'is_active', 'notes',
    ];

    $data = [$headers];
    foreach ($rows as $r) {
        $line = [];
        foreach ($headers as $h) {
            $line[] = $r[$h] ?? '';
        }
        $data[] = $line;
    }

    $tmpPath = tempnam(sys_get_temp_dir(), 'customer_import_').'.xlsx';

    $export = new class($data) implements FromArray, WithHeadings
    {
        public function __construct(private readonly array $data) {}

        public function headings(): array
        {
            return $this->data[0];
        }

        public function array(): array
        {
            return array_slice($this->data, 1);
        }
    };
    Excel::store($export, basename($tmpPath), 'local');

    $storagePath = storage_path('app/private/'.basename($tmpPath));
    if (! file_exists($storagePath)) {
        $storagePath = storage_path('app/'.basename($tmpPath));
    }

    return new UploadedFile(
        $storagePath,
        'customers.xlsx',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        null,
        true,
    );
}

test('admin bisa download template customer', function (): void {
    $admin = ciUser(Role::CODE_SUPERADMIN);

    $this->actingAs($admin)
        ->get(route('customers.import.template'))
        ->assertOk();
});

test('kasir tidak boleh download template (forbidden)', function (): void {
    $kasir = ciUser(Role::CODE_KASIR);

    $this->actingAs($kasir)
        ->get(route('customers.import.template'))
        ->assertForbidden();
});

test('template contains sheet Customers + sheet Reference', function (): void {
    $admin = ciUser(Role::CODE_SUPERADMIN);

    // Force render template kalau perlu (sanity check di-export object langsung)
    $export = new CustomerTemplateExport;
    expect($export->sheets())->toHaveCount(2);
});

test('import sukses untuk semua baris valid', function (): void {
    $admin = ciUser(Role::CODE_SUPERADMIN);

    $tierCode = PriceTier::query()->where('is_active', true)->value('code');
    $typeCode = CustomerType::query()->where('is_active', true)->value('code');

    $file = ciUploadXlsx([
        [
            'name' => 'Toko A',
            'price_tier_code' => $tierCode,
            'customer_type_code' => $typeCode,
            'payment_term_days' => 14,
        ],
        [
            'name' => 'Toko B',
            'price_tier_code' => $tierCode,
            'payment_term_days' => 7,
        ],
    ]);

    $this->actingAs($admin)
        ->post(route('customers.import'), ['file' => $file])
        ->assertRedirect();

    expect(Customer::where('name', 'Toko A')->exists())->toBeTrue();
    expect(Customer::where('name', 'Toko B')->exists())->toBeTrue();
});

test('import partial — baris invalid di-skip dan dilaporkan', function (): void {
    $admin = ciUser(Role::CODE_SUPERADMIN);

    $tierCode = PriceTier::query()->where('is_active', true)->value('code');

    $file = ciUploadXlsx([
        [
            'name' => 'Toko Valid',
            'price_tier_code' => $tierCode,
        ],
        [
            // name kosong → invalid (required)
            'price_tier_code' => $tierCode,
        ],
        [
            'name' => 'Toko Bad Tier',
            'price_tier_code' => 'NONEXIST_TIER_X', // tier code tidak ada
        ],
    ]);

    $response = $this->actingAs($admin)
        ->post(route('customers.import'), ['file' => $file]);

    $response->assertRedirect();
    expect(Customer::where('name', 'Toko Valid')->exists())->toBeTrue();
    expect(Customer::where('name', 'Toko Bad Tier')->exists())->toBeFalse();
    expect(session('customer_import.errors'))->not->toBeEmpty();
});

test('download error report setelah import dengan error', function (): void {
    $admin = ciUser(Role::CODE_SUPERADMIN);

    $tierCode = PriceTier::query()->where('is_active', true)->value('code');

    $file = ciUploadXlsx([
        [
            'name' => 'Bad Row',
            'price_tier_code' => 'INVALID',
        ],
    ]);

    $this->actingAs($admin)
        ->post(route('customers.import'), ['file' => $file])
        ->assertRedirect();

    // session error dari post sebelumnya
    $errors = session('customer_import.errors');
    expect($errors)->not->toBeEmpty();

    // Download endpoint hanya bekerja kalau session masih ada
    // (di test, session di-share antar request via testing helper)
    $this->actingAs($admin)
        ->withSession(['customer_import.errors' => $errors])
        ->get(route('customers.import.errors'))
        ->assertOk();
});

test('kasir tidak boleh import (forbidden)', function (): void {
    $kasir = ciUser(Role::CODE_KASIR);
    $tierCode = PriceTier::query()->where('is_active', true)->value('code');

    $file = ciUploadXlsx([
        ['name' => 'Toko X', 'price_tier_code' => $tierCode],
    ]);

    $this->actingAs($kasir)
        ->post(route('customers.import'), ['file' => $file])
        ->assertForbidden();
});

test('file selain xlsx/xls/csv ditolak', function (): void {
    $admin = ciUser(Role::CODE_SUPERADMIN);
    $file = UploadedFile::fake()->create('not-excel.pdf', 100, 'application/pdf');

    $this->actingAs($admin)
        ->post(route('customers.import'), ['file' => $file])
        ->assertSessionHasErrors('file');
});
