<?php

namespace App\Models;

use App\Concerns\HasActivityLog;
use App\Concerns\HasAuditFields;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasActivityLog, HasAuditFields, SoftDeletes;

    protected $fillable = [
        'supplier_id',
        'sku',
        'name',
        'category_id',
        'base_unit_id',
        'description',
        'image_path',
        'is_active',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }

    public function baseUnit(): BelongsTo
    {
        return $this->belongsTo(ProductUnit::class, 'base_unit_id');
    }

    public function units(): HasMany
    {
        return $this->hasMany(ProductUnit::class)->orderBy('qty_to_base', 'desc');
    }

    public function batches(): HasMany
    {
        return $this->hasMany(ProductBatch::class);
    }

    /**
     * Paket harga bernama milik produk ini. Tiap paket berisi baris
     * (satuan → modal + jual). Paket di-assign ke sales lewat Product Group.
     */
    public function pricePackages(): HasMany
    {
        return $this->hasMany(ProductPricePackage::class)->orderBy('sort_order');
    }

    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(ProductGroup::class, 'product_group_items')
            ->withPivot('price_package_id')
            ->withTimestamps();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeWithCategory(Builder $query, string $code): Builder
    {
        return $query->whereHas('category', fn ($q) => $q->where('code', $code));
    }

    /**
     * Format stok dari base unit menjadi "10 Krt + 2 Pak + 4 Pcs".
     * Membutuhkan relasi `units` ter-load.
     */
    public function formatStock(int $baseQty): string
    {
        // Diimplementasikan via UomConverter — di sini stub.
        return (string) $baseQty;
    }
}
