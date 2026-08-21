<?php

namespace App\Models;

use App\Concerns\HasActivityLog;
use App\Concerns\HasAuditFields;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class StockAdjustment extends Model
{
    use HasActivityLog, HasAuditFields, SoftDeletes;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_POSTED = 'posted';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_POSTED,
        self::STATUS_CANCELLED,
    ];

    /** Kategori alasan penyesuaian. */
    public const REASON_DAMAGED = 'damaged';

    public const REASON_LOST = 'lost';

    public const REASON_SHRINKAGE = 'shrinkage';

    public const REASON_MISCOUNT = 'miscount';

    public const REASON_FOUND = 'found';

    public const REASON_OTHER = 'other';

    public const REASON_CATEGORIES = [
        self::REASON_DAMAGED,
        self::REASON_LOST,
        self::REASON_SHRINKAGE,
        self::REASON_MISCOUNT,
        self::REASON_FOUND,
        self::REASON_OTHER,
    ];

    protected $fillable = [
        'adjustment_number',
        'adjustment_date',
        'reason_category',
        'notes',
        'status',
        'fiscal_year',
        'posted_by',
        'posted_at',
        'cancelled_by',
        'cancelled_at',
        'cancel_reason',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'adjustment_date' => 'date',
            'fiscal_year' => 'integer',
            'posted_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockAdjustmentItem::class)->orderBy('sort_order');
    }

    public function poster(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function canBeEdited(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function canBePosted(): bool
    {
        return $this->status === self::STATUS_DRAFT && $this->items()->exists();
    }

    public function canBeCancelled(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }
}
