<?php

namespace App\Models;

use App\Concerns\HasAuditFields;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pricing pivot per (supplier × produk × satuan). Sumber kebenaran untuk
 * cost & sell price yang dipakai SalesOrderService.
 */
class SupplierProductUnit extends Model
{
    use HasAuditFields;

    protected $fillable = [
        'supplier_id',
        'product_id',
        'product_unit_id',
        'cost_price',
        'sell_price',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'cost_price' => 'decimal:2',
            'sell_price' => 'decimal:2',
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

    public function productUnit(): BelongsTo
    {
        return $this->belongsTo(ProductUnit::class);
    }
}
