<?php

namespace App\Models;

use App\Concerns\HasActivityLog;
use App\Concerns\HasAuditFields;
use App\Concerns\HasGeoCoordinates;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use HasActivityLog, HasAuditFields, HasGeoCoordinates, SoftDeletes;

    protected $fillable = [
        'code',
        'name',
        'owner_name',
        'customer_type_id',
        'price_tier_id',
        'npwp',
        'phone',
        'whatsapp',
        'email',
        'address',
        'city',
        'province',
        'postal_code',
        'area',
        'latitude',
        'longitude',
        'geo_confirmed_at',
        'geo_confirmed_by',
        'geo_captured_by',
        'assigned_sales_id',
        'credit_limit',
        'payment_term_days',
        'is_active',
        'notes',
        'tags',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'geo_confirmed_at' => 'datetime',
            'credit_limit' => 'decimal:2',
            'payment_term_days' => 'integer',
            'is_active' => 'boolean',
            'tags' => 'array',
        ];
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(CustomerType::class, 'customer_type_id');
    }

    public function priceTier(): BelongsTo
    {
        return $this->belongsTo(PriceTier::class);
    }

    public function assignedSales(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_sales_id');
    }

    public function geoConfirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'geo_confirmed_by');
    }

    public function geoCapturedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'geo_captured_by');
    }

    public function photos(): HasMany
    {
        return $this->hasMany(CustomerPhoto::class)->orderBy('sort_order');
    }

    public function geoPendings(): HasMany
    {
        return $this->hasMany(CustomerGeoPending::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeAssignedTo(Builder $query, int $userId): Builder
    {
        return $query->where('assigned_sales_id', $userId);
    }

    public function scopeInArea(Builder $query, string $area): Builder
    {
        return $query->where('area', $area);
    }

    public function scopeWithoutGeo(Builder $query): Builder
    {
        return $query->whereNull('latitude')->orWhereNull('longitude');
    }

    public function scopeWithTag(Builder $query, string $tag): Builder
    {
        return $query->whereJsonContains('tags', $tag);
    }

    public function hasTag(string $tag): bool
    {
        return in_array($tag, (array) ($this->tags ?? []), true);
    }
}
