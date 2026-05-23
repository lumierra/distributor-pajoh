<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only audit untuk perubahan harga produk.
 * Tabel di-partition by year (YEAR(changed_at)).
 */
class ProductPriceHistory extends Model
{
    public const REASON_MANUAL = 'manual';

    public const REASON_BULK = 'bulk_update';

    protected $table = 'product_price_history';

    public $timestamps = false;

    protected $fillable = [
        'product_id',
        'product_unit_id',
        'price_tier_id',
        'old_price',
        'new_price',
        'change_reason',
        'bulk_update_id',
        'changed_by',
        'changed_at',
    ];

    protected function casts(): array
    {
        return [
            'old_price' => 'decimal:2',
            'new_price' => 'decimal:2',
            'changed_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(ProductUnit::class, 'product_unit_id');
    }

    public function tier(): BelongsTo
    {
        return $this->belongsTo(PriceTier::class, 'price_tier_id');
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
