<?php

namespace App\Concerns;

use App\Services\Audit\ActivityLogger;
use Illuminate\Database\Eloquent\Model;

/**
 * Drop this on a model to auto-log create/update/delete/restore actions.
 *
 *   use App\Concerns\HasActivityLog;
 *   class Supplier extends Model { use HasActivityLog; }
 *
 * The trait wires Eloquent events; payloads use the model's `getChanges()` /
 * `getOriginal()` so unchanged columns aren't recorded.
 *
 * If a model needs to exclude sensitive columns from the diff, define:
 *   protected array $activityLogExclude = ['password', 'remember_token'];
 */
trait HasActivityLog
{
    public static function bootHasActivityLog(): void
    {
        static::created(fn (Model $model) => self::recordActivity($model, 'created', null, self::diffPayload($model->getAttributes(), $model)));
        static::updated(fn (Model $model) => self::recordActivity(
            $model,
            'updated',
            self::diffPayload($model->getOriginal(), $model),
            self::diffPayload($model->getChanges(), $model),
        ));
        static::deleted(fn (Model $model) => self::recordActivity($model, $model->isForceDeleting() ? 'force_deleted' : 'deleted', self::diffPayload($model->getOriginal(), $model)));
        if (method_exists(static::class, 'restored')) {
            static::restored(fn (Model $model) => self::recordActivity($model, 'restored'));
        }
    }

    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    private static function recordActivity(Model $model, string $action, ?array $before = null, ?array $after = null): void
    {
        app(ActivityLogger::class)->logModel($action, $model, $before, $after);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private static function diffPayload(array $payload, Model $model): array
    {
        $exclude = property_exists($model, 'activityLogExclude')
            ? (array) $model->activityLogExclude
            : ['password', 'remember_token'];

        return collect($payload)
            ->except($exclude)
            ->all();
    }
}
