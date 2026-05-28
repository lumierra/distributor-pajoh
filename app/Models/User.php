<?php

namespace App\Models;

use App\Concerns\HasAuditFields;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasAuditFields, HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'name',
        'username',
        'email',
        'phone',
        'photo_path',
        'password',
        'role_id',
        'is_active',
        'force_password_change',
        'password_changed_at',
        'last_login_at',
        'last_login_ip',
        'address',
        'city',
        'nik',
        'emergency_contact_name',
        'emergency_contact_phone',
        'hire_date',
        'photo_ktp_path',
        'default_area',
        'monthly_target',
        'created_by',
        'updated_by',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean',
            'force_password_change' => 'boolean',
            'password_changed_at' => 'datetime',
            'last_login_at' => 'datetime',
            'hire_date' => 'date',
            'monthly_target' => 'decimal:2',
        ];
    }

    /* ---------------------------------------------------------------- relations -- */

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function menuOverrides(): HasMany
    {
        return $this->hasMany(UserMenuOverride::class);
    }

    public function devices(): HasMany
    {
        return $this->hasMany(UserDevice::class);
    }

    public function loginHistories(): HasMany
    {
        return $this->hasMany(LoginHistory::class);
    }

    public function passwordHistory(): HasMany
    {
        return $this->hasMany(PasswordHistory::class)->orderByDesc('created_at');
    }

    public function productGroups(): BelongsToMany
    {
        return $this->belongsToMany(ProductGroup::class, 'sales_product_groups')
            ->withPivot('monthly_limit', 'created_by')
            ->withTimestamps();
    }

    /* ------------------------------------------------------------------- scopes -- */

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOfRole(Builder $query, string $roleCode): Builder
    {
        return $query->whereHas('role', fn (Builder $q) => $q->where('code', $roleCode));
    }

    /* ----------------------------------------------------------- role / can(...) -- */

    public function isSuperadmin(): bool
    {
        return $this->role?->code === Role::CODE_SUPERADMIN;
    }

    public function hasRole(string $code): bool
    {
        return $this->role?->code === $code;
    }

    /**
     * Resolve permission for a menu by code following the documented algorithm:
     * superadmin bypass → user override → role permission.
     *
     * @param  'view'|'create'|'update'|'delete'|'approve'|'export'  $action
     */
    public function hasPermission(string $action, string $menuCode): bool
    {
        if ($this->isSuperadmin()) {
            return true;
        }

        $menu = Menu::query()->where('code', $menuCode)->first();
        if (! $menu) {
            return false;
        }

        $column = "can_{$action}";

        $override = UserMenuOverride::query()
            ->where('user_id', $this->id)
            ->where('menu_id', $menu->id)
            ->first();

        if ($override && $override->{$column} !== null) {
            return (bool) $override->{$column};
        }

        $rolePerm = RoleMenu::query()
            ->where('role_id', $this->role_id)
            ->where('menu_id', $menu->id)
            ->first();

        return (bool) ($rolePerm?->{$column} ?? false);
    }

    public function canView(string $menuCode): bool
    {
        return $this->hasPermission('view', $menuCode);
    }

    public function canCreate(string $menuCode): bool
    {
        return $this->hasPermission('create', $menuCode);
    }

    public function canUpdate(string $menuCode): bool
    {
        return $this->hasPermission('update', $menuCode);
    }

    public function canDelete(string $menuCode): bool
    {
        return $this->hasPermission('delete', $menuCode);
    }

    public function canApprove(string $menuCode): bool
    {
        return $this->hasPermission('approve', $menuCode);
    }

    public function canExport(string $menuCode): bool
    {
        return $this->hasPermission('export', $menuCode);
    }
}
