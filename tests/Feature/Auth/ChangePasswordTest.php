<?php

use App\Models\PasswordHistory;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(\Database\Seeders\SettingSeeder::class);
    $this->seed(\Database\Seeders\RoleSeeder::class);
});

function makeAdminUser(array $overrides = []): User
{
    return User::create(array_merge([
        'name' => 'Test Admin',
        'username' => 'admin1',
        'password' => 'old-pass1',
        'role_id' => Role::ofCode(Role::CODE_ADMIN)->value('id'),
        'is_active' => true,
        'force_password_change' => false,
    ], $overrides));
}

test('user can change their password with valid current + new password', function (): void {
    $user = makeAdminUser();

    $this->actingAs($user)
        ->post(route('password.change.store'), [
            'current_password' => 'old-pass1',
            'new_password' => 'new-pass99',
            'new_password_confirmation' => 'new-pass99',
        ])->assertRedirect(route('dashboard'));

    $user->refresh();
    expect(Hash::check('new-pass99', $user->password))->toBeTrue();
    expect($user->force_password_change)->toBeFalse();
    expect(PasswordHistory::where('user_id', $user->id)->count())->toBe(1);
});

test('change-password rejects wrong current password', function (): void {
    $user = makeAdminUser();

    $this->actingAs($user)
        ->post(route('password.change.store'), [
            'current_password' => 'wrong-current',
            'new_password' => 'new-pass99',
            'new_password_confirmation' => 'new-pass99',
        ])->assertSessionHasErrors('current_password');
});

test('change-password enforces minimum length', function (): void {
    $user = makeAdminUser();

    $this->actingAs($user)
        ->post(route('password.change.store'), [
            'current_password' => 'old-pass1',
            'new_password' => 'abc1',
            'new_password_confirmation' => 'abc1',
        ])->assertSessionHasErrors('new_password');
});

test('change-password rejects a new password that matches username', function (): void {
    $user = makeAdminUser(['username' => 'lumierra1', 'password' => 'old-pass1']);

    $this->actingAs($user)
        ->post(route('password.change.store'), [
            'current_password' => 'old-pass1',
            'new_password' => 'Lumierra1',
            'new_password_confirmation' => 'Lumierra1',
        ])->assertSessionHasErrors('new_password');
});

test('force_password_change blocks access to dashboard until changed', function (): void {
    $user = makeAdminUser(['force_password_change' => true]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect(route('password.change.show'));
});

test('change-password rejects re-use of one of the last 3 passwords', function (): void {
    $user = makeAdminUser();

    // Establish history with a known previous hash.
    PasswordHistory::create([
        'user_id' => $user->id,
        'password_hash' => Hash::make('history-pass1'),
    ]);

    $this->actingAs($user)
        ->post(route('password.change.store'), [
            'current_password' => 'old-pass1',
            'new_password' => 'history-pass1',
            'new_password_confirmation' => 'history-pass1',
        ])->assertSessionHasErrors('new_password');
});
