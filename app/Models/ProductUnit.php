<?php

namespace App\Models;

use App\Concerns\HasAuditFields;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductUnit extends Model
{
    use HasAuditFields, SoftDeletes;

    public const LEVEL_BSR = 'BSR'; // Besar (Karton/Box)

    public const LEVEL_TGH = 'TGH'; // Tengah (Pak/Pack)

    public const LEVEL_KCL = 'KCL'; // Kecil (Pcs) — base unit

    public const LEVELS = [self::LEVEL_BSR, self::LEVEL_TGH, self::LEVEL_KCL];

    protected $fillable = [
        'product_id',
        'unit_id',
        'level',
        'name',
        'qty_to_base',
        'barcode',
        'sort_order',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'qty_to_base' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function supplierProductUnits(): HasMany
    {
        return $this->hasMany(SupplierProductUnit::class);
    }
}
