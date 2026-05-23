<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class VehicleDocument extends Model
{
    use SoftDeletes;

    public const TYPES = [
        'STNK', 'BPKB', 'KIR', 'ASURANSI', 'PAJAK_TAHUNAN', 'IZIN_TRAYEK', 'LAINNYA',
    ];

    public const CRITICAL_TYPES = ['STNK', 'KIR'];

    protected $fillable = [
        'vehicle_id',
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

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
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
