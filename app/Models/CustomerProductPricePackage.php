<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Assignment paket harga per (customer, produk). Produk yang tidak punya baris
 * jatuh ke fallback resolveSellPrice (sales-group → paket pertama).
 */
class CustomerProductPricePackage extends Model
{
    protected $fillable = [
        'customer_id',
        'product_id',
        'price_package_id',
        'created_by',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function pricePackage(): BelongsTo
    {
        return $this->belongsTo(ProductPricePackage::class, 'price_package_id');
    }
}
