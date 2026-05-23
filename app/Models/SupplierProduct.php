<?php

namespace App\Models;

use App\Concerns\HasAuditFields;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Pivot Supplier ↔ Product dengan atribut tambahan
 * (supplier_sku, default_cost_price, moq, is_primary).
 */
class SupplierProduct extends Pivot
{
    use HasAuditFields;

    protected $table = 'supplier_products';

    public $incrementing = true;

    protected $fillable = [
        'supplier_id',
        'product_id',
        'supplier_sku',
        'default_cost_price',
        'moq',
        'is_primary',
        'is_active',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'default_cost_price' => 'decimal:2',
            'moq' => 'integer',
            'is_primary' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
