<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierReturnItem extends Model
{
    public const SOURCE_GRN_DAMAGED = 'grn_damaged';

    public const SOURCE_CUSTOMER_RETURN_BS = 'customer_return_bs';

    public const SOURCE_STOCK = 'stock';

    public const SOURCE_ADJUSTMENT = 'adjustment';

    public const SOURCES = [
        self::SOURCE_GRN_DAMAGED,
        self::SOURCE_CUSTOMER_RETURN_BS,
        self::SOURCE_STOCK,
        self::SOURCE_ADJUSTMENT,
    ];

    protected $fillable = [
        'supplier_return_id',
        'source_type',
        'source_id',
        'grn_item_id',
        'customer_return_item_id',
        'stock_adjustment_item_id',
        'product_id',
        'product_unit_id',
        'product_name_snapshot',
        'product_sku_snapshot',
        'product_unit_name_snapshot',
        'batch_id',
        'batch_code_snapshot',
        'qty',
        'qty_base',
        'cost_price',
        'line_value',
        'notes',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'integer',
            'qty_base' => 'integer',
            'cost_price' => 'decimal:2',
            'line_value' => 'decimal:2',
            'sort_order' => 'integer',
        ];
    }

    public function supplierReturn(): BelongsTo
    {
        return $this->belongsTo(SupplierReturn::class);
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

    public function grnItem(): BelongsTo
    {
        return $this->belongsTo(GrnItem::class);
    }

    public function customerReturnItem(): BelongsTo
    {
        return $this->belongsTo(CustomerReturnItem::class);
    }
}
