<?php

namespace App\Models;

use App\Concerns\HasActivityLog;
use App\Concerns\HasAuditFields;
use App\Concerns\LinksToActiveVisit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SalesOrder extends Model
{
    use HasActivityLog, HasAuditFields, LinksToActiveVisit, SoftDeletes;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_PENDING_CREDIT_REVIEW = 'pending_credit_review';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_PARTIALLY_DELIVERED = 'partially_delivered';

    public const STATUS_DELIVERED = 'delivered';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_SUBMITTED,
        self::STATUS_PENDING_CREDIT_REVIEW,
        self::STATUS_APPROVED,
        self::STATUS_REJECTED,
        self::STATUS_PARTIALLY_DELIVERED,
        self::STATUS_DELIVERED,
        self::STATUS_CANCELLED,
    ];

    public const DISCOUNT_TYPES = ['rp', 'percent'];

    protected $fillable = [
        'so_number',
        'customer_id',
        'customer_snapshot',
        'sales_id',
        'visit_id',
        'so_date',
        'eta_date',
        'payment_term_days',
        'due_date',
        'status',
        'fiscal_year',
        'is_carry_over',
        'subtotal',
        'header_discount_type',
        'header_discount_value',
        'header_discount_amount',
        'total',
        'notes',
        'submitted_at',
        'submitted_by',
        'approved_at',
        'approved_by',
        'rejected_at',
        'rejected_by',
        'rejection_reason',
        'cancelled_at',
        'cancelled_by',
        'cancel_reason',
        'credit_review_required',
        'credit_outstanding_snapshot',
        'credit_override_approved_by',
        'credit_override_approved_at',
        'credit_override_reason',
        'pdf_path',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'customer_snapshot' => 'array',
            'so_date' => 'date',
            'eta_date' => 'date',
            'due_date' => 'date',
            'payment_term_days' => 'integer',
            'fiscal_year' => 'integer',
            'is_carry_over' => 'boolean',
            'subtotal' => 'decimal:2',
            'header_discount_value' => 'decimal:2',
            'header_discount_amount' => 'decimal:2',
            'total' => 'decimal:2',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'credit_review_required' => 'boolean',
            'credit_outstanding_snapshot' => 'decimal:2',
            'credit_override_approved_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function sales(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sales_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(SoItem::class)->orderBy('sort_order');
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(SoReservation::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function rejecter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function creditOverrideApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'credit_override_approved_by');
    }

    public function canBeEdited(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function canBeSubmitted(): bool
    {
        return $this->status === self::STATUS_DRAFT && $this->items()->exists();
    }

    public function canBeApproved(): bool
    {
        return in_array($this->status, [
            self::STATUS_SUBMITTED,
            self::STATUS_PENDING_CREDIT_REVIEW,
        ], true);
    }

    public function canBeRejected(): bool
    {
        return $this->canBeApproved();
    }

    public function canBeCancelled(): bool
    {
        // Boleh cancel sebelum DO posted (partially_delivered/delivered hard-block).
        return in_array($this->status, [
            self::STATUS_DRAFT,
            self::STATUS_SUBMITTED,
            self::STATUS_PENDING_CREDIT_REVIEW,
            self::STATUS_APPROVED,
        ], true);
    }

    public function scopeByStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    public function scopeBySales(Builder $query, int $userId): Builder
    {
        return $query->where('sales_id', $userId);
    }

    public function scopeByCustomer(Builder $query, int $customerId): Builder
    {
        return $query->where('customer_id', $customerId);
    }

    public function scopePendingApproval(Builder $query): Builder
    {
        return $query->whereIn('status', [
            self::STATUS_SUBMITTED,
            self::STATUS_PENDING_CREDIT_REVIEW,
        ]);
    }
}
