<?php

namespace App\Policies;

use App\Models\SalesSchedule;
use App\Models\User;

class SalesSchedulePolicy
{
    private const MENU = 'sales.schedule';

    public function before(User $user, string $ability): ?bool
    {
        return $user->isSuperadmin() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->canView(self::MENU);
    }

    public function view(User $user, SalesSchedule $schedule): bool
    {
        if (! $user->canView(self::MENU)) {
            return false;
        }
        if ($user->hasRole('sales')) {
            return $schedule->sales_id === $user->id;
        }

        return true;
    }

    public function create(User $user): bool
    {
        return $user->canCreate(self::MENU);
    }

    public function update(User $user, SalesSchedule $schedule): bool
    {
        return $user->canUpdate(self::MENU);
    }

    public function delete(User $user, SalesSchedule $schedule): bool
    {
        return $user->canDelete(self::MENU);
    }
}
