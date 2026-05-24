<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArAgingSnapshot extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'snapshot_date',
        'customer_id',
        'bucket_0_30',
        'bucket_31_60',
        'bucket_61_90',
        'bucket_over_90',
        'total_outstanding',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'snapshot_date' => 'date',
            'bucket_0_30' => 'decimal:2',
            'bucket_31_60' => 'decimal:2',
            'bucket_61_90' => 'decimal:2',
            'bucket_over_90' => 'decimal:2',
            'total_outstanding' => 'decimal:2',
            'created_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
