<?php

namespace App\Exports\Customer;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

/**
 * Excel report row-row yang gagal di-import. Kolom data asli + kolom 'errors'
 * gabungan semua message pakai ' | ' separator.
 */
class CustomerImportErrorExport implements FromArray, WithHeadings, WithTitle
{
    /**
     * @param  array<int, array{row: int, data: array<string, mixed>, errors: array<string>}>  $errors
     */
    public function __construct(
        private readonly array $errors,
    ) {}

    public function title(): string
    {
        return 'Errors';
    }

    public function headings(): array
    {
        return [
            'row',
            'name',
            'owner_name',
            'customer_type_code',
            'whatsapp',
            'area',
            'assigned_sales_username',
            'credit_limit',
            'payment_term_days',
            'is_active',
            'notes',
            'errors',
        ];
    }

    public function array(): array
    {
        $rows = [];
        foreach ($this->errors as $err) {
            $data = $err['data'];
            $rows[] = [
                $err['row'],
                $data['name'] ?? '',
                $data['owner_name'] ?? '',
                $data['customer_type_code'] ?? '',
                $data['whatsapp'] ?? '',
                $data['area'] ?? '',
                $data['assigned_sales_username'] ?? '',
                $data['credit_limit'] ?? '',
                $data['payment_term_days'] ?? '',
                $data['is_active'] ?? '',
                $data['notes'] ?? '',
                implode(' | ', $err['errors']),
            ];
        }

        return $rows;
    }
}
