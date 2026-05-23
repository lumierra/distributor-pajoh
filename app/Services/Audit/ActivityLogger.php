<?php

namespace App\Services\Audit;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Central writer for activity_logs.
 *
 * Per roadmap tip #1, this service is set up at the end of Fase 1 so that
 * each module added in Fase 2+ can wire its observer/listener immediately.
 * The viewer UI (per Topic 19) is finalised in Fase 6.
 */
class ActivityLogger
{
    public function __construct(private readonly Request $request) {}

    /**
     * Record a model action (created/updated/deleted/restored/custom).
     *
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     * @param  array<string, mixed>|null  $context
     */
    public function logModel(
        string $action,
        Model $model,
        ?array $before = null,
        ?array $after = null,
        ?array $context = null,
        ?User $actor = null,
    ): ActivityLog {
        $actor ??= $this->resolveActor();

        return ActivityLog::create([
            'user_id' => $actor?->id,
            'user_name_snapshot' => $actor?->name,
            'action' => $action,
            'model_type' => $model::class,
            'model_id' => $model->getKey(),
            'model_label' => $this->labelFor($model),
            'before' => $before,
            'after' => $after,
            'context' => $context,
            'ip' => $this->request->ip(),
            'user_agent' => substr((string) $this->request->userAgent(), 0, 512),
            'channel' => $this->channel(),
            'created_at' => now(),
        ]);
    }

    /**
     * Record a non-model action (e.g. login, logout, settings change).
     *
     * @param  array<string, mixed>|null  $context
     */
    public function logAction(string $action, ?array $context = null, ?User $actor = null): ActivityLog
    {
        $actor ??= $this->resolveActor();

        return ActivityLog::create([
            'user_id' => $actor?->id,
            'user_name_snapshot' => $actor?->name,
            'action' => $action,
            'context' => $context,
            'ip' => $this->request->ip(),
            'user_agent' => substr((string) $this->request->userAgent(), 0, 512),
            'channel' => $this->channel(),
            'created_at' => now(),
        ]);
    }

    /**
     * Best-effort human label for an activity row.
     */
    private function labelFor(Model $model): ?string
    {
        foreach (['code', 'number', 'name', 'username', 'label', 'title'] as $attr) {
            $value = $model->{$attr} ?? null;
            if (is_string($value) && $value !== '') {
                return mb_substr($value, 0, 255);
            }
        }

        return null;
    }

    /**
     * Prefer the authenticated session/guard user — `$request->user()` is
     * `null` until the Authenticate middleware runs, which doesn't happen in
     * unit/feature tests that use `actingAs()` without an actual HTTP cycle.
     */
    private function resolveActor(): ?User
    {
        return $this->request->user() ?? auth()->user();
    }

    private function channel(): string
    {
        if ($this->request->expectsJson() || $this->request->is('api/*')) {
            return ActivityLog::CHANNEL_MOBILE;
        }

        if ($this->request->user() !== null) {
            return ActivityLog::CHANNEL_WEB;
        }

        return ActivityLog::CHANNEL_SYSTEM;
    }
}
