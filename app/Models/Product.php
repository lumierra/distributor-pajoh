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
        'sku',
        'name',
        'brand',
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

    public function supplierProductUnits(): HasMany
    {
        return $this->hasMany(SupplierProductUnit::class);
    }

    public function suppliers(): BelongsToMany
    {
        return $this->belongsToMany(Supplier::class, 'supplier_products')
            ->using(SupplierProduct::class)
            ->withPivot([
                'supplier_sku', 'moq',
                'is_primary', 'is_active', 'notes',
            ])
            ->withTimestamps();
    }

    public function supplierProducts(): HasMany
    {
        return $this->hasMany(SupplierProduct::class);
    }

    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(ProductGroup::class, 'product_group_items')
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
