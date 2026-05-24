<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SoReservation extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_RELEASED = 'released';

    public const STATUS_CONSUMED = 'consumed';

    protected $fillable = [
        'sales_order_id',
        'so_item_id',
        'product_id',
        'batch_id',
        'qty_reserved',
        'status',
        'consumed_at',
        'consumed_by_do_id',
        'released_at',
        'released_reason',
    ];

    protected function casts(): array
    {
        return [
            'qty_reserved' => 'integer',
            'consumed_at' => 'datetime',
            'released_at' => 'datetime',
        ];
    }

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
    }

    public function soItem(): BelongsTo
    {
        return $this->belongsTo(SoItem::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ProductBatch::class, 'batch_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }
}
