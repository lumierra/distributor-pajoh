<?php

namespace App\Jobs\Sales;

use App\Models\Role;
use App\Models\SalesVisit;
use App\Models\User;
use App\Services\Setting\SettingManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable as FoundationQueueable;

/**
 * Alert sales yang hari ini tidak ada visit sama sekali.
 * Returns count of sales tanpa visit hari ini.
 * Notif WA dispatch ke admin di-handle terpisah (kalau setting on).
 */
class NoVisitAlertJob implements ShouldQueue
{
    use FoundationQueueable, Queueable;

    public function handle(SettingManager $settings): int
    {
        if (! (bool) $settings->get('notification.wa.sales_no_visit.enabled', false)) {
            return 0;
        }

        $salesUsers = User::query()
            ->whereHas('role', fn ($q) => $q->where('code', Role::CODE_SALES))
            ->where('is_active', true)
            ->pluck('id');

        $withVisitToday = SalesVisit::query()
            ->whereDate('checked_in_at', today())
            ->whereNotIn('status', [SalesVisit::STATUS_CANCELLED])
            ->pluck('sales_id')
            ->unique();

        return $salesUsers->diff($withVisitToday)->count();
    }
}
