<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesActivitySnapshot extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'snapshot_date',
        'sales_id',
        'total_visits',
        'valid_visits',
        'unique_customers_visited',
        'total_visit_duration_min',
        'so_count',
        'so_value',
        'so_approved_count',
        'so_cancelled_count',
        'conversion_rate',
        'target_value',
        'achievement_percent',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'snapshot_date' => 'date',
            'total_visits' => 'integer',
            'valid_visits' => 'integer',
            'unique_customers_visited' => 'integer',
            'total_visit_duration_min' => 'integer',
            'so_count' => 'integer',
            'so_value' => 'decimal:2',
            'so_approved_count' => 'integer',
            'so_cancelled_count' => 'integer',
            'conversion_rate' => 'decimal:2',
            'target_value' => 'decimal:2',
            'achievement_percent' => 'decimal:2',
            'created_at' => 'datetime',
        ];
    }

    public function sales(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sales_id');
    }
}
