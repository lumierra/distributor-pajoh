<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoginHistory extends Model
{
    public const CHANNEL_WEB = 'web';

    public const CHANNEL_MOBILE = 'mobile';

    public const FAIL_INVALID_CREDENTIALS = 'invalid_credentials';

    public const FAIL_ACCOUNT_INACTIVE = 'account_inactive';

    public const FAIL_THROTTLED = 'throttled';

    protected $table = 'login_history';

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'username_input',
        'is_successful',
        'failure_reason',
        'ip',
        'user_agent',
        'device_uuid',
        'channel',
        'login_at',
    ];

    protected function casts(): array
    {
        return [
            'is_successful' => 'boolean',
            'login_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
