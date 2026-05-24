<?php

namespace App\Models;

use App\Concerns\HasActivityLog;
use App\Concerns\HasAuditFields;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class DeliveryOrder extends Model
{
    use HasActivityLog, HasAuditFields, SoftDeletes;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PICKING = 'picking';

    public const STATUS_PACKED = 'packed';

    public const STATUS_IN_TRANSIT = 'in_transit';

    public const STATUS_DELIVERED = 'delivered';

    public const STATUS_PARTIAL_RETURNED = 'partial_returned';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_PICKING,
        self::STATUS_PACKED,
        self::STATUS_IN_TRANSIT,
        self::STATUS_DELIVERED,
        self::STATUS_PARTIAL_RETURNED,
        self::STATUS_CANCELLED,
    ];

    protected $fillable = [
        'do_number',
        'sales_order_id',
        'customer_id',
        'delivery_address_snapshot',
        'do_date',
        'expected_delivery_date',
        'status',
        'fiscal_year',
        'is_carry_over',
        'driver_id',
        'vehicle_id',
        'driver_snapshot',
        'vehicle_snapshot',
        'picking_started_at',
        'picking_started_by',
        'packed_at',
        'packed_by',
        'in_transit_at',
        'in_transit_by',
        'delivered_at',
        'delivered_by',
        'cancelled_at',
        'cancelled_by',
        'cancel_reason',
        'receiver_name',
        'receiver_notes',
        'proof_photo_signed_path',
        'proof_photo_goods_path',
        'digital_signature_path',
        'delivery_latitude',
        'delivery_longitude',
        'has_partial_return',
        'partial_return_notes',
        'customer_return_id',
        'pdf_path',
        'pdf_generated_at',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'delivery_address_snapshot' => 'array',
            'driver_snapshot' => 'array',
            'vehicle_snapshot' => 'array',
            'do_date' => 'date',
            'expected_delivery_date' => 'date',
            'fiscal_year' => 'integer',
            'is_carry_over' => 'boolean',
            'picking_started_at' => 'datetime',
            'packed_at' => 'datetime',
            'in_transit_at' => 'datetime',
            'delivered_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'pdf_generated_at' => 'datetime',
            'delivery_latitude' => 'float',
            'delivery_longitude' => 'float',
            'has_partial_return' => 'boolean',
        ];
    }

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(DoItem::class)->orderBy('sort_order');
    }

    public function pickingStarter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'picking_started_by');
    }

    public function packer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'packed_by');
    }

    public function inTransitStarter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'in_transit_by');
    }

    public function deliverer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delivered_by');
    }

    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function canBeEdited(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function canStartPicking(): bool
    {
        return $this->status === self::STATUS_DRAFT && $this->items()->exists();
    }

    public function canMarkPacked(): bool
    {
        return $this->status === self::STATUS_PICKING;
    }

    public function canStartDelivery(): bool
    {
        return $this->status === self::STATUS_PACKED;
    }

    public function canMarkDelivered(): bool
    {
        return $this->status === self::STATUS_IN_TRANSIT;
    }

    public function canBeCancelled(): bool
    {
        return in_array($this->status, [
            self::STATUS_DRAFT,
            self::STATUS_PICKING,
            self::STATUS_PACKED,
        ], true);
    }

    public function scopeByStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }
}
