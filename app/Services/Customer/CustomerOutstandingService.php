<?php

namespace App\Services\Customer;

use App\Models\Customer;
use Illuminate\Support\Facades\Cache;

/**
 * Stub MVP — bergantung ke `invoices` (T13) & `payments` (T14).
 * Returns 0/empty sampai modul tsb landing. Cache key `customer:{id}:outstanding`.
 */
class CustomerOutstandingService
{
    public function getTotal(Customer $customer): float
    {
        return (float) Cache::remember(
            "customer:{$customer->id}:outstanding",
            now()->addMinutes(5),
            fn () => 0.0,
        );
    }

    /**
     * @return array<string, float>
     */
    public function getAging(Customer $customer): array
    {
        return [
            '0-30' => 0.0,
            '31-60' => 0.0,
            '61-90' => 0.0,
            '90+' => 0.0,
        ];
    }

    public function isOverLimit(Customer $customer, float $additionalAmount = 0.0): bool
    {
        if ((float) $customer->credit_limit <= 0.0) {
            return false;
        }

        return ($this->getTotal($customer) + $additionalAmount) > (float) $customer->credit_limit;
    }

    public function invalidate(Customer $customer): void
    {
        Cache::forget("customer:{$customer->id}:outstanding");
    }
}
