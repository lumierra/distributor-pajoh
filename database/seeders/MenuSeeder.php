<?php

namespace Database\Seeders;

use App\Models\Menu;
use Illuminate\Database\Seeder;

class MenuSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->parents() as $idx => $parent) {
            Menu::updateOrCreate(
                ['code' => $parent['code']],
                array_merge($parent, [
                    'parent_id' => null,
                    'order' => $idx + 1,
                    'is_system' => true,
                    'is_active' => true,
                ]),
            );
        }

        foreach ($this->children() as $row) {
            $parent = Menu::query()->where('code', $row['parent'])->first();
            if (! $parent) {
                continue;
            }
            $payload = $row;
            unset($payload['parent']);
            Menu::updateOrCreate(
                ['code' => $payload['code']],
                array_merge($payload, [
                    'parent_id' => $parent->id,
                    'is_system' => true,
                    'is_active' => true,
                ]),
            );
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function parents(): array
    {
        return [
            ['code' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'LayoutDashboard', 'route' => 'dashboard'],
            ['code' => 'master', 'label' => 'Master Data', 'icon' => 'Database', 'route' => null],
            ['code' => 'purchasing', 'label' => 'Pembelian', 'icon' => 'ShoppingCart', 'route' => null],
            ['code' => 'inventory', 'label' => 'Inventory', 'icon' => 'Boxes', 'route' => null],
            ['code' => 'sales', 'label' => 'Penjualan', 'icon' => 'ShoppingBag', 'route' => null],
            ['code' => 'finance', 'label' => 'Keuangan', 'icon' => 'CreditCard', 'route' => null],
            ['code' => 'returns', 'label' => 'Retur', 'icon' => 'Undo2', 'route' => null],
            ['code' => 'reports', 'label' => 'Laporan', 'icon' => 'FileBarChart', 'route' => null],
            ['code' => 'settings', 'label' => 'Pengaturan', 'icon' => 'Settings', 'route' => null],
            ['code' => 'audit', 'label' => 'Aktivitas & Audit', 'icon' => 'History', 'route' => null],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function children(): array
    {
        return [
            // master
            ['parent' => 'master', 'code' => 'master.user', 'label' => 'User', 'icon' => 'User', 'route' => 'users.index', 'order' => 1],
            ['parent' => 'master', 'code' => 'master.role', 'label' => 'Role', 'icon' => 'Shield', 'route' => 'roles.index', 'order' => 2],
            ['parent' => 'master', 'code' => 'master.menu', 'label' => 'Menu (Permission)', 'icon' => 'Menu', 'route' => 'menus.index', 'order' => 3],
            ['parent' => 'master', 'code' => 'master.supplier', 'label' => 'Supplier', 'icon' => 'Factory', 'route' => 'suppliers.index', 'order' => 4],
            ['parent' => 'master', 'code' => 'master.customer', 'label' => 'Customer', 'icon' => 'Store', 'route' => 'customers.index', 'order' => 5],
            ['parent' => 'master', 'code' => 'master.product', 'label' => 'Produk', 'icon' => 'Package', 'route' => 'products.index', 'order' => 6],
            ['parent' => 'master', 'code' => 'master.product_group', 'label' => 'Product Group', 'icon' => 'Layers', 'route' => 'product-groups.index', 'order' => 7],
            ['parent' => 'master', 'code' => 'master.price_tier', 'label' => 'Price Tier', 'icon' => 'Tag', 'route' => 'price-tiers.index', 'order' => 8],
            ['parent' => 'master', 'code' => 'master.driver', 'label' => 'Driver', 'icon' => 'UserCog', 'route' => 'drivers.index', 'order' => 9],
            ['parent' => 'master', 'code' => 'master.vehicle', 'label' => 'Vehicle', 'icon' => 'Truck', 'route' => 'vehicles.index', 'order' => 10],

            // purchasing
            ['parent' => 'purchasing', 'code' => 'purchasing.po', 'label' => 'Purchase Order', 'icon' => 'FileText', 'route' => 'purchase-orders.index', 'order' => 1],
            ['parent' => 'purchasing', 'code' => 'purchasing.grn', 'label' => 'GRN (Penerimaan)', 'icon' => 'PackageOpen', 'route' => 'grns.index', 'order' => 2],

            // inventory
            ['parent' => 'inventory', 'code' => 'inventory.stock', 'label' => 'Stok per Produk', 'icon' => 'Package', 'route' => 'stocks.index', 'order' => 1],
            ['parent' => 'inventory', 'code' => 'inventory.ledger', 'label' => 'Stock Ledger', 'icon' => 'BookOpen', 'route' => 'stock-ledger.index', 'order' => 2],
            ['parent' => 'inventory', 'code' => 'inventory.adjustment', 'label' => 'Adjustment', 'icon' => 'Settings2', 'route' => 'adjustments.index', 'order' => 3],
            ['parent' => 'inventory', 'code' => 'inventory.opname', 'label' => 'Stock Opname', 'icon' => 'ClipboardList', 'route' => 'opnames.index', 'order' => 4],

            // sales
            ['parent' => 'sales', 'code' => 'sales.schedule', 'label' => 'Jadwal Kunjungan', 'icon' => 'CalendarDays', 'route' => 'sales-schedules.index', 'order' => 1],
            ['parent' => 'sales', 'code' => 'sales.visit', 'label' => 'Visit Log', 'icon' => 'MapPin', 'route' => 'visits.index', 'order' => 2],
            ['parent' => 'sales', 'code' => 'sales.so', 'label' => 'Sales Order', 'icon' => 'ShoppingBag', 'route' => 'sales-orders.index', 'order' => 3],
            ['parent' => 'sales', 'code' => 'sales.do', 'label' => 'Surat Jalan', 'icon' => 'Truck', 'route' => 'delivery-orders.index', 'order' => 4],
            ['parent' => 'sales', 'code' => 'sales.invoice', 'label' => 'Faktur', 'icon' => 'ReceiptText', 'route' => 'invoices.index', 'order' => 5],

            // finance
            ['parent' => 'finance', 'code' => 'finance.payment_request', 'label' => 'Payment Request', 'icon' => 'Inbox', 'route' => 'payment-requests.index', 'order' => 1],
            ['parent' => 'finance', 'code' => 'finance.payment', 'label' => 'Payment', 'icon' => 'Wallet', 'route' => 'payments.index', 'order' => 2],
            ['parent' => 'finance', 'code' => 'finance.extension', 'label' => 'Perpanjangan Jatuh Tempo', 'icon' => 'CalendarClock', 'route' => 'extensions.index', 'order' => 3],

            // returns
            ['parent' => 'returns', 'code' => 'returns.customer', 'label' => 'Retur Customer', 'icon' => 'Undo2', 'route' => 'customer-returns.index', 'order' => 1],
            ['parent' => 'returns', 'code' => 'returns.supplier', 'label' => 'Retur Supplier', 'icon' => 'Undo', 'route' => 'supplier-returns.index', 'order' => 2],
            ['parent' => 'returns', 'code' => 'returns.credit_note', 'label' => 'Credit Note', 'icon' => 'FileMinus', 'route' => 'credit-notes.index', 'order' => 3],

            // reports
            ['parent' => 'reports', 'code' => 'reports.sales', 'label' => 'Penjualan', 'icon' => 'TrendingUp', 'route' => 'reports.sales', 'order' => 1],
            ['parent' => 'reports', 'code' => 'reports.stock', 'label' => 'Stok', 'icon' => 'BarChart3', 'route' => 'reports.stock', 'order' => 2],
            ['parent' => 'reports', 'code' => 'reports.ar_aging', 'label' => 'AR Aging', 'icon' => 'Calendar', 'route' => 'reports.ar-aging', 'order' => 3],
            ['parent' => 'reports', 'code' => 'reports.margin', 'label' => 'Margin', 'icon' => 'LineChart', 'route' => 'reports.margin', 'order' => 4],
            ['parent' => 'reports', 'code' => 'reports.sales_activity', 'label' => 'Aktivitas Sales', 'icon' => 'Activity', 'route' => 'reports.sales-activity', 'order' => 5],

            // settings
            ['parent' => 'settings', 'code' => 'settings.company', 'label' => 'Profil Perusahaan', 'icon' => 'Building2', 'route' => 'settings.company', 'order' => 1],
            ['parent' => 'settings', 'code' => 'settings.bank', 'label' => 'Rekening Bank', 'icon' => 'Landmark', 'route' => 'settings.bank-accounts.index', 'order' => 2],
            ['parent' => 'settings', 'code' => 'settings.numbering', 'label' => 'Penomoran Dokumen', 'icon' => 'Hash', 'route' => 'settings.numbering', 'order' => 3],
            ['parent' => 'settings', 'code' => 'settings.sales', 'label' => 'Sales & Mobile', 'icon' => 'Smartphone', 'route' => 'settings.sales', 'order' => 4],
            ['parent' => 'settings', 'code' => 'settings.notif', 'label' => 'Notifikasi WhatsApp', 'icon' => 'MessageCircle', 'route' => 'settings.notifications', 'order' => 5],
            ['parent' => 'settings', 'code' => 'settings.inventory', 'label' => 'Inventory & Customer', 'icon' => 'Sliders', 'route' => 'settings.inventory', 'order' => 6],
            ['parent' => 'settings', 'code' => 'settings.system', 'label' => 'Sistem & Backup', 'icon' => 'Cog', 'route' => 'settings.system', 'order' => 7],
            ['parent' => 'settings', 'code' => 'settings.sensitive_log', 'label' => 'Log Sensitif', 'icon' => 'Lock', 'route' => 'settings.sensitive-log', 'order' => 8],

            // audit
            ['parent' => 'audit', 'code' => 'audit.activity', 'label' => 'Activity Log', 'icon' => 'History', 'route' => 'activity-logs.index', 'order' => 1],
            ['parent' => 'audit', 'code' => 'audit.login_history', 'label' => 'Login History', 'icon' => 'LogIn', 'route' => 'login-history.index', 'order' => 2],
            ['parent' => 'audit', 'code' => 'audit.trash', 'label' => 'Trash (Soft Delete)', 'icon' => 'Trash2', 'route' => 'trash.index', 'order' => 3],
        ];
    }
}
