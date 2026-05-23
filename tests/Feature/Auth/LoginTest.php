<?php

use App\Models\LoginHistory;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(\Database\Seeders\SettingSeeder::class);
    $this->seed(\Database\Seeders\RoleSeeder::class);
});

function makeUser(array $overrides = []): User
{
    return User::create(array_merge([
        'name' => 'Test User',
        'username' => 'tester',
        'password' => 'pass1234',
        'role_id' => Role::ofCode(Role::CODE_ADMIN)->value('id'),
        'is_active' => true,
        'force_password_change' => false,
    ], $overrides));
}

test('GET /login renders the Inertia login page', function (): void {
    $this->get(route('login'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Auth/Login'));
});

test('valid credentials authenticate and redirect to dashboard', function (): void {
    makeUser(['username' => 'admin1']);

    $this->post(route('login'), [
        'username' => 'admin1',
        'password' => 'pass1234',
    ])->assertRedirect(route('dashboard'));

    expect(auth()->check())->toBeTrue();
    expect(auth()->user()->username)->toBe('admin1');
});

test('invalid credentials throw a validation error and log the attempt', function (): void {
    makeUser(['username' => 'admin1']);

    $this->post(route('login'), [
        'username' => 'admin1',
        'password' => 'wrong-password',
    ])->assertSessionHasErrors('username');

    expect(auth()->check())->toBeFalse();
    expect(LoginHistory::query()->where('is_successful', false)->count())->toBe(1);
});

test('inactive accounts cannot log in', function (): void {
    makeUser(['username' => 'inactive', 'is_active' => false]);

    $this->post(route('login'), [
        'username' => 'inactive',
        'password' => 'pass1234',
    ])->assertSessionHasErrors('username');

    expect(auth()->check())->toBeFalse();
    expect(LoginHistory::query()->where('failure_reason', LoginHistory::FAIL_ACCOUNT_INACTIVE)->count())->toBe(1);
});

test('username input is normalized to lowercase', function (): void {
    makeUser(['username' => 'admin1']);

    $this->post(route('login'), [
        'username' => 'Admin1',
        'password' => 'pass1234',
    ])->assertRedirect(route('dashboard'));
});

test('force_password_change redirects to the change-password screen', function (): void {
    makeUser(['username' => 'newbie', 'force_password_change' => true]);

    $this->post(route('login'), [
        'username' => 'newbie',
        'password' => 'pass1234',
    ])->assertRedirect(route('password.change.show'));
});

test('authenticated users are redirected away from /login', function (): void {
    $user = makeUser();

    $this->actingAs($user)
        ->get(route('login'))
        ->assertRedirect();
});

test('logout invalidates the session', function (): void {
    $user = makeUser();
    $this->actingAs($user);

    $this->post(route('logout'))->assertRedirect(route('login'));
    expect(auth()->check())->toBeFalse();
});

test('successful login records last_login_at and ip', function (): void {
    $user = makeUser(['username' => 'admin1']);

    $this->post(route('login'), [
        'username' => 'admin1',
        'password' => 'pass1234',
    ]);

    $user->refresh();
    expect($user->last_login_at)->not->toBeNull();
    expect($user->last_login_ip)->not->toBeNull();
});
