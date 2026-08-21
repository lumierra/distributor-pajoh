<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockAdjustmentItem extends Model
{
    public const DIRECTION_IN = 'in';

    public const DIRECTION_OUT = 'out';

    public const DIRECTIONS = [self::DIRECTION_IN, self::DIRECTION_OUT];

    protected $fillable = [
        'stock_adjustment_id',
        'product_id',
        'product_unit_id',
        'product_unit_name_snapshot',
        'batch_id',
        'product_name_snapshot',
        'product_sku_snapshot',
        'batch_code_snapshot',
        'direction',
        'qty',
        'qty_base',
        'system_qty_snapshot',
        'cost_price',
        'notes',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'integer',
            'qty_base' => 'integer',
            'system_qty_snapshot' => 'integer',
            'cost_price' => 'decimal:2',
            'sort_order' => 'integer',
        ];
    }

    public function adjustment(): BelongsTo
    {
        return $this->belongsTo(StockAdjustment::class, 'stock_adjustment_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ProductBatch::class, 'batch_id');
    }
}
