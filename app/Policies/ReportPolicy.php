<?php

namespace App\Policies;

use App\Models\User;

class ReportPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->isSuperadmin() ? true : null;
    }

    public function viewSales(User $user): bool
    {
        return $user->canView('reports.sales');
    }

    public function viewStock(User $user): bool
    {
        return $user->canView('reports.stock');
    }

    public function viewArAging(User $user): bool
    {
        return $user->canView('reports.ar_aging');
    }

    public function viewMargin(User $user): bool
    {
        return $user->canView('reports.margin');
    }

    public function viewSalesActivity(User $user): bool
    {
        return $user->canView('reports.sales_activity');
    }

    public function export(User $user, string $reportType): bool
    {
        $menu = match ($reportType) {
            'sales_summary', 'sales' => 'reports.sales',
            'stock_position', 'stock' => 'reports.stock',
            'ar_aging' => 'reports.ar_aging',
            'margin' => 'reports.margin',
            'sales_activity' => 'reports.sales_activity',
            default => null,
        };

        if ($menu === null) {
            return false;
        }

        return $user->canExport($menu);
    }

    public function regenerate(User $user): bool
    {
        // Hanya admin/superadmin yang bisa trigger snapshot regen manual
        return $user->canApprove('reports.sales');
    }
}
