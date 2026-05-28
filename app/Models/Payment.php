<?php

namespace App\Models;

use App\Concerns\HasActivityLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Payment extends Model
{
    use HasActivityLog, SoftDeletes;

    public const STATUS_POSTED = 'posted';

    public const STATUS_PENDING_CLEARING = 'pending_clearing';

    public const STATUS_CLEARED = 'cleared';

    public const STATUS_BOUNCED = 'bounced';

    public const STATUSES = [
        self::STATUS_POSTED,
        self::STATUS_PENDING_CLEARING,
        self::STATUS_CLEARED,
        self::STATUS_BOUNCED,
    ];

    protected $fillable = [
        'payment_number',
        'payment_request_id',
        'invoice_id',
        'invoice_snapshot',
        'customer_id',
        'amount',
        'applied_amount',
        'overpayment_amount',
        'method',
        'reference_no',
        'bank_name',
        'giro_due_date',
        'paid_at',
        'verified_at',
        'cleared_at',
        'applied_to_invoice_at',
        'bounced_at',
        'bounce_reason',
        'status',
        'fiscal_year',
        'proof_image_path',
        'notes',
        'recorded_by',
        'cleared_by',
        'bounced_by',
    ];

    protected function casts(): array
    {
        return [
            'invoice_snapshot' => 'array',
            'amount' => 'decimal:2',
            'applied_amount' => 'decimal:2',
            'overpayment_amount' => 'decimal:2',
            'giro_due_date' => 'date',
            'paid_at' => 'datetime',
            'verified_at' => 'datetime',
            'cleared_at' => 'datetime',
            'applied_to_invoice_at' => 'datetime',
            'bounced_at' => 'datetime',
            'fiscal_year' => 'integer',
        ];
    }

    public function paymentRequest(): BelongsTo
    {
        return $this->belongsTo(PaymentRequest::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function clearer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cleared_by');
    }

    public function bouncer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'bounced_by');
    }

    public function supplierAllocations(): HasMany
    {
        return $this->hasMany(PaymentSupplierAllocation::class);
    }

    public function canBeCleared(): bool
    {
        return $this->status === self::STATUS_PENDING_CLEARING;
    }

    public function canBeBounced(): bool
    {
        return $this->status === self::STATUS_PENDING_CLEARING;
    }

    public function scopeByStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    public function scopePendingClearing(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING_CLEARING);
    }
}
