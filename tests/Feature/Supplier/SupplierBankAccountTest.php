<?php

use App\Models\Role;
use App\Models\SupplierBankAccount;
use App\Models\User;
use App\Services\Supplier\SupplierService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(\Database\Seeders\SettingSeeder::class);
    $this->seed(\Database\Seeders\RoleSeeder::class);
    $this->seed(\Database\Seeders\MenuSeeder::class);
    $this->seed(\Database\Seeders\RolePermissionSeeder::class);

    $this->admin = User::create([
        'name' => 'Admin',
        'username' => 'admin_bank_'.uniqid('', true),
        'password' => 'secret1234',
        'role_id' => Role::ofCode(Role::CODE_ADMIN)->value('id'),
        'is_active' => true,
        'force_password_change' => false,
    ]);

    $this->supplier = app(SupplierService::class)->create([
        'name' => 'PT Bank Test',
    ]);
});

test('admin bisa tambah rekening bank', function (): void {
    $this->actingAs($this->admin)
        ->post(route('suppliers.bank-accounts.store', $this->supplier->id), [
            'bank_name' => 'BCA',
            'bank_code' => '014',
            'account_number' => '1234567890',
            'account_holder' => 'PT Bank Test',
            'is_default' => true,
        ])
        ->assertRedirect();

    expect($this->supplier->bankAccounts()->count())->toBe(1);
    $bank = $this->supplier->bankAccounts()->first();
    expect($bank->is_default)->toBeTrue();
});

test('account_number wajib digit 5-30', function (): void {
    $this->actingAs($this->admin)
        ->post(route('suppliers.bank-accounts.store', $this->supplier->id), [
            'bank_name' => 'BCA',
            'account_number' => 'ABC',
            'account_holder' => 'Nama',
        ])
        ->assertSessionHasErrors('account_number');
});

test('set is_default=true auto unset bank lain di supplier yang sama', function (): void {
    // Buat 2 bank, satu default
    $this->actingAs($this->admin)->post(route('suppliers.bank-accounts.store', $this->supplier->id), [
        'bank_name' => 'BCA',
        'account_number' => '111111',
        'account_holder' => 'A',
        'is_default' => true,
    ]);

    $this->actingAs($this->admin)->post(route('suppliers.bank-accounts.store', $this->supplier->id), [
        'bank_name' => 'Mandiri',
        'account_number' => '222222',
        'account_holder' => 'A',
        'is_default' => true,
    ]);

    $banks = $this->supplier->bankAccounts()->orderBy('id')->get();
    expect($banks)->toHaveCount(2);
    expect($banks->where('is_default', true)->count())->toBe(1);
    expect($banks->last()->is_default)->toBeTrue();
    expect($banks->first()->fresh()->is_default)->toBeFalse();
});

test('endpoint set-default berhasil mengubah default', function (): void {
    $b1 = SupplierBankAccount::create([
        'supplier_id' => $this->supplier->id,
        'bank_name' => 'BCA',
        'account_number' => '111111',
        'account_holder' => 'A',
        'is_default' => true,
        'is_active' => true,
    ]);
    $b2 = SupplierBankAccount::create([
        'supplier_id' => $this->supplier->id,
        'bank_name' => 'Mandiri',
        'account_number' => '222222',
        'account_holder' => 'A',
        'is_default' => false,
        'is_active' => true,
    ]);

    $this->actingAs($this->admin)
        ->post(route('supplier-bank-accounts.set-default', $b2->id))
        ->assertRedirect();

    expect($b1->fresh()->is_default)->toBeFalse();
    expect($b2->fresh()->is_default)->toBeTrue();
});

test('hapus bank default → bank lain yang aktif otomatis jadi default', function (): void {
    $b1 = SupplierBankAccount::create([
        'supplier_id' => $this->supplier->id,
        'bank_name' => 'BCA',
        'account_number' => '111111',
        'account_holder' => 'A',
        'is_default' => true,
        'is_active' => true,
    ]);
    $b2 = SupplierBankAccount::create([
        'supplier_id' => $this->supplier->id,
        'bank_name' => 'Mandiri',
        'account_number' => '222222',
        'account_holder' => 'A',
        'is_default' => false,
        'is_active' => true,
    ]);

    $this->actingAs($this->admin)
        ->delete(route('supplier-bank-accounts.destroy', $b1->id))
        ->assertRedirect();

    // B1 trashed
    expect(SupplierBankAccount::withTrashed()->find($b1->id)->trashed())->toBeTrue();
    // B2 jadi default otomatis
    expect($b2->fresh()->is_default)->toBeTrue();
});
