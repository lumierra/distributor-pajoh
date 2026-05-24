<?php

namespace App\Models;

use App\Concerns\HasActivityLog;
use App\Concerns\HasAuditFields;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CreditNote extends Model
{
    use HasActivityLog, HasAuditFields, SoftDeletes;

    public const STATUS_OPEN = 'open';

    public const STATUS_APPLIED = 'applied';

    public const STATUS_CLOSED = 'closed';

    public const STATUSES = [self::STATUS_OPEN, self::STATUS_APPLIED, self::STATUS_CLOSED];

    protected $fillable = [
        'cn_number',
        'customer_return_id',
        'customer_id',
        'cn_date',
        'amount',
        'applied_amount',
        'remaining_amount',
        'status',
        'fiscal_year',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'cn_date' => 'date',
            'amount' => 'decimal:2',
            'applied_amount' => 'decimal:2',
            'remaining_amount' => 'decimal:2',
        ];
    }

    public function customerReturn(): BelongsTo
    {
        return $this->belongsTo(CustomerReturn::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function applications(): HasMany
    {
        return $this->hasMany(CreditNoteApplication::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isFullyApplied(): bool
    {
        return $this->status === self::STATUS_CLOSED || (float) $this->remaining_amount <= 0.0001;
    }

    public function canBeApplied(): bool
    {
        return in_array($this->status, [self::STATUS_OPEN, self::STATUS_APPLIED], true)
            && (float) $this->remaining_amount > 0.0001;
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', [self::STATUS_OPEN, self::STATUS_APPLIED]);
    }
}
