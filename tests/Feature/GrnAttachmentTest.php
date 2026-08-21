<?php

use App\Models\GoodsReceipt;
use App\Models\GrnAttachment;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Services\Product\ProductService;
use App\Services\Purchasing\GoodsReceiptService;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SettingSeeder;
use Database\Seeders\SuperadminSeeder;
use Database\Seeders\UnitSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed([
        SettingSeeder::class,
        RoleSeeder::class,
        MenuSeeder::class,
        RolePermissionSeeder::class,
        SuperadminSeeder::class,
        UnitSeeder::class,
    ]);
});

/**
 * Bikin 1 GRN penerimaan langsung (draft) + admin, siap diuji lampiran.
 *
 * @return array{admin: User, grn: GoodsReceipt}
 */
function makeDraftDirectGrn(): array
{
    $admin = User::query()->whereHas('role', fn ($q) => $q->where('code', Role::CODE_SUPERADMIN))->firstOrFail();

    $supplier = Supplier::create([
        'code' => 'SUP-ATT',
        'name' => 'Supplier Lampiran',
        'is_active' => true,
        'created_by' => $admin->id,
    ]);

    $pcs = Unit::query()->where('name', 'PCS')->value('id');
    $product = app(ProductService::class)->create(
        [
            'supplier_id' => $supplier->id,
            'sku' => 'ATT-001',
            'name' => 'Produk Lampiran',
            'is_active' => true,
            'created_by' => $admin->id,
        ],
        [['unit_id' => $pcs, 'qty_to_base' => 1, 'barcode' => null]],
        [['name' => 'Harga Reguler', 'items' => [['unit_index' => 0, 'cost_price' => 1000, 'sell_price' => 1500]]]],
    );
    $unit = $product->units()->first();

    $grn = app(GoodsReceiptService::class)->createDraft(
        [
            'supplier_id' => $supplier->id,
            'received_date' => now()->toDateString(),
        ],
        [[
            'po_item_id' => null,
            'product_id' => $product->id,
            'product_unit_id' => $unit->id,
            'batch_code' => 'BATCH-ATT',
            'qty_reguler' => 10,
            'cost_price' => 1000,
            'condition' => 'good',
        ]],
        $admin,
    );

    return ['admin' => $admin, 'grn' => $grn];
}

test('bisa unggah beberapa lampiran sekaligus ke GRN draft', function (): void {
    Storage::fake('local');
    ['admin' => $admin, 'grn' => $grn] = makeDraftDirectGrn();

    $this->actingAs($admin)->post(route('grns.attachments.store', $grn->id), [
        'files' => [
            UploadedFile::fake()->image('surat-1.jpg'),
            UploadedFile::fake()->create('surat-2.pdf', 200, 'application/pdf'),
        ],
    ])->assertRedirect();

    expect($grn->attachments()->count())->toBe(2);

    $att = $grn->attachments()->first();
    expect($att->original_name)->not->toBeEmpty();
    Storage::disk('local')->assertExists($att->file_path);
});

test('lampiran bisa dibuka/di-serve (endpoint show tidak error)', function (): void {
    Storage::fake('local');
    ['admin' => $admin, 'grn' => $grn] = makeDraftDirectGrn();

    $this->actingAs($admin)->post(route('grns.attachments.store', $grn->id), [
        'files' => [UploadedFile::fake()->image('surat.jpg')],
    ])->assertRedirect();

    $att = $grn->attachments()->first();
    $this->actingAs($admin)->get(route('grn-attachments.show', $att->id))->assertOk();
});

test('file melebihi 10MB ditolak', function (): void {
    Storage::fake('local');
    ['admin' => $admin, 'grn' => $grn] = makeDraftDirectGrn();

    $this->actingAs($admin)->post(route('grns.attachments.store', $grn->id), [
        'files' => [UploadedFile::fake()->create('besar.pdf', 11 * 1024, 'application/pdf')],
    ])->assertSessionHasErrors('files.0');

    expect($grn->attachments()->count())->toBe(0);
});

test('lampiran bisa dihapus & file fisik ikut terhapus', function (): void {
    Storage::fake('local');
    ['admin' => $admin, 'grn' => $grn] = makeDraftDirectGrn();

    $this->actingAs($admin)->post(route('grns.attachments.store', $grn->id), [
        'files' => [UploadedFile::fake()->image('surat.jpg')],
    ])->assertRedirect();

    /** @var GrnAttachment $att */
    $att = $grn->attachments()->first();
    Storage::disk('local')->assertExists($att->file_path);

    $this->actingAs($admin)->delete(route('grn-attachments.destroy', $att->id))->assertRedirect();

    Storage::disk('local')->assertMissing($att->file_path);
    expect(GrnAttachment::query()->whereKey($att->id)->exists())->toBeFalse();
});

test('GRN yang sudah posted tidak bisa lagi menerima lampiran', function (): void {
    Storage::fake('local');
    ['admin' => $admin, 'grn' => $grn] = makeDraftDirectGrn();

    $service = app(GoodsReceiptService::class);
    $service->submit($grn, $admin);
    $service->post($grn->refresh(), $admin);
    expect($grn->refresh()->status)->toBe(GoodsReceipt::STATUS_POSTED);

    $this->actingAs($admin)->post(route('grns.attachments.store', $grn->id), [
        'files' => [UploadedFile::fake()->image('telat.jpg')],
    ])->assertForbidden();

    expect($grn->attachments()->count())->toBe(0);
});
