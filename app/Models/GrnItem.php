<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GrnItem extends Model
{
    public const CONDITION_GOOD = 'good';

    public const CONDITION_DAMAGED = 'damaged';

    public const CONDITION_MIXED = 'mixed';

    public const CONDITIONS = [self::CONDITION_GOOD, self::CONDITION_DAMAGED, self::CONDITION_MIXED];

    protected $fillable = [
        'goods_receipt_id',
        'po_item_id',
        'product_id',
        'product_unit_id',
        'product_name_snapshot',
        'product_sku_snapshot',
        'product_unit_name_snapshot',
        'batch_id',
        'batch_code',
        'production_date',
        'expired_date',
        'qty_reguler',
        'qty_bonus',
        'qty_damaged',
        'qty_reguler_base',
        'qty_bonus_base',
        'cost_price',
        'cost_price_base',
        'cost_overridden',
        'cost_override_reason',
        'condition',
        'notes',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'production_date' => 'date',
            'expired_date' => 'date',
            'qty_reguler' => 'integer',
            'qty_bonus' => 'integer',
            'qty_damaged' => 'integer',
            'qty_reguler_base' => 'integer',
            'qty_bonus_base' => 'integer',
            'cost_price' => 'decimal:2',
            'cost_price_base' => 'decimal:4',
            'cost_overridden' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function goodsReceipt(): BelongsTo
    {
        return $this->belongsTo(GoodsReceipt::class);
    }

    public function poItem(): BelongsTo
    {
        return $this->belongsTo(PoItem::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function productUnit(): BelongsTo
    {
        return $this->belongsTo(ProductUnit::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ProductBatch::class, 'batch_id');
    }
}
