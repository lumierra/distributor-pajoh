<?php

namespace App\Models;

use App\Concerns\HasAuditFields;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductBatch extends Model
{
    use HasAuditFields, SoftDeletes;

    protected $fillable = [
        'product_id',
        'supplier_id',
        'batch_code',
        'production_date',
        'expired_date',
        'initial_qty_base',
        'first_received_at',
        'last_received_at',
        'is_active',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'production_date' => 'date',
            'expired_date' => 'date',
            'initial_qty_base' => 'integer',
            'first_received_at' => 'datetime',
            'last_received_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(StockLedger::class, 'batch_id');
    }

    public function balance(): HasOne
    {
        return $this->hasOne(StockBalance::class, 'batch_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForProduct(Builder $query, int $productId): Builder
    {
        return $query->where('product_id', $productId);
    }

    public function scopeExpired(Builder $query): Builder
    {
        return $query->whereNotNull('expired_date')
            ->whereDate('expired_date', '<', now());
    }

    public function scopeExpiringSoon(Builder $query, int $days = 30): Builder
    {
        return $query->whereNotNull('expired_date')
            ->whereDate('expired_date', '>=', now())
            ->whereDate('expired_date', '<=', now()->addDays($days));
    }

    public function isExpired(): bool
    {
        return $this->expired_date !== null && $this->expired_date->isPast();
    }

    public function daysUntilExpired(): ?int
    {
        return $this->expired_date?->diffInDays(now(), false) * -1; // future = positive
    }
}
