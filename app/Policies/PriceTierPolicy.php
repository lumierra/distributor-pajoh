<?php

namespace App\Policies;

use App\Models\PriceTier;
use App\Models\User;

class PriceTierPolicy
{
    private const MENU = 'master.price_tier';

    public function before(User $user, string $ability): ?bool
    {
        return $user->isSuperadmin() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->canView(self::MENU);
    }

    public function view(User $user, PriceTier $tier): bool
    {
        return $user->canView(self::MENU);
    }

    public function create(User $user): bool
    {
        return $user->canCreate(self::MENU);
    }

    public function update(User $user, PriceTier $tier): bool
    {
        // Code system tier tidak bisa diubah (handled di FormRequest)
        return $user->canUpdate(self::MENU);
    }

    public function delete(User $user, PriceTier $tier): bool
    {
        if (! $user->canDelete(self::MENU)) {
            return false;
        }
        // System tier tidak bisa dihapus
        if ($tier->is_system) {
            return false;
        }

        return $tier->prices()->count() === 0;
    }
}
