<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DoItem extends Model
{
    protected $fillable = [
        'delivery_order_id',
        'so_item_id',
        'reservation_id',
        'product_id',
        'product_unit_id',
        'product_name_snapshot',
        'product_sku_snapshot',
        'product_unit_name_snapshot',
        'batch_id',
        'batch_code_snapshot',
        'expired_date_snapshot',
        'qty_planned',
        'qty_picked',
        'qty_delivered',
        'qty_returned',
        'is_bonus',
        'qty_planned_base',
        'qty_delivered_base',
        'qty_returned_base',
        'cost_price_base',
        'notes',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'expired_date_snapshot' => 'date',
            'qty_planned' => 'integer',
            'qty_picked' => 'integer',
            'qty_delivered' => 'integer',
            'qty_returned' => 'integer',
            'is_bonus' => 'boolean',
            'qty_planned_base' => 'integer',
            'qty_delivered_base' => 'integer',
            'qty_returned_base' => 'integer',
            'cost_price_base' => 'decimal:4',
            'sort_order' => 'integer',
        ];
    }

    public function deliveryOrder(): BelongsTo
    {
        return $this->belongsTo(DeliveryOrder::class);
    }

    public function soItem(): BelongsTo
    {
        return $this->belongsTo(SoItem::class);
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(SoReservation::class, 'reservation_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function productUnit(): BelongsTo
    {
        return $this->belongsTo(ProductUnit::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ProductBatch::class, 'batch_id');
    }
}
