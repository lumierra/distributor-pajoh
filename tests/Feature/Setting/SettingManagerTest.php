<?php

use App\Models\Setting;
use App\Services\Setting\SettingManager;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(\Database\Seeders\SettingSeeder::class);
    $this->manager = app(SettingManager::class);
});

test('reads a setting by group.key', function (): void {
    expect($this->manager->get('company.name'))->toBe('CV Ananda Berkah Sejahtera - LGS');
    expect($this->manager->get('system.timezone'))->toBe('Asia/Jakarta');
});

test('returns default when key not found', function (): void {
    expect($this->manager->get('non.existent', 'fallback'))->toBe('fallback');
});

test('casts bool, int, and string types correctly', function (): void {
    expect($this->manager->get('sales.geofence.enabled'))->toBeTrue();
    expect($this->manager->get('sales.geofence.radius_meter'))->toBe(100);
    expect($this->manager->get('system.locale'))->toBe('id_ID');
});

test('sets a setting and invalidates cache', function (): void {
    $this->manager->set('company.name', 'CV Pajoh Distributor');

    expect($this->manager->get('company.name'))->toBe('CV Pajoh Distributor');
    expect(Setting::ofGroup('company')->where('key', 'name')->first()->value)
        ->toBe('CV Pajoh Distributor');
});

test('helper function setting() works', function (): void {
    expect(setting('company.name'))->toBe('CV Ananda Berkah Sejahtera - LGS');
    expect(setting('non.existent', 'fallback'))->toBe('fallback');
    expect(setting())->toBeInstanceOf(SettingManager::class);
});

test('all() returns flat keyed array, filterable by group', function (): void {
    $all = $this->manager->all();
    expect($all)->toBeArray()->toHaveKey('company.name');

    $companyOnly = $this->manager->all('company');
    expect($companyOnly)->toHaveKey('company.name');
    expect($companyOnly)->not->toHaveKey('system.timezone');
});

test('isSensitive detects flagged keys', function (): void {
    expect($this->manager->isSensitive('notification.wa.gateway_token'))->toBeTrue();
    expect($this->manager->isSensitive('company.name'))->toBeFalse();
});

test('reset restores default_value', function (): void {
    $this->manager->set('company.name', 'New CV');
    expect(setting('company.name'))->toBe('New CV');

    $this->manager->reset('company.name');
    expect(setting('company.name'))->toBe('CV Ananda Berkah Sejahtera - LGS');
});

test('observer invalidates cache on save', function (): void {
    setting('company.name'); // warm cache

    Setting::ofGroup('company')->where('key', 'name')->first()->update([
        'value' => 'Updated Name',
    ]);

    expect(setting('company.name'))->toBe('Updated Name');
});
