<?php

namespace App\Concerns;

trait HasAuditFields
{
    public static function bootHasAuditFields(): void
    {
        static::creating(function ($model): void {
            if (auth()->check()) {
                if (in_array('created_by', $model->getFillable(), true) || $model->isFillable('created_by')) {
                    $model->created_by ??= auth()->id();
                }
                if (in_array('updated_by', $model->getFillable(), true) || $model->isFillable('updated_by')) {
                    $model->updated_by ??= auth()->id();
                }
            }
        });

        static::updating(function ($model): void {
            if (auth()->check() && ($model->isFillable('updated_by') || in_array('updated_by', $model->getFillable(), true))) {
                $model->updated_by = auth()->id();
            }
        });
    }
}
