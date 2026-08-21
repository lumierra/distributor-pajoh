<?php

namespace App\Models;

use App\Concerns\HasAuditFields;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Paket harga bernama milik satu produk (mis. "Harga Reguler", "Harga
 * Grosir"). Berisi baris harga per satuan. Di-assign ke sales lewat
 * Product Group (product_group_items.price_package_id).
 */
class ProductPricePackage extends Model
{
    use HasAuditFields, SoftDeletes;

    protected $fillable = [
        'product_id',
        'name',
        'sort_order',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ProductPricePackageItem::class, 'price_package_id');
    }
}
