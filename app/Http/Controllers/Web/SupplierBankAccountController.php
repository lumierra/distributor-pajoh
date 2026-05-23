<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Supplier\StoreBankAccountRequest;
use App\Http\Requests\Supplier\UpdateBankAccountRequest;
use App\Models\Supplier;
use App\Models\SupplierBankAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class SupplierBankAccountController extends Controller
{
    public function store(StoreBankAccountRequest $request, Supplier $supplier): RedirectResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($supplier, $data): void {
            if (! empty($data['is_default'])) {
                $supplier->bankAccounts()->where('is_default', true)->update(['is_default' => false]);
            }

            $supplier->bankAccounts()->create($data);
        });

        return back()->with('flash.success', 'Rekening bank disimpan.');
    }

    public function update(UpdateBankAccountRequest $request, SupplierBankAccount $bankAccount): RedirectResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($bankAccount, $data): void {
            if (! empty($data['is_default'])) {
                $bankAccount->supplier
                    ->bankAccounts()
                    ->where('id', '!=', $bankAccount->id)
                    ->where('is_default', true)
                    ->update(['is_default' => false]);
            }

            $bankAccount->update($data);
        });

        return back()->with('flash.success', 'Rekening bank diperbarui.');
    }

    public function destroy(SupplierBankAccount $bankAccount): RedirectResponse
    {
        $this->authorize('update', $bankAccount->supplier);

        DB::transaction(function () use ($bankAccount): void {
            $wasDefault = $bankAccount->is_default;
            $supplierId = $bankAccount->supplier_id;

            $bankAccount->delete();

            // Promote bank aktif tertua jadi default kalau yang dihapus adalah default
            if ($wasDefault) {
                $next = SupplierBankAccount::query()
                    ->where('supplier_id', $supplierId)
                    ->where('is_active', true)
                    ->orderBy('created_at')
                    ->first();
                $next?->update(['is_default' => true]);
            }
        });

        return back()->with('flash.success', 'Rekening bank dihapus.');
    }

    public function setDefault(SupplierBankAccount $bankAccount): RedirectResponse
    {
        $this->authorize('update', $bankAccount->supplier);

        DB::transaction(function () use ($bankAccount): void {
            $bankAccount->supplier
                ->bankAccounts()
                ->where('id', '!=', $bankAccount->id)
                ->update(['is_default' => false]);
            $bankAccount->update(['is_default' => true]);
        });

        return back()->with('flash.success', 'Rekening default diperbarui.');
    }
}
