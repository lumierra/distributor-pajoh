<?php

namespace App\Models;

use App\Concerns\HasActivityLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesVisitBypassRequest extends Model
{
    use HasActivityLog;

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_EXPIRED = 'expired';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_APPROVED,
        self::STATUS_REJECTED,
        self::STATUS_EXPIRED,
    ];

    public $timestamps = false;

    protected $fillable = [
        'sales_id',
        'customer_id',
        'reason',
        'requested_lat',
        'requested_lng',
        'distance_meter',
        'requested_at',
        'status',
        'reviewed_at',
        'reviewed_by',
        'expires_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'requested_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'expires_at' => 'datetime',
            'requested_lat' => 'decimal:7',
            'requested_lng' => 'decimal:7',
            'distance_meter' => 'integer',
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

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function isApprovedAndNotExpired(): bool
    {
        return $this->status === self::STATUS_APPROVED
            && $this->expires_at !== null
            && $this->expires_at->isFuture();
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeApprovedNotExpired(Builder $query): Builder
    {
        return $query
            ->where('status', self::STATUS_APPROVED)
            ->where('expires_at', '>', now());
    }
}
