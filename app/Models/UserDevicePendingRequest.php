<?php

namespace App\Models;

use App\Concerns\HasActivityLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserDevicePendingRequest extends Model
{
    use HasActivityLog;

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUSES = [self::STATUS_PENDING, self::STATUS_APPROVED, self::STATUS_REJECTED];

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'device_uuid',
        'mac_address',
        'device_name',
        'device_model',
        'os',
        'os_version',
        'app_version',
        'requested_ip',
        'requested_at',
        'status',
        'reviewed_at',
        'reviewed_by',
        'rejection_reason',
        'activated_device_id',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'requested_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function activatedDevice(): BelongsTo
    {
        return $this->belongsTo(UserDevice::class, 'activated_device_id');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }
}
