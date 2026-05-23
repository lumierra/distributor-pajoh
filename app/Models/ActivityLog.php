<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ActivityLog extends Model
{
    public const CHANNEL_WEB = 'web';

    public const CHANNEL_MOBILE = 'mobile';

    public const CHANNEL_SYSTEM = 'system';

    protected $table = 'activity_logs';

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'user_name_snapshot',
        'action',
        'model_type',
        'model_id',
        'model_label',
        'before',
        'after',
        'context',
        'ip',
        'user_agent',
        'channel',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'before' => 'array',
            'after' => 'array',
            'context' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function model(): MorphTo
    {
        return $this->morphTo();
    }
}
