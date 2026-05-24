<?php

namespace App\Models;

use App\Concerns\HasActivityLog;
use App\Concerns\HasAuditFields;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Invoice extends Model
{
    use HasActivityLog, HasAuditFields, SoftDeletes;

    public const STATUS_OPEN = 'open';

    public const STATUS_PARTIAL_PAID = 'partial_paid';

    public const STATUS_PAID = 'paid';

    public const STATUS_OVERDUE = 'overdue';

    public const STATUSES = [
        self::STATUS_OPEN,
        self::STATUS_PARTIAL_PAID,
        self::STATUS_PAID,
        self::STATUS_OVERDUE,
    ];

    protected $fillable = [
        'invoice_number',
        'sales_order_id',
        'delivery_order_id',
        'customer_id',
        'customer_snapshot',
        'sales_id',
        'sales_name_snapshot',
        'driver_name_snapshot',
        'vehicle_plate_snapshot',
        'invoice_date',
        'payment_term_days',
        'due_date',
        'original_due_date',
        'is_cash',
        'status',
        'fiscal_year',
        'is_carry_over',
        'subtotal',
        'header_discount_amount',
        'total',
        'paid_amount',
        'outstanding',
        'notes',
        'delivery_notes_snapshot',
        'pdf_path',
        'pdf_generated_at',
        'last_reminder_sent_at',
        'overdue_set_at',
        'paid_at',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'customer_snapshot' => 'array',
            'invoice_date' => 'date',
            'due_date' => 'date',
            'original_due_date' => 'date',
            'payment_term_days' => 'integer',
            'is_cash' => 'boolean',
            'fiscal_year' => 'integer',
            'is_carry_over' => 'boolean',
            'subtotal' => 'decimal:2',
            'header_discount_amount' => 'decimal:2',
            'total' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'outstanding' => 'decimal:2',
            'pdf_generated_at' => 'datetime',
            'last_reminder_sent_at' => 'datetime',
            'overdue_set_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
    }

    public function deliveryOrder(): BelongsTo
    {
        return $this->belongsTo(DeliveryOrder::class);
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
        return $this->hasMany(InvoiceItem::class)->orderBy('sort_order');
    }

    public function scopeByStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    public function scopeOpenOrPartial(Builder $query): Builder
    {
        return $query->whereIn('status', [self::STATUS_OPEN, self::STATUS_PARTIAL_PAID, self::STATUS_OVERDUE]);
    }

    public function scopeOverdueCandidates(Builder $query): Builder
    {
        return $query
            ->whereIn('status', [self::STATUS_OPEN, self::STATUS_PARTIAL_PAID])
            ->whereDate('due_date', '<', now())
            ->where('outstanding', '>', 0);
    }
}
