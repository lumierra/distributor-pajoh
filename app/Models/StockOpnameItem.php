<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockOpnameItem extends Model
{
    protected $fillable = [
        'stock_opname_id',
        'product_id',
        'batch_id',
        'product_name_snapshot',
        'product_sku_snapshot',
        'batch_code_snapshot',
        'system_qty_snapshot',
        'counted_qty',
        'variance',
        'notes',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'system_qty_snapshot' => 'integer',
            'counted_qty' => 'integer',
            'variance' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function opname(): BelongsTo
    {
        return $this->belongsTo(StockOpname::class, 'stock_opname_id');
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
