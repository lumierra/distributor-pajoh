<?php

namespace App\Models;

use App\Concerns\HasActivityLog;
use App\Concerns\HasAuditFields;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Vehicle extends Model
{
    use HasActivityLog, HasAuditFields, SoftDeletes;

    public const TYPES = ['truck', 'pickup', 'motor', 'box', 'lainnya'];

    public const STATUS_IDLE = 'idle';

    public const STATUS_ON_DELIVERY = 'on_delivery';

    public const STATUS_MAINTENANCE = 'maintenance';

    public const STATUSES = [self::STATUS_IDLE, self::STATUS_ON_DELIVERY, self::STATUS_MAINTENANCE];

    protected $fillable = [
        'code',
        'plate_number',
        'type',
        'brand',
        'model',
        'year',
        'color',
        'capacity_kg',
        'capacity_kubik',
        'last_service_date',
        'next_service_date',
        'odometer_km',
        'is_active',
        'status',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'capacity_kg' => 'float',
            'capacity_kubik' => 'float',
            'last_service_date' => 'date',
            'next_service_date' => 'date',
            'odometer_km' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function drivers(): HasMany
    {
        return $this->hasMany(Driver::class, 'default_vehicle_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeNotInMaintenance(Builder $query): Builder
    {
        return $query->where('status', '!=', self::STATUS_MAINTENANCE);
    }

    public function isAvailable(): bool
    {
        return $this->is_active && $this->status === self::STATUS_IDLE;
    }
}
