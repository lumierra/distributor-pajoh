<?php

namespace App\Services\Customer;

use App\Models\Customer;
use App\Models\Invoice;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Customer outstanding receivable (AR) aggregator.
 *
 * Total = SUM(invoices.outstanding) untuk invoice yg masih open/partial/overdue.
 * Aging = bucket outstanding berdasarkan umur sejak `due_date` (0-30/31-60/61-90/90+).
 *
 * Dipanggil di /customers/{id} tab Outstanding & di legacy global credit check.
 * Cache 5 menit per customer; auto invalidate saat invoice/payment berubah.
 */
class CustomerOutstandingService
{
    private const CACHE_TTL_MINUTES = 5;

    public function getTotal(Customer $customer): float
    {
        return (float) Cache::remember(
            $this->totalCacheKey($customer),
            now()->addMinutes(self::CACHE_TTL_MINUTES),
            fn (): float => (float) Invoice::query()
                ->where('customer_id', $customer->id)
                ->openOrPartial()
                ->sum('outstanding'),
        );
    }

    /**
     * @return array<string, float>
     */
    public function getAging(Customer $customer): array
    {
        return Cache::remember(
            $this->agingCacheKey($customer),
            now()->addMinutes(self::CACHE_TTL_MINUTES),
            fn (): array => $this->computeAging($customer),
        );
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
        Cache::forget($this->totalCacheKey($customer));
        Cache::forget($this->agingCacheKey($customer));
    }

    /**
     * @return array<string, float>
     */
    private function computeAging(Customer $customer): array
    {
        $today = Carbon::today();

        $invoices = Invoice::query()
            ->where('customer_id', $customer->id)
            ->openOrPartial()
            ->where('outstanding', '>', 0)
            ->get(['outstanding', 'due_date']);

        $buckets = ['0-30' => 0.0, '31-60' => 0.0, '61-90' => 0.0, '90+' => 0.0];

        foreach ($invoices as $invoice) {
            $outstanding = (float) $invoice->outstanding;
            if ($outstanding <= 0) {
                continue;
            }

            $dueDate = $invoice->due_date instanceof Carbon
                ? $invoice->due_date
                : Carbon::parse($invoice->due_date);

            // daysOverdue: 0 = belum lewat tempo, positif = sudah lewat tempo
            $daysOverdue = max(0, $today->diffInDays($dueDate, false) * -1);

            $bucket = match (true) {
                $daysOverdue <= 30 => '0-30',
                $daysOverdue <= 60 => '31-60',
                $daysOverdue <= 90 => '61-90',
                default => '90+',
            };

            $buckets[$bucket] += $outstanding;
        }

        return $buckets;
    }

    private function totalCacheKey(Customer $customer): string
    {
        return "customer:{$customer->id}:outstanding";
    }

    private function agingCacheKey(Customer $customer): string
    {
        return "customer:{$customer->id}:outstanding:aging";
    }
}
