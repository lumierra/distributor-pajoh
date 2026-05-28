<?php

namespace App\Models;

use App\Concerns\HasAuditFields;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductGroup extends Model
{
    use HasAuditFields, SoftDeletes;

    protected $fillable = [
        'code',
        'name',
        'description',
        'is_active',
        'sort_order',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_group_items')->withTimestamps();
    }

    public function salesUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'sales_product_groups')
            ->withPivot('monthly_limit', 'created_by')
            ->withTimestamps();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
