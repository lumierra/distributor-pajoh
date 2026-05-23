<?php

use App\Models\LoginHistory;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(\Database\Seeders\SettingSeeder::class);
    $this->seed(\Database\Seeders\RoleSeeder::class);
    $this->seed(\Database\Seeders\MenuSeeder::class);
    $this->seed(\Database\Seeders\RolePermissionSeeder::class);
});

function makeSales(array $overrides = []): User
{
    return User::create(array_merge([
        'name' => 'Sales A',
        'username' => 'sales01',
        'password' => 'sales1234',
        'role_id' => Role::ofCode(Role::CODE_SALES)->value('id'),
        'is_active' => true,
        'force_password_change' => false,
    ], $overrides));
}

test('mobile login returns token, user, and permissions', function (): void {
    makeSales();

    $response = $this->postJson('/api/v1/auth/login', [
        'username' => 'sales01',
        'password' => 'sales1234',
        'device_uuid' => 'uuid-abc-123',
        'device_name' => 'Pixel 8 / sales01',
    ]);

    $response->assertOk()
        ->assertJsonStructure(['token', 'expires_at', 'user' => ['id', 'name', 'username', 'role'], 'permissions']);

    expect($response->json('user.role'))->toBe('sales');
});

test('mobile login with wrong password returns 401 and logs the attempt', function (): void {
    makeSales();

    $this->postJson('/api/v1/auth/login', [
        'username' => 'sales01',
        'password' => 'incorrect',
        'device_uuid' => 'uuid-abc-123',
        'device_name' => 'Pixel 8',
    ])->assertStatus(401);

    expect(LoginHistory::query()->where('channel', LoginHistory::CHANNEL_MOBILE)->where('is_successful', false)->count())->toBe(1);
});

test('mobile login on inactive account returns 403', function (): void {
    makeSales(['is_active' => false]);

    $this->postJson('/api/v1/auth/login', [
        'username' => 'sales01',
        'password' => 'sales1234',
        'device_uuid' => 'uuid-abc-123',
        'device_name' => 'Pixel 8',
    ])->assertStatus(403);
});

test('mobile login requires device_uuid and device_name', function (): void {
    makeSales();

    $this->postJson('/api/v1/auth/login', [
        'username' => 'sales01',
        'password' => 'sales1234',
    ])->assertStatus(422);
});

test('GET /api/v1/auth/me returns the authenticated user', function (): void {
    $user = makeSales();

    Laravel\Sanctum\Sanctum::actingAs($user);

    $this->getJson('/api/v1/auth/me')
        ->assertOk()
        ->assertJsonPath('user.username', 'sales01');
});

test('POST /api/v1/auth/logout revokes the current token', function (): void {
    $user = makeSales();
    $token = $user->createToken('test-device')->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/auth/logout')
        ->assertOk();

    expect($user->tokens()->count())->toBe(0);
});
