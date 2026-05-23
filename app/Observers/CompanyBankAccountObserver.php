<?php

namespace App\Observers;

use App\Models\CompanyBankAccount;
use Illuminate\Support\Facades\Cache;

class CompanyBankAccountObserver
{
    public function saved(CompanyBankAccount $bankAccount): void
    {
        Cache::forget('company_bank_accounts:active');
    }

    public function deleted(CompanyBankAccount $bankAccount): void
    {
        Cache::forget('company_bank_accounts:active');
    }

    public function restored(CompanyBankAccount $bankAccount): void
    {
        Cache::forget('company_bank_accounts:active');
    }
}
