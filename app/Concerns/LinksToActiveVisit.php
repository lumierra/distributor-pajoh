<?php

namespace App\Concerns;

use App\Services\Sales\SalesVisitService;
use Illuminate\Database\Eloquent\Model;

/**
 * Auto-set `visit_id` to the active visit owned by the currently
 * authenticated sales user, kalau visit_id belum di-set.
 *
 * Models yang pakai trait ini: SalesOrder, CustomerReturn, CustomerPhoto.
 */
trait LinksToActiveVisit
{
    public static function bootLinksToActiveVisit(): void
    {
        static::creating(function (Model $model): void {
            if (! isset($model->visit_id) || $model->visit_id !== null) {
                if ($model->getAttribute('visit_id') !== null) {
                    return;
                }
            }

            $user = auth()->user();
            if ($user === null) {
                return;
            }
            if (! method_exists($user, 'hasRole') || ! $user->hasRole('sales')) {
                return;
            }

            $active = app(SalesVisitService::class)->getActiveVisit($user);
            if ($active !== null) {
                $model->visit_id = $active->id;
            }
        });
    }
}
