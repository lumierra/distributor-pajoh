<?php

namespace App\Models;

use App\Concerns\HasActivityLog;
use App\Concerns\HasAuditFields;
use App\Concerns\LinksToActiveVisit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class CustomerReturn extends Model
{
    use HasActivityLog, HasAuditFields, LinksToActiveVisit, SoftDeletes;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_SORTED = 'sorted';

    public const STATUS_POSTED = 'posted';

    public const STATUS_CREDITED = 'credited';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_SORTED,
        self::STATUS_POSTED,
        self::STATUS_CREDITED,
        self::STATUS_CANCELLED,
    ];

    public const REASON_CODES = [
        'expired',
        'damaged',
        'wrong_item',
        'customer_request',
        'quality_issue',
        'other',
    ];

    protected $fillable = [
        'return_number',
        'customer_id',
        'customer_snapshot',
        'sales_id',
        'visit_id',
        'invoice_id',
        'delivery_order_id',
        'return_date',
        'brand_tag',
        'reason_code',
        'reason_notes',
        'status',
        'fiscal_year',
        'is_carry_over',
        'total_value',
        'total_qty_good_base',
        'total_qty_bs_base',
        'credit_note_id',
        'sorted_at',
        'sorted_by',
        'posted_at',
        'posted_by',
        'cancelled_at',
        'cancelled_by',
        'cancel_reason',
        'proof_photo_path',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'customer_snapshot' => 'array',
            'return_date' => 'date',
            'is_carry_over' => 'boolean',
            'total_value' => 'decimal:2',
            'total_qty_good_base' => 'integer',
            'total_qty_bs_base' => 'integer',
            'sorted_at' => 'datetime',
            'posted_at' => 'datetime',
            'cancelled_at' => 'datetime',
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

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function deliveryOrder(): BelongsTo
    {
        return $this->belongsTo(DeliveryOrder::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(CustomerReturnItem::class);
    }

    public function creditNote(): HasOne
    {
        return $this->hasOne(CreditNote::class);
    }

    public function sorter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sorted_by');
    }

    public function poster(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    public function canBeEdited(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function canBeSorted(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_SORTED], true);
    }

    public function canBePosted(): bool
    {
        return $this->status === self::STATUS_SORTED;
    }

    public function canBeCancelled(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_SORTED], true);
    }

    public function recomputeTotals(): void
    {
        $this->loadMissing('items');
        $this->total_value = (float) $this->items->sum('line_value');
        $this->total_qty_good_base = (int) $this->items->sum('qty_good_base');
        $this->total_qty_bs_base = (int) $this->items->sum('qty_bs_base');
        $this->save();
    }

    public function scopeByStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }
}
