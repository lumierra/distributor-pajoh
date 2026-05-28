<?php

namespace App\Exports\Customer;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class CustomerTemplateSheet implements FromArray, WithHeadings, WithTitle
{
    public function title(): string
    {
        return 'Customers';
    }

    public function headings(): array
    {
        return [
            'name',                    // wajib
            'owner_name',
            'customer_type_code',      // opsional, lihat sheet Reference
            'whatsapp',
            'area',
            'assigned_sales_username', // opsional, lihat sheet Reference
            'credit_limit',
            'payment_term_days',
            'is_active',               // 1 / 0, default 1
            'notes',
        ];
    }

    public function array(): array
    {
        // 2 baris contoh: lengkap & minimal
        return [
            [
                'Toko Berkah Jaya',
                'Pak Ahmad',
                'GROSIR',
                '08123456789',
                'Langsa Kota',
                'sales1',
                5_000_000,
                14,
                1,
                'Pelanggan baru, referensi dari Pak Budi',
            ],
            [
                'Warung Sederhana',
                '',
                '',
                '',
                '',
                '',
                '',
                14,
                1,
                '',
            ],
        ];
    }
}
