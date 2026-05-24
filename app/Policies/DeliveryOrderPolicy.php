<?php

namespace App\Policies;

use App\Models\DeliveryOrder;
use App\Models\User;

class DeliveryOrderPolicy
{
    private const MENU = 'sales.do';

    public function before(User $user, string $ability): ?bool
    {
        return $user->isSuperadmin() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->canView(self::MENU);
    }

    public function view(User $user, DeliveryOrder $do): bool
    {
        if (! $user->canView(self::MENU)) {
            return false;
        }

        // Driver (asal punya akses) hanya lihat DO miliknya (kalau ada role driver
        // di masa depan). Untuk MVP: semua role yang punya view bisa lihat.
        return true;
    }

    public function create(User $user): bool
    {
        return $user->canCreate(self::MENU);
    }

    public function update(User $user, DeliveryOrder $do): bool
    {
        return $do->canBeEdited() && $user->canUpdate(self::MENU);
    }

    public function delete(User $user, DeliveryOrder $do): bool
    {
        return false;
    }

    public function startPicking(User $user, DeliveryOrder $do): bool
    {
        return $do->canStartPicking() && $user->canUpdate(self::MENU);
    }

    public function confirmPick(User $user, DeliveryOrder $do): bool
    {
        return $do->status === DeliveryOrder::STATUS_PICKING && $user->canUpdate(self::MENU);
    }

    public function markPacked(User $user, DeliveryOrder $do): bool
    {
        return $do->canMarkPacked() && $user->canUpdate(self::MENU);
    }

    public function startDelivery(User $user, DeliveryOrder $do): bool
    {
        // Driver/Sales/Admin yang in-charge. Untuk MVP: canUpdate cukup.
        return $do->canStartDelivery() && $user->canUpdate(self::MENU);
    }

    public function markDelivered(User $user, DeliveryOrder $do): bool
    {
        return $do->canMarkDelivered() && $user->canUpdate(self::MENU);
    }

    public function cancel(User $user, DeliveryOrder $do): bool
    {
        return $do->canBeCancelled() && $user->canUpdate(self::MENU);
    }
}
