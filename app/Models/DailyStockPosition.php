<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyStockPosition extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'snapshot_date',
        'product_id',
        'batch_id',
        'qty_on_hand_base',
        'qty_reserved_base',
        'qty_bonus_pool_base',
        'avg_cost',
        'stock_value',
        'days_since_last_movement',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'snapshot_date' => 'date',
            'qty_on_hand_base' => 'integer',
            'qty_reserved_base' => 'integer',
            'qty_bonus_pool_base' => 'integer',
            'avg_cost' => 'decimal:4',
            'stock_value' => 'decimal:2',
            'days_since_last_movement' => 'integer',
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
}
