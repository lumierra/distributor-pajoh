<?php

use App\Models\Role;
use App\Models\User;
use App\Rules\UniqueUsername;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(\Database\Seeders\SettingSeeder::class);
    $this->seed(\Database\Seeders\RoleSeeder::class);

    $this->existingUser = User::create([
        'name' => 'Existing',
        'username' => 'existing',
        'password' => 'secret1234',
        'role_id' => Role::ofCode(Role::CODE_ADMIN)->value('id'),
        'is_active' => true,
        'force_password_change' => false,
    ]);
});

function runUniqueUsername(string $candidate, ?int $ignoreId = null): array
{
    $validator = Validator::make(
        ['username' => $candidate],
        ['username' => [new UniqueUsername($ignoreId)]],
    );

    return $validator->errors()->get('username');
}

test('rejects an existing username (exact match)', function (): void {
    expect(runUniqueUsername('existing'))->not->toBe([]);
});

test('rejects existing username case-insensitively', function (): void {
    expect(runUniqueUsername('ExIsTiNg'))->not->toBe([]);
});

test('accepts a brand-new username', function (): void {
    expect(runUniqueUsername('brand_new'))->toBe([]);
});

test('ignoreUserId allows the same row to keep its username', function (): void {
    expect(runUniqueUsername('existing', $this->existingUser->id))->toBe([]);
});
