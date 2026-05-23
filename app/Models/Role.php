<?php

namespace App\Models;

use App\Concerns\HasAuditFields;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Role extends Model
{
    use HasAuditFields, SoftDeletes;

    public const CODE_SUPERADMIN = 'superadmin';

    public const CODE_ADMIN = 'admin';

    public const CODE_KASIR = 'kasir';

    public const CODE_OPERATOR = 'operator';

    public const CODE_SALES = 'sales';

    protected $fillable = [
        'code',
        'name',
        'description',
        'is_system',
        'is_active',
        'sort_order',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function menus(): BelongsToMany
    {
        return $this->belongsToMany(Menu::class, 'role_menu')
            ->withPivot(['can_view', 'can_create', 'can_update', 'can_delete', 'can_approve', 'can_export'])
            ->withTimestamps();
    }

    public function roleMenus(): HasMany
    {
        return $this->hasMany(RoleMenu::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOfCode(Builder $query, string $code): Builder
    {
        return $query->where('code', $code);
    }

    public function isSuperadmin(): bool
    {
        return $this->code === self::CODE_SUPERADMIN;
    }
}
