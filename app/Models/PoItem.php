<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PoItem extends Model
{
    protected $fillable = [
        'purchase_order_id',
        'product_id',
        'product_unit_id',
        'product_name_snapshot',
        'product_sku_snapshot',
        'product_unit_name_snapshot',
        'qty_ordered',
        'qty_received',
        'bonus_qty',
        'bonus_qty_received',
        'cost_price',
        'discount_z1_pct',
        'discount_z2_pct',
        'unit_net_cost',
        'line_subtotal',
        'notes',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'qty_ordered' => 'integer',
            'qty_received' => 'integer',
            'bonus_qty' => 'integer',
            'bonus_qty_received' => 'integer',
            'cost_price' => 'decimal:2',
            'discount_z1_pct' => 'decimal:2',
            'discount_z2_pct' => 'decimal:2',
            'unit_net_cost' => 'decimal:2',
            'line_subtotal' => 'decimal:2',
            'sort_order' => 'integer',
        ];
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function productUnit(): BelongsTo
    {
        return $this->belongsTo(ProductUnit::class);
    }

    /**
     * Compute unit net cost: cost × (1 - z1) × (1 - z2). Compound Z1+Z2.
     */
    public static function computeUnitNetCost(float $cost, float $z1Pct, float $z2Pct): float
    {
        $afterZ1 = $cost * (1 - $z1Pct / 100);

        return round($afterZ1 * (1 - $z2Pct / 100), 2);
    }
}
