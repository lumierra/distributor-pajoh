<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\CompanyBankAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * Rekening Bank Perusahaan — CRUD rekening yang bisa ditampilkan di faktur.
 * Otorisasi via menu `settings.bank` (superadmin bypass).
 */
class CompanyBankAccountController extends Controller
{
    private const MENU = 'settings.bank';

    public function index(Request $request): InertiaResponse
    {
        $this->authorizeView($request);

        return Inertia::render('Settings/BankAccounts', [
            'accounts' => CompanyBankAccount::query()
                ->orderBy('sort_order')
                ->orderBy('bank_name')
                ->get(),
            'can' => ['manage' => $this->canManage($request)],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeManage($request);

        $data = $this->validated($request);
        CompanyBankAccount::create($data);

        return back()->with('flash.success', 'Rekening bank disimpan.');
    }

    public function update(Request $request, CompanyBankAccount $bankAccount): RedirectResponse
    {
        $this->authorizeManage($request);

        $data = $this->validated($request);
        $bankAccount->update($data);

        return back()->with('flash.success', 'Rekening bank diperbarui.');
    }

    public function destroy(Request $request, CompanyBankAccount $bankAccount): RedirectResponse
    {
        $this->authorizeManage($request);

        $bankAccount->delete();

        return back()->with('flash.success', 'Rekening bank dihapus.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'bank_name' => ['required', 'string', 'max:128'],
            'bank_code' => ['nullable', 'string', 'max:16'],
            'account_number' => ['required', 'string', 'max:64'],
            'account_holder' => ['required', 'string', 'max:191'],
            'branch' => ['nullable', 'string', 'max:128'],
            'is_active' => ['boolean'],
            'show_on_invoice' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ]);
    }

    private function authorizeView(Request $request): void
    {
        $user = $request->user();
        if ($user === null || (! $user->isSuperadmin() && ! $user->canView(self::MENU))) {
            abort(403);
        }
    }

    private function authorizeManage(Request $request): void
    {
        if (! $this->canManage($request)) {
            abort(403);
        }
    }

    private function canManage(Request $request): bool
    {
        $user = $request->user();

        return $user !== null && ($user->isSuperadmin() || $user->canUpdate(self::MENU));
    }
}
