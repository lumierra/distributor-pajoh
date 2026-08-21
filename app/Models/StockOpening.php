<?php

namespace App\Models;

use App\Concerns\HasActivityLog;
use App\Concerns\HasAuditFields;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Stok Awal (opening balance) — saldo awal stok saat aplikasi mulai dipakai.
 * Alur draft → posted. Saat posting, tiap item membuat batch & menulis
 * stock_ledger bertipe opening_in.
 */
class StockOpening extends Model
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

    protected $fillable = [
        'opening_number',
        'opening_date',
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
            'opening_date' => 'date',
            'fiscal_year' => 'integer',
            'posted_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockOpeningItem::class)->orderBy('sort_order');
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
