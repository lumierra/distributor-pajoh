<?php

namespace Database\Seeders;

use App\Models\Setting;
use App\Services\Setting\SettingManager;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    public function run(): void
    {
        $order = 0;

        foreach ($this->definitions() as $row) {
            $row['sort_order'] = $order++;
            $row['default_value'] = $row['default_value'] ?? $row['value'] ?? null;

            Setting::updateOrCreate(
                ['group' => $row['group'], 'key' => $row['key']],
                $row,
            );
        }

        app(SettingManager::class)->forgetCache();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function definitions(): array
    {
        return [
            // ── COMPANY ────────────────────────────────────────────────
            ['group' => 'company', 'key' => 'name', 'type' => 'string',
                'label' => 'Nama CV', 'value' => 'CV Ananda Berkah Sejahtera - LGS'],
            ['group' => 'company', 'key' => 'legal_form', 'type' => 'string',
                'label' => 'Bentuk Badan Usaha', 'value' => 'CV'],
            ['group' => 'company', 'key' => 'npwp', 'type' => 'string',
                'label' => 'NPWP', 'value' => ''],
            ['group' => 'company', 'key' => 'nib', 'type' => 'string',
                'label' => 'NIB / No Izin Usaha', 'value' => ''],
            ['group' => 'company', 'key' => 'address', 'type' => 'text',
                'label' => 'Alamat Lengkap', 'value' => ''],
            ['group' => 'company', 'key' => 'city', 'type' => 'string',
                'label' => 'Kota', 'value' => 'Langsa'],
            ['group' => 'company', 'key' => 'province', 'type' => 'string',
                'label' => 'Provinsi', 'value' => 'Aceh'],
            ['group' => 'company', 'key' => 'postal_code', 'type' => 'string',
                'label' => 'Kode Pos', 'value' => ''],
            ['group' => 'company', 'key' => 'phone', 'type' => 'string',
                'label' => 'Telepon', 'value' => ''],
            ['group' => 'company', 'key' => 'whatsapp', 'type' => 'string',
                'label' => 'WhatsApp', 'value' => ''],
            ['group' => 'company', 'key' => 'email', 'type' => 'string',
                'label' => 'Email', 'value' => ''],
            ['group' => 'company', 'key' => 'website', 'type' => 'string',
                'label' => 'Website', 'value' => ''],

            // ── COMPANY ASSETS ─────────────────────────────────────────
            ['group' => 'company.assets', 'key' => 'logo_path', 'type' => 'file',
                'label' => 'Logo (header app & faktur)', 'value' => 'images/logo.png'],
            ['group' => 'company.assets', 'key' => 'signature_path', 'type' => 'file',
                'label' => 'Tanda Tangan Owner', 'value' => '', 'is_sensitive' => true],
            ['group' => 'company.assets', 'key' => 'stamp_path', 'type' => 'file',
                'label' => 'Stempel CV', 'value' => '', 'is_sensitive' => true],
            ['group' => 'company.assets', 'key' => 'show_signature_on_invoice', 'type' => 'bool',
                'label' => 'Tampilkan TTD di Faktur', 'value' => true],
            ['group' => 'company.assets', 'key' => 'show_stamp_on_invoice', 'type' => 'bool',
                'label' => 'Tampilkan Stempel di Faktur', 'value' => true],

            // ── COMPANY INVOICE TEXT ───────────────────────────────────
            ['group' => 'company.invoice_text', 'key' => 'header_text', 'type' => 'text',
                'label' => 'Header Tambahan Faktur', 'value' => ''],
            ['group' => 'company.invoice_text', 'key' => 'footer_text', 'type' => 'text',
                'label' => 'Footer Faktur',
                'value' => 'Barang yang sudah dibeli tidak dapat dikembalikan kecuali atas perjanjian.'],
            ['group' => 'company.invoice_text', 'key' => 'terms_and_conditions', 'type' => 'text',
                'label' => 'Syarat & Ketentuan', 'value' => ''],
            ['group' => 'company.invoice_text', 'key' => 'payment_instruction', 'type' => 'text',
                'label' => 'Instruksi Pembayaran',
                'value' => 'Pembayaran transfer ke rekening yang tertera di bawah:'],

            // ── NUMBERING ─────────────────────────────────────────────
            ...$this->numberingDefinitions(),

            // ── SALES (mobile device + geofence) ───────────────────────
            ['group' => 'sales', 'key' => 'geofence.enabled', 'type' => 'bool',
                'label' => 'Aktifkan Geofence Check-in', 'value' => true],
            ['group' => 'sales', 'key' => 'geofence.radius_meter', 'type' => 'int',
                'label' => 'Radius Geofence (meter)', 'value' => 100,
                'validation' => 'required|integer|min:30|max:5000'],
            ['group' => 'sales', 'key' => 'geofence.allow_admin_bypass', 'type' => 'bool',
                'label' => 'Boleh Admin Grant Bypass per Visit', 'value' => true],
            ['group' => 'sales', 'key' => 'device.binding_enabled', 'type' => 'bool',
                'label' => 'Aktifkan Device Binding (MAC + UUID)', 'value' => true],
            ['group' => 'sales', 'key' => 'device.auto_register_first', 'type' => 'bool',
                'label' => 'Auto Register Device Pertama Login', 'value' => true],
            ['group' => 'sales', 'key' => 'device.max_per_user', 'type' => 'int',
                'label' => 'Max Device Aktif per Sales', 'value' => 1,
                'validation' => 'required|integer|min:1|max:5'],
            ['group' => 'sales', 'key' => 'fake_gps.check_enabled', 'type' => 'bool',
                'label' => 'Deteksi Mock Location', 'value' => true],
            ['group' => 'sales', 'key' => 'fake_gps.allow_in_dev_mode', 'type' => 'bool',
                'label' => 'Izinkan Mock Location di Mode Dev', 'value' => false, 'is_sensitive' => true],

            // ── NOTIFICATION WA ───────────────────────────────────────
            ['group' => 'notification.wa', 'key' => 'enabled', 'type' => 'bool',
                'label' => 'Aktifkan Notifikasi WhatsApp (master)', 'value' => true],
            ['group' => 'notification.wa', 'key' => 'gateway_provider', 'type' => 'string',
                'label' => 'Provider Gateway', 'value' => 'pos_pajoh', 'is_sensitive' => true],
            ['group' => 'notification.wa', 'key' => 'gateway_endpoint', 'type' => 'string',
                'label' => 'Endpoint WA Gateway', 'value' => '', 'is_sensitive' => true],
            ['group' => 'notification.wa', 'key' => 'gateway_token', 'type' => 'string',
                'label' => 'Token Autentikasi Gateway', 'value' => '', 'is_sensitive' => true],
            ['group' => 'notification.wa', 'key' => 'gateway_sender_id', 'type' => 'string',
                'label' => 'Sender ID', 'value' => '', 'is_sensitive' => true],
            ['group' => 'notification.wa', 'key' => 'rate_limit_per_day', 'type' => 'int',
                'label' => 'Max Notif per Customer per Hari', 'value' => 5],
            ['group' => 'notification.wa', 'key' => 'dedup_window_hours', 'type' => 'int',
                'label' => 'Dedup Window (jam)', 'value' => 24],
            ['group' => 'notification.wa', 'key' => 'retry_max', 'type' => 'int',
                'label' => 'Max Retry on Failure', 'value' => 3],

            // Notif toggles per category
            ['group' => 'notification.wa', 'key' => 'so_approved.to_customer', 'type' => 'bool',
                'label' => 'SO approved → Customer', 'value' => true],
            ['group' => 'notification.wa', 'key' => 'so_rejected.to_sales', 'type' => 'bool',
                'label' => 'SO rejected → Sales', 'value' => true],
            ['group' => 'notification.wa', 'key' => 'invoice.to_customer', 'type' => 'bool',
                'label' => 'Invoice generated → Customer', 'value' => true],
            ['group' => 'notification.wa', 'key' => 'delivery.to_operator', 'type' => 'bool',
                'label' => 'DO ready picking → Operator', 'value' => true],
            ['group' => 'notification.wa', 'key' => 'delivery.to_customer', 'type' => 'bool',
                'label' => 'DO in-transit → Customer', 'value' => true],
            ['group' => 'notification.wa', 'key' => 'delivered.to_customer', 'type' => 'bool',
                'label' => 'DO delivered → Customer', 'value' => true],
            ['group' => 'notification.wa', 'key' => 'payment_request.to_kasir', 'type' => 'bool',
                'label' => 'Payment Request submitted → Kasir', 'value' => true],
            ['group' => 'notification.wa', 'key' => 'payment.verified.to_sales', 'type' => 'bool',
                'label' => 'Payment verified → Sales', 'value' => true],
            ['group' => 'notification.wa', 'key' => 'payment.rejected.to_sales', 'type' => 'bool',
                'label' => 'Payment rejected → Sales', 'value' => true],
            ['group' => 'notification.wa', 'key' => 'payment.due_date_reminder', 'type' => 'bool',
                'label' => 'Payment Due Reminder (H-N)', 'value' => true],
            ['group' => 'notification.wa', 'key' => 'payment.due_date_days_before', 'type' => 'int',
                'label' => 'H-N untuk reminder', 'value' => 3],
            ['group' => 'notification.wa', 'key' => 'giro.due_today.to_kasir', 'type' => 'bool',
                'label' => 'Giro due today → Kasir', 'value' => true],
            ['group' => 'notification.wa', 'key' => 'giro.cleared.to_sales', 'type' => 'bool',
                'label' => 'Giro cleared → Sales', 'value' => true],
            ['group' => 'notification.wa', 'key' => 'giro.bounced.to_sales', 'type' => 'bool',
                'label' => 'Giro bounced → Sales', 'value' => true],
            ['group' => 'notification.wa', 'key' => 'extension.requested.to_admin', 'type' => 'bool',
                'label' => 'Extension request → Admin', 'value' => true],
            ['group' => 'notification.wa', 'key' => 'extension.approved.to_customer', 'type' => 'bool',
                'label' => 'Extension approved → Customer', 'value' => true],
            ['group' => 'notification.wa', 'key' => 'extension.approved.to_sales', 'type' => 'bool',
                'label' => 'Extension approved → Sales', 'value' => true],
            ['group' => 'notification.wa', 'key' => 'extension.rejected.to_sales', 'type' => 'bool',
                'label' => 'Extension rejected → Sales', 'value' => true],
            ['group' => 'notification.wa', 'key' => 'approval.so_over_credit.to_superadmin', 'type' => 'bool',
                'label' => 'SO over credit → Superadmin', 'value' => true],
            ['group' => 'notification.wa', 'key' => 'approval.grn_ready.to_admin', 'type' => 'bool',
                'label' => 'GRN ready verify → Admin', 'value' => true],
            ['group' => 'notification.wa', 'key' => 'adjustment_pending.to_admin', 'type' => 'bool',
                'label' => 'Adjustment pending → Admin', 'value' => true],
            ['group' => 'notification.wa', 'key' => 'document_expiry.enabled', 'type' => 'bool',
                'label' => 'Document Expiry Alert', 'value' => true],
            ['group' => 'notification.wa', 'key' => 'vehicle_service.enabled', 'type' => 'bool',
                'label' => 'Vehicle Service Due Alert', 'value' => true],
            ['group' => 'notification.wa', 'key' => 'expired_alert.enabled', 'type' => 'bool',
                'label' => 'Expired Batch Alert', 'value' => true],
            ['group' => 'notification.wa', 'key' => 'device_request.enabled', 'type' => 'bool',
                'label' => 'Device Request → Admin', 'value' => true],
            ['group' => 'notification.wa', 'key' => 'bypass_request.enabled', 'type' => 'bool',
                'label' => 'Bypass Request → Admin', 'value' => true],
            ['group' => 'notification.wa', 'key' => 'sales_no_visit.enabled', 'type' => 'bool',
                'label' => 'Sales No Visit Alert', 'value' => true],
            ['group' => 'notification.wa', 'key' => 'return_pending.to_operator', 'type' => 'bool',
                'label' => 'Customer Return Pending → Operator', 'value' => true],
            ['group' => 'notification.wa', 'key' => 'cn_applied.to_customer', 'type' => 'bool',
                'label' => 'Credit Note Applied → Customer', 'value' => true],
            ['group' => 'notification.wa', 'key' => 'customer_reassigned.to_sales', 'type' => 'bool',
                'label' => 'Customer Reassigned → Sales', 'value' => true],
            ['group' => 'notification.wa', 'key' => 'device_approved.to_sales', 'type' => 'bool',
                'label' => 'Device Approved → Sales', 'value' => true],
            ['group' => 'notification.wa', 'key' => 'overpayment.to_admin', 'type' => 'bool',
                'label' => 'Overpayment Alert → Admin', 'value' => true],

            // ── INVENTORY ────────────────────────────────────────────
            ['group' => 'inventory', 'key' => 'allow_negative_stock', 'type' => 'bool',
                'label' => 'Izinkan Negative Stock (backorder)', 'value' => false],
            ['group' => 'inventory', 'key' => 'reorder_point.default', 'type' => 'int',
                'label' => 'Reorder Point Default', 'value' => 0],
            ['group' => 'inventory', 'key' => 'expired_alert.enabled', 'type' => 'bool',
                'label' => 'Aktifkan Alert Expired', 'value' => true],
            ['group' => 'inventory', 'key' => 'expired_alert.days_h30', 'type' => 'int',
                'label' => 'Alert Kuning H-N (hari)', 'value' => 30],
            ['group' => 'inventory', 'key' => 'expired_alert.days_h60', 'type' => 'int',
                'label' => 'Alert Oranye H-N', 'value' => 60],
            ['group' => 'inventory', 'key' => 'expired_alert.days_h90', 'type' => 'int',
                'label' => 'Alert Merah H-N', 'value' => 90],
            ['group' => 'inventory', 'key' => 'fifo.bonus_pool_first', 'type' => 'bool',
                'label' => 'FIFO Pool Bonus dulu', 'value' => true],

            // ── VEHICLE ──────────────────────────────────────────────
            ['group' => 'vehicle', 'key' => 'block_expired_doc.enabled', 'type' => 'bool',
                'label' => 'Block Assign Vehicle/Driver dengan Doc Expired', 'value' => true],

            // ── CUSTOMER DEFAULTS ────────────────────────────────────
            ['group' => 'customer', 'key' => 'credit_limit.default', 'type' => 'int',
                'label' => 'Default Credit Limit', 'value' => 0],
            ['group' => 'customer', 'key' => 'payment_term_days.default', 'type' => 'int',
                'label' => 'Default Payment Term (hari)', 'value' => 7],
            ['group' => 'customer', 'key' => 'price_tier.default_id', 'type' => 'int',
                'label' => 'Default Price Tier ID', 'value' => null],

            // ── SYSTEM ───────────────────────────────────────────────
            ['group' => 'system', 'key' => 'timezone', 'type' => 'string',
                'label' => 'Timezone', 'value' => 'Asia/Jakarta', 'is_readonly' => true],
            ['group' => 'system', 'key' => 'locale', 'type' => 'string',
                'label' => 'Locale', 'value' => 'id_ID', 'is_readonly' => true],
            ['group' => 'system', 'key' => 'currency', 'type' => 'string',
                'label' => 'Mata Uang', 'value' => 'IDR', 'is_readonly' => true],
            ['group' => 'system', 'key' => 'date_format', 'type' => 'string',
                'label' => 'Format Tanggal', 'value' => 'd/m/Y', 'is_readonly' => true],
            ['group' => 'system', 'key' => 'pagination_default', 'type' => 'int',
                'label' => 'Default Baris per Halaman', 'value' => 25],
            ['group' => 'system', 'key' => 'backup.enabled', 'type' => 'bool',
                'label' => 'Backup Database Harian', 'value' => true],
            ['group' => 'system', 'key' => 'backup.retention_days', 'type' => 'int',
                'label' => 'Retensi Backup (hari)', 'value' => 30],
            ['group' => 'system', 'key' => 'backup.time', 'type' => 'string',
                'label' => 'Jam Jalankan Backup (HH:mm)', 'value' => '02:00'],
            ['group' => 'system', 'key' => 'backup.destination', 'type' => 'string',
                'label' => 'Tujuan Backup', 'value' => 'local', 'is_sensitive' => true,
                'options' => ['local', 's3']],

            // ── AUDIT ────────────────────────────────────────────────
            ['group' => 'audit', 'key' => 'log_enabled', 'type' => 'bool',
                'label' => 'Aktifkan Activity Log', 'value' => true],
            ['group' => 'audit', 'key' => 'log_retention_days', 'type' => 'int',
                'label' => 'Retensi Activity Log (hari)', 'value' => 730],

            // ── CLOSING ──────────────────────────────────────────────
            ['group' => 'closing', 'key' => 'notif_when_completed', 'type' => 'bool',
                'label' => 'Notif WA saat Year-end Closing selesai', 'value' => true],
        ];
    }

    /**
     * Numbering settings for all document types.
     *
     * @return array<int, array<string, mixed>>
     */
    private function numberingDefinitions(): array
    {
        $docs = [
            ['key' => 'supplier_code', 'format' => 'SUP-{seq:04d}', 'reset' => 'never', 'label' => 'Format Kode Supplier'],
            ['key' => 'customer_code', 'format' => 'CUST-{seq:04d}', 'reset' => 'never', 'label' => 'Format Kode Customer'],
            ['key' => 'product_sku', 'format' => 'SKU-{cat}-{seq:04d}', 'reset' => 'never', 'label' => 'Format SKU Produk'],
            ['key' => 'driver_code', 'format' => 'DRV-{seq:04d}', 'reset' => 'never', 'label' => 'Format Kode Driver'],
            ['key' => 'vehicle_code', 'format' => 'VEH-{seq:04d}', 'reset' => 'never', 'label' => 'Format Kode Vehicle'],
            ['key' => 'po', 'format' => 'PO-{YY}{MM}-{seq:04d}', 'reset' => 'yearly', 'label' => 'Format No Purchase Order'],
            ['key' => 'grn', 'format' => 'GRN-{YY}{MM}-{seq:04d}', 'reset' => 'yearly', 'label' => 'Format No GRN'],
            ['key' => 'so', 'format' => 'SO-{YY}{MM}-{seq:04d}', 'reset' => 'yearly', 'label' => 'Format No Sales Order'],
            ['key' => 'do', 'format' => 'DO-{YY}{MM}-{seq:04d}', 'reset' => 'yearly', 'label' => 'Format No Surat Jalan'],
            ['key' => 'invoice', 'format' => 'DIS/{seq:03d}/{YYMM}{cust:04d}', 'reset' => 'yearly', 'label' => 'Format No Faktur'],
            ['key' => 'payment', 'format' => 'PAY-{YY}{MM}-{seq:04d}', 'reset' => 'yearly', 'label' => 'Format No Payment'],
            ['key' => 'customer_return', 'format' => 'RTR-C-{YY}{MM}-{seq:04d}', 'reset' => 'yearly', 'label' => 'Format No Retur Customer'],
            ['key' => 'supplier_return', 'format' => 'RTR-S-{YY}{MM}-{seq:04d}', 'reset' => 'yearly', 'label' => 'Format No Retur Supplier'],
            ['key' => 'credit_note', 'format' => 'CN-{YY}{MM}-{seq:04d}', 'reset' => 'yearly', 'label' => 'Format No Credit Note'],
            ['key' => 'adjustment', 'format' => 'ADJ-{YY}{MM}-{seq:04d}', 'reset' => 'yearly', 'label' => 'Format No Adjustment'],
            ['key' => 'opname', 'format' => 'OPN-{YY}{MM}-{seq:04d}', 'reset' => 'yearly', 'label' => 'Format No Opname'],
        ];

        $out = [];
        foreach ($docs as $doc) {
            $isMasterCode = in_array($doc['key'], ['supplier_code', 'customer_code', 'product_sku', 'driver_code', 'vehicle_code'], true);

            $out[] = [
                'group' => 'numbering',
                'key' => "{$doc['key']}.format",
                'type' => 'string',
                'label' => $doc['label'],
                'value' => $doc['format'],
                'is_sensitive' => ! $isMasterCode,
            ];
            $out[] = [
                'group' => 'numbering',
                'key' => "{$doc['key']}.reset_period",
                'type' => 'string',
                'label' => "Reset Period {$doc['label']}",
                'value' => $doc['reset'],
                'is_sensitive' => ! $isMasterCode,
                'options' => ['yearly', 'monthly', 'never'],
            ];
        }

        return $out;
    }
}
