<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockOpeningItem extends Model
{
    protected $fillable = [
        'stock_opening_id',
        'product_id',
        'product_unit_id',
        'product_unit_name_snapshot',
        'supplier_id',
        'batch_id',
        'batch_code',
        'expired_date',
        'product_name_snapshot',
        'product_sku_snapshot',
        'qty',
        'qty_base',
        'qty_bonus',
        'qty_bonus_base',
        'cost_price',
        'notes',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'expired_date' => 'date',
            'qty' => 'integer',
            'qty_base' => 'integer',
            'qty_bonus' => 'integer',
            'qty_bonus_base' => 'integer',
            'cost_price' => 'decimal:2',
            'sort_order' => 'integer',
        ];
    }

    public function opening(): BelongsTo
    {
        return $this->belongsTo(StockOpening::class, 'stock_opening_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function productUnit(): BelongsTo
    {
        return $this->belongsTo(ProductUnit::class, 'product_unit_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ProductBatch::class, 'batch_id');
    }
}
