<?php

namespace App\Models;

use App\Concerns\HasActivityLog;
use App\Concerns\HasAuditFields;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SupplierReturn extends Model
{
    use HasActivityLog, HasAuditFields, SoftDeletes;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_SENT = 'sent';

    public const STATUS_SETTLED = 'settled';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_APPROVED,
        self::STATUS_SENT,
        self::STATUS_SETTLED,
        self::STATUS_CANCELLED,
    ];

    public const REASON_CODES = ['damaged', 'expired', 'quality', 'wrong_item', 'other'];

    protected $fillable = [
        'return_number',
        'supplier_id',
        'supplier_snapshot',
        'return_date',
        'sent_date',
        'settled_date',
        'reason_code',
        'reason_notes',
        'status',
        'fiscal_year',
        'is_carry_over',
        'claim_amount',
        'settled_amount',
        'settlement_type',
        'settlement_notes',
        'approved_at',
        'approved_by',
        'sent_at',
        'sent_by',
        'settled_at',
        'settled_by',
        'cancelled_at',
        'cancelled_by',
        'cancel_reason',
        'notes',
        'proof_photo_path',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'supplier_snapshot' => 'array',
            'return_date' => 'date',
            'sent_date' => 'date',
            'settled_date' => 'date',
            'is_carry_over' => 'boolean',
            'claim_amount' => 'decimal:2',
            'settled_amount' => 'decimal:2',
            'approved_at' => 'datetime',
            'sent_at' => 'datetime',
            'settled_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SupplierReturnItem::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }

    public function settler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'settled_by');
    }

    public function canBeEdited(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function canBeApproved(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function canBeSent(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function canBeSettled(): bool
    {
        return $this->status === self::STATUS_SENT;
    }

    public function canBeCancelled(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_APPROVED], true);
    }

    public function recomputeTotals(): void
    {
        $this->loadMissing('items');
        $this->claim_amount = (float) $this->items->sum('line_value');
        $this->save();
    }

    public function scopeByStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }
}
