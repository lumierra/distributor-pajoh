<?php

namespace App\Models;

use App\Concerns\HasAuditFields;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Setting extends Model
{
    use HasAuditFields, SoftDeletes;

    protected $fillable = [
        'group',
        'key',
        'value',
        'default_value',
        'type',
        'label',
        'description',
        'validation',
        'options',
        'is_sensitive',
        'is_readonly',
        'sort_order',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'json',
            'default_value' => 'json',
            'options' => 'array',
            'is_sensitive' => 'boolean',
            'is_readonly' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function scopeOfGroup(Builder $query, string $group): Builder
    {
        return $query->where('group', $group);
    }

    public function scopePublic(Builder $query): Builder
    {
        return $query->where('is_sensitive', false);
    }

    public function scopeSensitive(Builder $query): Builder
    {
        return $query->where('is_sensitive', true);
    }

    public function getFullKeyAttribute(): string
    {
        return "{$this->group}.{$this->key}";
    }

    /**
     * Cast value according to type, used by SettingManager.
     */
    public function castedValue(): mixed
    {
        $value = $this->value;

        return match ($this->type) {
            'int' => is_null($value) ? null : (int) $value,
            'float' => is_null($value) ? null : (float) $value,
            'bool' => filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
            'json', 'array' => is_string($value) ? json_decode($value, true) : $value,
            default => $value,
        };
    }
}
