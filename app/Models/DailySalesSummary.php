<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailySalesSummary extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'snapshot_date',
        'sales_id',
        'customer_id',
        'product_id',
        'category_id',
        'invoice_count',
        'qty_sold_base',
        'revenue',
        'discount_total',
        'cost_total',
        'margin',
        'margin_percent',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'snapshot_date' => 'date',
            'invoice_count' => 'integer',
            'qty_sold_base' => 'integer',
            'revenue' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'cost_total' => 'decimal:2',
            'margin' => 'decimal:2',
            'margin_percent' => 'decimal:2',
            'created_at' => 'datetime',
        ];
    }

    public function sales(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sales_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
