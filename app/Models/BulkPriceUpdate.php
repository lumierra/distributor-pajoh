<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BulkPriceUpdate extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_APPLIED = 'applied';

    public const STATUS_FAILED = 'failed';

    public const TYPE_PERCENTAGE = 'percentage';

    public const TYPE_FIXED_AMOUNT = 'fixed_amount';

    public const TYPE_FIXED_VALUE = 'fixed_value';

    protected $table = 'product_bulk_price_updates';

    protected $fillable = [
        'name',
        'description',
        'filter_criteria',
        'update_type',
        'update_value',
        'tier_ids',
        'unit_levels',
        'affected_count',
        'status',
        'applied_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'filter_criteria' => 'array',
            'tier_ids' => 'array',
            'unit_levels' => 'array',
            'update_value' => 'decimal:4',
            'affected_count' => 'integer',
            'applied_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
