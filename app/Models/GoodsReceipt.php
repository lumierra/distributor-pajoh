<?php

namespace App\Models;

use App\Concerns\HasActivityLog;
use App\Concerns\HasAuditFields;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class GoodsReceipt extends Model
{
    use HasActivityLog, HasAuditFields, SoftDeletes;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_POSTED = 'posted';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_SUBMITTED,
        self::STATUS_REJECTED,
        self::STATUS_POSTED,
        self::STATUS_CANCELLED,
    ];

    protected $fillable = [
        'grn_number',
        'purchase_order_id',
        'supplier_id',
        'received_date',
        'supplier_delivery_no',
        'supplier_vehicle_info',
        'supplier_driver_name',
        'status',
        'fiscal_year',
        'received_by',
        'submitted_at',
        'submitted_by',
        'posted_at',
        'posted_by',
        'rejected_at',
        'rejected_by',
        'rejection_reason',
        'cancelled_at',
        'cancelled_by',
        'cancel_reason',
        'has_discrepancy',
        'discrepancy_notes',
        'notes',
        'pdf_path',
        'pdf_generated_at',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'received_date' => 'date',
            'fiscal_year' => 'integer',
            'has_discrepancy' => 'boolean',
            'submitted_at' => 'datetime',
            'posted_at' => 'datetime',
            'rejected_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'pdf_generated_at' => 'datetime',
        ];
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(GrnItem::class)->orderBy('sort_order');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function poster(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    public function rejecter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function canBeEdited(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_REJECTED], true);
    }

    public function canBeSubmitted(): bool
    {
        return $this->canBeEdited() && $this->items()->exists();
    }

    public function canBePosted(): bool
    {
        return $this->status === self::STATUS_SUBMITTED;
    }

    public function canBeRejected(): bool
    {
        return $this->status === self::STATUS_SUBMITTED;
    }

    public function canBeCancelled(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    /**
     * Cek apakah ada item yang qty_reguler-nya melebihi sisa qty_ordered di po_item.
     */
    public function hasOverReceive(): bool
    {
        $this->loadMissing('items.poItem');
        foreach ($this->items as $item) {
            $newCumulative = (int) $item->poItem->qty_received + (int) $item->qty_reguler;
            if ($newCumulative > (int) $item->poItem->qty_ordered) {
                return true;
            }
        }

        return false;
    }

    public function scopeByStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }
}
