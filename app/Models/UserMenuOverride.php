<?php

namespace App\Models;

use App\Concerns\HasAuditFields;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserMenuOverride extends Model
{
    use HasAuditFields;

    protected $table = 'user_menu_override';

    protected $fillable = [
        'user_id',
        'menu_id',
        'can_view',
        'can_create',
        'can_update',
        'can_delete',
        'can_approve',
        'can_export',
        'note',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'can_view' => 'boolean',
            'can_create' => 'boolean',
            'can_update' => 'boolean',
            'can_delete' => 'boolean',
            'can_approve' => 'boolean',
            'can_export' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function menu(): BelongsTo
    {
        return $this->belongsTo(Menu::class);
    }
}
