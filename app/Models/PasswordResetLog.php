<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PasswordResetLog extends Model
{
    protected $table = 'password_reset_logs';

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'reset_by_user_id',
        'reset_at',
        'ip',
        'user_agent',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'reset_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function resetBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reset_by_user_id');
    }
}
