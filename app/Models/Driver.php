<?php

namespace App\Models;

use App\Concerns\HasActivityLog;
use App\Concerns\HasAuditFields;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Driver extends Model
{
    use HasActivityLog, HasAuditFields, SoftDeletes;

    public const LICENSE_TYPES = ['A', 'B1', 'B1_UMUM', 'B2', 'B2_UMUM', 'C'];

    public const STATUS_IDLE = 'idle';

    public const STATUS_ON_DELIVERY = 'on_delivery';

    public const STATUS_UNAVAILABLE = 'unavailable';

    public const STATUSES = [self::STATUS_IDLE, self::STATUS_ON_DELIVERY, self::STATUS_UNAVAILABLE];

    protected $fillable = [
        'code',
        'name',
        'nik',
        'phone',
        'whatsapp',
        'address',
        'city',
        'license_no',
        'license_type',
        'license_expired_date',
        'emergency_contact_name',
        'emergency_contact_phone',
        'hire_date',
        'default_vehicle_id',
        'is_active',
        'status',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'license_expired_date' => 'date',
            'hire_date' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function defaultVehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class, 'default_vehicle_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(DriverDocument::class)->latest();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('is_active', true)->where('status', self::STATUS_IDLE);
    }

    public function isAvailable(): bool
    {
        return $this->is_active && $this->status === self::STATUS_IDLE;
    }

    public function hasValidLicense(): bool
    {
        return $this->license_expired_date !== null
            && $this->license_expired_date->isFuture();
    }
}
