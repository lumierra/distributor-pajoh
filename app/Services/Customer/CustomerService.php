<?php

namespace App\Services\Customer;

use App\Models\Customer;
use App\Models\User;
use App\Services\Numbering\NumberingService;
use App\Services\Setting\SettingManager;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CustomerService
{
    public function __construct(
        private readonly NumberingService $numbering,
        private readonly SettingManager $settings,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Customer
    {
        return DB::transaction(function () use ($data): Customer {
            $data['code'] = $this->numbering->next('customer_code');

            $data['credit_limit'] ??= (float) $this->settings->get('customer.credit_limit.default', 0);
            $data['payment_term_days'] ??= (int) $this->settings->get('customer.payment_term_days.default', 0);
            $data['price_tier_id'] ??= (int) $this->settings->get('customer.price_tier.default_id', 0);

            $customer = Customer::create($data);

            $this->invalidateCache();

            return $customer;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Customer $customer, array $data): Customer
    {
        $customer->update($data);
        $this->invalidateCache();

        return $customer->refresh();
    }

    public function delete(Customer $customer): void
    {
        $blockers = $this->canBeDeleted($customer);

        if ($blockers !== []) {
            throw ValidationException::withMessages(['delete' => $blockers]);
        }

        $customer->delete();
        $this->invalidateCache();
    }

    /**
     * @return array<int, string>
     */
    public function canBeDeleted(Customer $customer): array
    {
        // Stub — invoice/SO/payment checks ditambah saat T11/T13/T14 landing.
        return [];
    }

    public function reassignSales(Customer $customer, User $newSales, ?string $reason = null): void
    {
        $customer->update([
            'assigned_sales_id' => $newSales->id,
        ]);

        $this->invalidateCache();
    }

    public function toggleActive(Customer $customer): Customer
    {
        $customer->update(['is_active' => ! $customer->is_active]);
        $this->invalidateCache();

        return $customer->refresh();
    }

    private function invalidateCache(): void
    {
        Cache::forget('customers:active');
    }
}
