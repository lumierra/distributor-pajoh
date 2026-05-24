<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceItem extends Model
{
    protected $fillable = [
        'invoice_id',
        'sales_order_item_id',
        'do_item_id',
        'product_id',
        'product_unit_id',
        'product_name_snapshot',
        'product_sku_snapshot',
        'product_unit_name_snapshot',
        'batch_code_snapshot',
        'qty',
        'unit_price',
        'discount_z1_pct',
        'discount_z2_pct',
        'unit_net_price',
        'line_subtotal',
        'is_bonus',
        'notes',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'integer',
            'unit_price' => 'decimal:2',
            'discount_z1_pct' => 'decimal:2',
            'discount_z2_pct' => 'decimal:2',
            'unit_net_price' => 'decimal:2',
            'line_subtotal' => 'decimal:2',
            'is_bonus' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function soItem(): BelongsTo
    {
        return $this->belongsTo(SoItem::class, 'sales_order_item_id');
    }

    public function doItem(): BelongsTo
    {
        return $this->belongsTo(DoItem::class, 'do_item_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
