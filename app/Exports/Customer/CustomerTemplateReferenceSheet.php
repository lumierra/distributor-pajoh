<?php

namespace App\Exports\Customer;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class CustomerTemplateReferenceSheet implements FromArray, WithHeadings, WithTitle
{
    /**
     * @param  array<int, array{code: string, name: string}>  $customerTypes
     * @param  array<int, array{code: string, name: string}>  $priceTiers
     * @param  array<int, array{username: string, name: string}>  $salesUsers
     */
    public function __construct(
        private readonly array $customerTypes,
        private readonly array $priceTiers,
        private readonly array $salesUsers,
    ) {}

    public function title(): string
    {
        return 'Reference';
    }

    public function headings(): array
    {
        return ['section', 'code', 'name'];
    }

    public function array(): array
    {
        $rows = [];

        foreach ($this->customerTypes as $t) {
            $rows[] = ['customer_type_code', $t['code'], $t['name']];
        }
        foreach ($this->priceTiers as $t) {
            $rows[] = ['price_tier_code', $t['code'], $t['name']];
        }
        foreach ($this->salesUsers as $u) {
            $rows[] = ['assigned_sales_username', $u['username'], $u['name']];
        }

        return $rows;
    }
}
