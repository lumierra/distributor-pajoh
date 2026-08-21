<?php

namespace App\Models;

use App\Concerns\HasActivityLog;
use App\Concerns\HasAuditFields;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseOrder extends Model
{
    use HasActivityLog, HasAuditFields, SoftDeletes;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_PARTIAL_RECEIVED = 'partial_received';

    public const STATUS_CLOSED = 'closed';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_APPROVED,
        self::STATUS_PARTIAL_RECEIVED,
        self::STATUS_CLOSED,
        self::STATUS_CANCELLED,
    ];

    public const DISCOUNT_TYPES = ['rp', 'percent'];

    protected $fillable = [
        'po_number',
        'supplier_id',
        'supplier_snapshot',
        'po_date',
        'eta_date',
        'payment_term_days',
        'status',
        'fiscal_year',
        'is_carry_over',
        'subtotal',
        'header_discount_type',
        'header_discount_value',
        'header_discount_amount',
        'total',
        'notes',
        'approved_by',
        'approved_at',
        'closed_by',
        'closed_at',
        'close_reason',
        'cancelled_by',
        'cancelled_at',
        'cancel_reason',
        'pdf_path',
        'pdf_generated_at',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'supplier_snapshot' => 'array',
            'po_date' => 'date',
            'eta_date' => 'date',
            'payment_term_days' => 'integer',
            'fiscal_year' => 'integer',
            'is_carry_over' => 'boolean',
            'subtotal' => 'decimal:2',
            'header_discount_value' => 'decimal:2',
            'header_discount_amount' => 'decimal:2',
            'total' => 'decimal:2',
            'approved_at' => 'datetime',
            'closed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'pdf_generated_at' => 'datetime',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PoItem::class)->orderBy('sort_order');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function scopeByStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    public function scopeCurrentYearOrCarryOver(Builder $query, int $year): Builder
    {
        return $query->where(function ($q) use ($year): void {
            $q->where('fiscal_year', $year)
                ->orWhere('is_carry_over', true);
        });
    }

    public function canBeEdited(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function canBeApproved(): bool
    {
        return $this->status === self::STATUS_DRAFT && $this->items()->exists();
    }

    public function canBeCancelled(): bool
    {
        // Boleh cancel draft atau approved selama belum ada GRN posted (validated
        // ulang di service). Status partial_received TIDAK disertakan: status itu
        // hanya bisa terjadi setelah ada qty_received > 0, yang service selalu
        // tolak untuk cancel — jadi tombol Cancel di UI tidak boleh muncul lagi.
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_APPROVED], true);
    }

    public function canBeClosed(): bool
    {
        // Manual close hanya untuk partial_received atau approved (short-shipped).
        return in_array($this->status, [self::STATUS_APPROVED, self::STATUS_PARTIAL_RECEIVED], true);
    }
}
