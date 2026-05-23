<?php

use App\Models\ActivityLog;
use App\Models\Role;
use App\Models\User;
use App\Services\Audit\ActivityLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(\Database\Seeders\SettingSeeder::class);
    $this->seed(\Database\Seeders\RoleSeeder::class);
});

function activityLoggerActor(): User
{
    return User::create([
        'name' => 'Actor',
        'username' => 'actor_'.uniqid('', true),
        'password' => 'secret1234',
        'role_id' => Role::ofCode(Role::CODE_ADMIN)->value('id'),
        'is_active' => true,
        'force_password_change' => false,
    ]);
}

test('logModel records before/after diff and resolves a label', function (): void {
    $actor = activityLoggerActor();
    $this->actingAs($actor);

    $target = activityLoggerActor();

    $logger = app(ActivityLogger::class);
    $log = $logger->logModel(
        'updated',
        $target,
        before: ['name' => 'old'],
        after: ['name' => 'new'],
    );

    expect($log->action)->toBe('updated');
    expect($log->model_type)->toBe(User::class);
    expect($log->model_id)->toBe($target->id);
    expect($log->model_label)->toBe($target->name);
    expect($log->before)->toBe(['name' => 'old']);
    expect($log->after)->toBe(['name' => 'new']);
    expect($log->user_id)->toBe($actor->id);
    expect($log->user_name_snapshot)->toBe($actor->name);
});

test('logAction records non-model events with context', function (): void {
    $actor = activityLoggerActor();
    $this->actingAs($actor);

    $logger = app(ActivityLogger::class);
    $log = $logger->logAction('settings.updated', ['key' => 'company.name']);

    expect($log->action)->toBe('settings.updated');
    expect($log->model_type)->toBeNull();
    expect($log->context)->toBe(['key' => 'company.name']);
    expect(ActivityLog::query()->where('action', 'settings.updated')->count())->toBe(1);
});

test('channel falls back to system when no user is authenticated', function (): void {
    $target = activityLoggerActor();

    $log = app(ActivityLogger::class)->logModel('created', $target);

    expect($log->channel)->toBe(ActivityLog::CHANNEL_SYSTEM);
});
