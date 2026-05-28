<?php

namespace App\Exports\Customer;

use App\Models\CustomerType;
use App\Models\Role;
use App\Models\User;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Template Excel untuk bulk-import customer.
 *
 * Sheet 1 (Customers) — header + 2 contoh row.
 * Sheet 2 (Reference) — daftar valid kode untuk customer_type_code & username
 * sales aktif. Admin lihat sheet ini saat ngisi.
 */
class CustomerTemplateExport implements WithMultipleSheets
{
    use Exportable;

    public function sheets(): array
    {
        return [
            new CustomerTemplateSheet,
            new CustomerTemplateReferenceSheet(
                customerTypes: CustomerType::query()
                    ->where('is_active', true)
                    ->orderBy('sort_order')
                    ->get(['code', 'name'])
                    ->toArray(),
                salesUsers: User::query()
                    ->whereHas('role', fn ($q) => $q->where('code', Role::CODE_SALES))
                    ->where('is_active', true)
                    ->orderBy('name')
                    ->get(['username', 'name'])
                    ->toArray(),
            ),
        ];
    }
}
