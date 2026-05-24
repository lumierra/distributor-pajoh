<?php

namespace App\Models;

use App\Concerns\HasActivityLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class SalesVisit extends Model
{
    use HasActivityLog;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [self::STATUS_ACTIVE, self::STATUS_COMPLETED, self::STATUS_CANCELLED];

    public const AUTO_REASON_NEXT_VISIT = 'next_visit_started';

    public const AUTO_REASON_END_OF_DAY = 'end_of_day';

    protected $fillable = [
        'sales_id',
        'customer_id',
        'schedule_id',
        'device_id',
        'checked_in_at',
        'checkin_latitude',
        'checkin_longitude',
        'checkin_accuracy_meter',
        'checkin_distance_to_outlet',
        'checkin_photo_path',
        'checkin_notes',
        'is_mock_location',
        'bypass_geofence',
        'bypass_reason',
        'bypass_granted_by',
        'checked_out_at',
        'checkout_latitude',
        'checkout_longitude',
        'checkout_notes',
        'duration_minutes',
        'auto_checked_out',
        'auto_checkout_reason',
        'status',
        'so_count',
        'so_total_value',
        'return_count',
        'photo_count',
        'cancelled_at',
        'cancel_reason',
    ];

    protected function casts(): array
    {
        return [
            'checked_in_at' => 'datetime',
            'checked_out_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'checkin_latitude' => 'decimal:7',
            'checkin_longitude' => 'decimal:7',
            'checkout_latitude' => 'decimal:7',
            'checkout_longitude' => 'decimal:7',
            'checkin_accuracy_meter' => 'integer',
            'checkin_distance_to_outlet' => 'integer',
            'duration_minutes' => 'integer',
            'so_count' => 'integer',
            'so_total_value' => 'decimal:2',
            'return_count' => 'integer',
            'photo_count' => 'integer',
            'is_mock_location' => 'boolean',
            'bypass_geofence' => 'boolean',
            'auto_checked_out' => 'boolean',
        ];
    }

    public function sales(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sales_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(SalesSchedule::class, 'schedule_id');
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(UserDevice::class, 'device_id');
    }

    public function salesOrders(): HasMany
    {
        return $this->hasMany(SalesOrder::class, 'visit_id');
    }

    public function customerReturns(): HasMany
    {
        return $this->hasMany(CustomerReturn::class, 'visit_id');
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function duration(): int
    {
        if ($this->duration_minutes !== null) {
            return (int) $this->duration_minutes;
        }

        $end = $this->checked_out_at ?? now();

        return (int) Carbon::parse($this->checked_in_at)->diffInMinutes($end);
    }

    public function recomputeAggregates(): void
    {
        $soCount = SalesOrder::query()->where('visit_id', $this->id)->count();
        $soValue = SalesOrder::query()->where('visit_id', $this->id)->sum('total');
        $returnCount = CustomerReturn::query()->where('visit_id', $this->id)->count();

        $this->update([
            'so_count' => $soCount,
            'so_total_value' => (float) $soValue,
            'return_count' => $returnCount,
        ]);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeToday(Builder $query): Builder
    {
        return $query->whereDate('checked_in_at', today());
    }

    public function scopeForSales(Builder $query, int $salesId): Builder
    {
        return $query->where('sales_id', $salesId);
    }
}
