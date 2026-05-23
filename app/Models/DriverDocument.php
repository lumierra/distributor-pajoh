<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class DriverDocument extends Model
{
    use SoftDeletes;

    public const TYPES = [
        'SIM_A', 'SIM_B1', 'SIM_B1_UMUM', 'SIM_B2', 'SIM_B2_UMUM', 'SIM_C',
        'KTP', 'KK', 'KONTRAK_KERJA', 'LAINNYA',
    ];

    protected $fillable = [
        'driver_id',
        'type',
        'title',
        'file_path',
        'file_size',
        'file_mime',
        'issued_date',
        'expires_date',
        'notes',
        'uploaded_by',
    ];

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
            'issued_date' => 'date',
            'expires_date' => 'date',
        ];
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function scopeExpiringSoon(Builder $query, int $days = 30): Builder
    {
        return $query->whereNotNull('expires_date')
            ->whereDate('expires_date', '>=', now())
            ->whereDate('expires_date', '<=', now()->addDays($days));
    }

    public function scopeExpired(Builder $query): Builder
    {
        return $query->whereNotNull('expires_date')
            ->whereDate('expires_date', '<', now());
    }
}
