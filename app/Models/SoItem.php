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
        'supplier_id',
        'product_unit_id',
        'product_name_snapshot',
        'product_sku_snapshot',
        'product_unit_name_snapshot',
        'qty',
        'qty_delivered',
        'unit_price',
        'discount_z1_pct',
        'discount_z2_pct',
        'discount_type',
        'discount_value',
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
            'discount_value' => 'decimal:2',
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

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
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

    public const DISCOUNT_PERCENT = 'percent';

    public const DISCOUNT_RP = 'rp';

    public const DISCOUNT_TYPES = [self::DISCOUNT_PERCENT, self::DISCOUNT_RP];

    /**
     * Harga net per unit setelah diskon per-item. Diskon bisa persen atau rupiah
     * (per unit). Bonus → 0. Net tidak boleh negatif.
     */
    public static function computeUnitNetPrice(
        float $price,
        ?string $discountType,
        float $discountValue,
        bool $isBonus,
    ): float {
        if ($isBonus) {
            return 0.0;
        }

        $net = match ($discountType) {
            self::DISCOUNT_PERCENT => $price * (1 - max(0.0, min(100.0, $discountValue)) / 100),
            self::DISCOUNT_RP => $price - $discountValue,
            default => $price,
        };

        return round(max(0.0, $net), 2);
    }
}
