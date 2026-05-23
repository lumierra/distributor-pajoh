<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockBalance extends Model
{
    protected $fillable = [
        'product_id',
        'batch_id',
        'qty_on_hand',
        'qty_bonus_pool',
        'qty_reserved',
        'last_movement_at',
    ];

    protected function casts(): array
    {
        return [
            'qty_on_hand' => 'integer',
            'qty_bonus_pool' => 'integer',
            'qty_reserved' => 'integer',
            'last_movement_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ProductBatch::class, 'batch_id');
    }

    protected function qtyAvailable(): Attribute
    {
        return Attribute::make(
            get: fn (): int => $this->qty_on_hand - $this->qty_reserved,
        );
    }

    public function scopeAvailable(Builder $query): Builder
    {
        return $query->whereRaw('qty_on_hand - qty_reserved > 0');
    }

    public function scopeOutOfStock(Builder $query): Builder
    {
        return $query->where('qty_on_hand', '<=', 0);
    }
}
