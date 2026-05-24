<?php

namespace App\Services\Sales;

use App\Models\SalesSchedule;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class SalesScheduleResolver
{
    /**
     * Resolve schedule yang applicable untuk sales pada tanggal tertentu.
     *
     * @return Collection<int, SalesSchedule>
     */
    public function todayFor(User $sales, ?Carbon $date = null): Collection
    {
        return SalesSchedule::query()
            ->forSales($sales->id)
            ->todayApplicable($date)
            ->with('customer:id,code,name,address,phone,latitude,longitude')
            ->orderBy('sort_order')
            ->orderBy('visit_time')
            ->get();
    }
}
