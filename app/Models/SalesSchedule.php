<?php

namespace App\Models;

use App\Concerns\HasActivityLog;
use App\Concerns\HasAuditFields;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

class SalesSchedule extends Model
{
    use HasActivityLog, HasAuditFields, SoftDeletes;

    public const PATTERN_RECURRING = 'recurring';

    public const PATTERN_ONE_TIME = 'one_time';

    public const PATTERNS = [self::PATTERN_RECURRING, self::PATTERN_ONE_TIME];

    protected $fillable = [
        'sales_id',
        'customer_id',
        'pattern',
        'day_of_week',
        'visit_date',
        'visit_time',
        'start_date',
        'end_date',
        'notes',
        'is_active',
        'sort_order',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'day_of_week' => 'integer',
            'visit_date' => 'date',
            'start_date' => 'date',
            'end_date' => 'date',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function sales(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sales_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * ISO day_of_week: 1=Mon, 7=Sun.
     */
    public function isApplicableOn(Carbon $date): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->pattern === self::PATTERN_ONE_TIME) {
            return $this->visit_date !== null
                && $this->visit_date->isSameDay($date);
        }

        // Recurring
        if ((int) $date->dayOfWeekIso !== (int) $this->day_of_week) {
            return false;
        }
        if ($this->start_date !== null && $this->start_date->gt($date)) {
            return false;
        }
        if ($this->end_date !== null && $this->end_date->lt($date)) {
            return false;
        }

        return true;
    }

    public function scopeRecurring(Builder $query): Builder
    {
        return $query->where('pattern', self::PATTERN_RECURRING);
    }

    public function scopeOneTime(Builder $query): Builder
    {
        return $query->where('pattern', self::PATTERN_ONE_TIME);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeTodayApplicable(Builder $query, ?Carbon $date = null): Builder
    {
        $date ??= now();
        $dateStr = $date->toDateString();
        $iso = (int) $date->dayOfWeekIso;

        return $query->where('is_active', true)->where(function ($q) use ($iso, $dateStr): void {
            $q->where(function ($qr) use ($iso, $dateStr): void {
                $qr->where('pattern', self::PATTERN_RECURRING)
                    ->where('day_of_week', $iso)
                    ->where(function ($qs) use ($dateStr): void {
                        $qs->whereNull('start_date')->orWhereDate('start_date', '<=', $dateStr);
                    })
                    ->where(function ($qe) use ($dateStr): void {
                        $qe->whereNull('end_date')->orWhereDate('end_date', '>=', $dateStr);
                    });
            })->orWhere(function ($qo) use ($dateStr): void {
                $qo->where('pattern', self::PATTERN_ONE_TIME)
                    ->whereDate('visit_date', $dateStr);
            });
        });
    }

    public function scopeForSales(Builder $query, int $salesId): Builder
    {
        return $query->where('sales_id', $salesId);
    }
}
