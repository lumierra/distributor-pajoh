<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerReturnItem extends Model
{
    protected $fillable = [
        'customer_return_id',
        'invoice_item_id',
        'do_item_id',
        'so_item_id',
        'product_id',
        'product_unit_id',
        'product_name_snapshot',
        'product_sku_snapshot',
        'product_unit_name_snapshot',
        'batch_id',
        'batch_code_snapshot',
        'qty_total',
        'qty_good',
        'qty_bs',
        'qty_total_base',
        'qty_good_base',
        'qty_bs_base',
        'unit_price',
        'line_value',
        'notes',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'qty_total' => 'integer',
            'qty_good' => 'integer',
            'qty_bs' => 'integer',
            'qty_total_base' => 'integer',
            'qty_good_base' => 'integer',
            'qty_bs_base' => 'integer',
            'unit_price' => 'decimal:2',
            'line_value' => 'decimal:2',
            'sort_order' => 'integer',
        ];
    }

    public function customerReturn(): BelongsTo
    {
        return $this->belongsTo(CustomerReturn::class);
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

    public function invoiceItem(): BelongsTo
    {
        return $this->belongsTo(InvoiceItem::class);
    }

    public function doItem(): BelongsTo
    {
        return $this->belongsTo(DoItem::class);
    }

    public function soItem(): BelongsTo
    {
        return $this->belongsTo(SoItem::class);
    }
}
