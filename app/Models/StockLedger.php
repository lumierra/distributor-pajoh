<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Immutable journal stok. Append-only.
 *
 * JANGAN update / delete row via Eloquent — pakai insert baru bertipe
 * `adjustment_in/out` untuk koreksi.
 */
class StockLedger extends Model
{
    public $timestamps = false;

    protected $table = 'stock_ledger';

    public const TYPE_PURCHASE_IN = 'purchase_in';

    public const TYPE_BONUS_IN = 'bonus_in';

    public const TYPE_SALE_OUT = 'sale_out';

    public const TYPE_RETURN_IN = 'return_in';

    public const TYPE_RETURN_OUT_TO_SUPPLIER = 'return_out_to_supplier';

    public const TYPE_ADJUSTMENT_IN = 'adjustment_in';

    public const TYPE_ADJUSTMENT_OUT = 'adjustment_out';

    public const TYPE_OPNAME_IN = 'opname_in';

    public const TYPE_OPNAME_OUT = 'opname_out';

    public const TYPE_OPENING_IN = 'opening_in';

    public const TYPE_WRITE_OFF = 'write_off';

    public const TYPES = [
        self::TYPE_PURCHASE_IN,
        self::TYPE_BONUS_IN,
        self::TYPE_SALE_OUT,
        self::TYPE_RETURN_IN,
        self::TYPE_RETURN_OUT_TO_SUPPLIER,
        self::TYPE_ADJUSTMENT_IN,
        self::TYPE_ADJUSTMENT_OUT,
        self::TYPE_OPNAME_IN,
        self::TYPE_OPNAME_OUT,
        self::TYPE_OPENING_IN,
        self::TYPE_WRITE_OFF,
    ];

    protected $fillable = [
        'product_id',
        'batch_id',
        'product_unit_id',
        'type',
        'is_bonus_pool',
        'qty_in',
        'qty_out',
        'cost_price',
        'ref_type',
        'ref_id',
        'notes',
        'created_by',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'is_bonus_pool' => 'boolean',
            'qty_in' => 'integer',
            'qty_out' => 'integer',
            'cost_price' => 'decimal:4',
            'ref_id' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ProductBatch::class, 'batch_id');
    }

    public function productUnit(): BelongsTo
    {
        return $this->belongsTo(ProductUnit::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeForProduct(Builder $query, int $productId): Builder
    {
        return $query->where('product_id', $productId);
    }

    public function scopeForBatch(Builder $query, int $batchId): Builder
    {
        return $query->where('batch_id', $batchId);
    }

    public function scopeByType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    public function scopeByRef(Builder $query, string $refType, int $refId): Builder
    {
        return $query->where('ref_type', $refType)->where('ref_id', $refId);
    }
}
