<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu baris harga di dalam paket: satuan produk → modal + jual.
 */
class ProductPricePackageItem extends Model
{
    protected $fillable = [
        'price_package_id',
        'product_unit_id',
        'cost_price',
        'sell_price',
    ];

    protected function casts(): array
    {
        return [
            'cost_price' => 'decimal:2',
            'sell_price' => 'decimal:2',
        ];
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(ProductPricePackage::class, 'price_package_id');
    }

    public function productUnit(): BelongsTo
    {
        return $this->belongsTo(ProductUnit::class);
    }
}
