<?php

namespace Database\Seeders;

use App\Models\Menu;
use App\Models\Role;
use App\Models\RoleMenu;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Superadmin: skip seeding (bypass in code).

        $this->seedRole(Role::CODE_ADMIN, $this->adminMatrix());
        $this->seedRole(Role::CODE_KASIR, $this->kasirMatrix());
        $this->seedRole(Role::CODE_OPERATOR, $this->operatorMatrix());
        $this->seedRole(Role::CODE_SALES, $this->salesMatrix());

        // Every role gets dashboard view.
        foreach ([Role::CODE_ADMIN, Role::CODE_KASIR, Role::CODE_OPERATOR, Role::CODE_SALES] as $code) {
            $this->seedRole($code, ['dashboard' => ['view' => true]]);
        }
    }

    /**
     * @param  array<string, array<string, bool>>  $matrix  menuCode => [action => bool]
     */
    private function seedRole(string $roleCode, array $matrix): void
    {
        $role = Role::query()->where('code', $roleCode)->first();
        if (! $role) {
            return;
        }

        foreach ($matrix as $menuCode => $actions) {
            $menu = Menu::query()->where('code', $menuCode)->first();
            if (! $menu) {
                continue;
            }

            RoleMenu::updateOrCreate(
                ['role_id' => $role->id, 'menu_id' => $menu->id],
                $this->normalizeActions($actions),
            );
        }
    }

    /**
     * @param  array<string, bool>  $actions
     * @return array<string, bool>
     */
    private function normalizeActions(array $actions): array
    {
        return [
            'can_view' => $actions['view'] ?? false,
            'can_create' => $actions['create'] ?? false,
            'can_update' => $actions['update'] ?? false,
            'can_delete' => $actions['delete'] ?? false,
            'can_approve' => $actions['approve'] ?? false,
            'can_export' => $actions['export'] ?? false,
        ];
    }

    /**
     * @return array<string, array<string, bool>>
     */
    private function adminMatrix(): array
    {
        return [
            'master.user' => ['view' => true, 'create' => true, 'update' => true, 'export' => true],
            'master.supplier' => ['view' => true, 'create' => true, 'update' => true, 'export' => true],
            'master.customer' => ['view' => true, 'create' => true, 'update' => true, 'export' => true],
            'master.product' => ['view' => true, 'create' => true, 'update' => true, 'export' => true],
            'master.product_group' => ['view' => true, 'create' => true, 'update' => true],
            'master.price_tier' => ['view' => true, 'create' => true, 'update' => true],
            'master.driver' => ['view' => true, 'create' => true, 'update' => true],
            'master.vehicle' => ['view' => true, 'create' => true, 'update' => true],
            'purchasing.po' => ['view' => true, 'approve' => true, 'export' => true],
            'purchasing.grn' => ['view' => true, 'approve' => true, 'export' => true],
            'inventory.stock' => ['view' => true, 'export' => true],
            'inventory.ledger' => ['view' => true, 'export' => true],
            'inventory.adjustment' => ['view' => true, 'approve' => true],
            'inventory.opname' => ['view' => true, 'approve' => true],
            'sales.schedule' => ['view' => true, 'create' => true, 'update' => true, 'delete' => true],
            'sales.visit' => ['view' => true, 'export' => true],
            'sales.so' => ['view' => true, 'update' => true, 'approve' => true, 'export' => true],
            'sales.do' => ['view' => true, 'export' => true],
            'sales.invoice' => ['view' => true, 'export' => true],
            'finance.payment_request' => ['view' => true],
            'finance.payment' => ['view' => true, 'export' => true],
            'finance.extension' => ['view' => true, 'approve' => true],
            'returns.customer' => ['view' => true, 'approve' => true],
            'returns.supplier' => ['view' => true, 'create' => true, 'update' => true, 'approve' => true],
            'returns.credit_note' => ['view' => true],
            'reports.sales' => ['view' => true, 'export' => true],
            'reports.stock' => ['view' => true, 'export' => true],
            'reports.ar_aging' => ['view' => true, 'export' => true],
            'reports.margin' => ['view' => true, 'export' => true],
            'reports.sales_activity' => ['view' => true, 'export' => true],
            'settings.company' => ['view' => true, 'update' => true],
            'settings.bank' => ['view' => true, 'create' => true, 'update' => true, 'delete' => true],
            'settings.sales' => ['view' => true, 'update' => true],
            'settings.notif' => ['view' => true, 'update' => true],
            'settings.inventory' => ['view' => true, 'update' => true],
            'audit.activity' => ['view' => true, 'export' => true],
            'audit.login_history' => ['view' => true, 'export' => true],
        ];
    }

    /**
     * @return array<string, array<string, bool>>
     */
    private function kasirMatrix(): array
    {
        return [
            'sales.invoice' => ['view' => true],
            'finance.payment_request' => ['view' => true, 'update' => true, 'approve' => true, 'export' => true],
            'finance.payment' => ['view' => true, 'create' => true, 'update' => true, 'export' => true],
            'reports.ar_aging' => ['view' => true, 'export' => true],
        ];
    }

    /**
     * @return array<string, array<string, bool>>
     */
    private function operatorMatrix(): array
    {
        return [
            'master.product' => ['view' => true],
            'purchasing.grn' => ['view' => true, 'create' => true, 'update' => true],
            'inventory.stock' => ['view' => true],
            'inventory.ledger' => ['view' => true],
            'inventory.adjustment' => ['view' => true, 'create' => true, 'update' => true],
            'inventory.opname' => ['view' => true, 'create' => true, 'update' => true],
            'sales.so' => ['view' => true],
            'sales.do' => ['view' => true, 'create' => true, 'update' => true],
            'returns.customer' => ['view' => true, 'create' => true, 'update' => true],
            'returns.supplier' => ['view' => true],
        ];
    }

    /**
     * @return array<string, array<string, bool>>
     */
    private function salesMatrix(): array
    {
        return [
            'master.customer' => ['view' => true],
            'master.product' => ['view' => true],
            'sales.schedule' => ['view' => true],
            'sales.visit' => ['view' => true, 'create' => true],
            'sales.so' => ['view' => true, 'create' => true, 'update' => true],
            'sales.invoice' => ['view' => true],
            'finance.payment_request' => ['view' => true, 'create' => true],
            'finance.extension' => ['view' => true, 'create' => true],
            'returns.customer' => ['view' => true, 'create' => true],
        ];
    }
}
