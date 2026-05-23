<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class WaNotification extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    public const STATUS_SKIPPED = 'skipped';

    public const SKIP_RATE_LIMITED = 'rate_limited';

    public const SKIP_DUPLICATE = 'duplicate';

    public const SKIP_SETTING_OFF = 'setting_off';

    public const SKIP_NO_PHONE = 'no_phone';

    public const RECIPIENT_CUSTOMER = 'customer';

    public const RECIPIENT_USER = 'user';

    protected $table = 'wa_notifications';

    public $timestamps = false;

    protected $fillable = [
        'category',
        'recipient_type',
        'recipient_id',
        'recipient_name',
        'recipient_phone',
        'template_used',
        'message',
        'context_data',
        'related_ref_type',
        'related_ref_id',
        'status',
        'skip_reason',
        'sent_at',
        'response_payload',
        'gateway_message_id',
        'error_message',
        'retry_count',
        'triggered_by',
        'is_manual',
        'created_at',
        'updated_at',
    ];

    protected function casts(): array
    {
        return [
            'context_data' => 'array',
            'response_payload' => 'array',
            'is_manual' => 'boolean',
            'retry_count' => 'integer',
            'sent_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeOfCategory(Builder $query, string $category): Builder
    {
        return $query->where('category', $category);
    }
}
