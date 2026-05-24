<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SoItem extends Model
{
    protected $fillable = [
        'sales_order_id',
        'product_id',
        'product_unit_id',
        'product_name_snapshot',
        'product_sku_snapshot',
        'product_unit_name_snapshot',
        'qty',
        'qty_delivered',
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
            'qty_delivered' => 'integer',
            'unit_price' => 'decimal:2',
            'discount_z1_pct' => 'decimal:2',
            'discount_z2_pct' => 'decimal:2',
            'unit_net_price' => 'decimal:2',
            'line_subtotal' => 'decimal:2',
            'is_bonus' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function productUnit(): BelongsTo
    {
        return $this->belongsTo(ProductUnit::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(SoReservation::class);
    }

    /**
     * Compound Z1+Z2 net price: price × (1-z1) × (1-z2). Bonus → 0.
     */
    public static function computeUnitNetPrice(
        float $price,
        float $z1Pct,
        float $z2Pct,
        bool $isBonus,
    ): float {
        if ($isBonus) {
            return 0.0;
        }

        return round($price * (1 - $z1Pct / 100) * (1 - $z2Pct / 100), 2);
    }
}
