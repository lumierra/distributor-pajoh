<?php

namespace App\Http\Controllers\Web;

use App\Exports\Customer\CustomerImportErrorExport;
use App\Exports\Customer\CustomerTemplateExport;
use App\Http\Controllers\Controller;
use App\Imports\Customer\CustomerImport;
use App\Models\Customer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Bulk-import customer:
 *  - downloadTemplate: kirim file Excel template
 *  - import: terima upload, proses lewat CustomerImport, kasih flash result.
 *    Kalau ada row gagal, simpan errors ke session supaya admin bisa download
 *    laporan error sebagai Excel via downloadErrors.
 *  - downloadErrors: ambil errors dari session, render ke Excel.
 */
class CustomerImportController extends Controller
{
    private const SESSION_ERRORS_KEY = 'customer_import.errors';

    public function downloadTemplate(): BinaryFileResponse
    {
        $this->authorize('create', Customer::class);

        return Excel::download(
            new CustomerTemplateExport,
            'template-customer.xlsx',
        );
    }

    public function import(Request $request): RedirectResponse
    {
        $this->authorize('create', Customer::class);

        $request->validate([
            'file' => [
                'required',
                'file',
                'mimes:xlsx,xls,csv',
                'max:5120', // 5 MB
            ],
        ]);

        $import = app(CustomerImport::class);
        $import->import($request->file('file'));

        // Simpan errors ke session supaya bisa di-download sebagai Excel
        if (! empty($import->errors)) {
            session()->flash(self::SESSION_ERRORS_KEY, $import->errors);
        }

        $msg = sprintf(
            '%d customer berhasil dibuat, %d gagal.',
            $import->created,
            $import->skipped,
        );

        if (! empty($import->errors)) {
            $msg .= ' Klik tombol "Download Error" untuk detail.';

            return back()->with('flash.warning', $msg);
        }

        return back()->with('flash.success', $msg);
    }

    public function downloadErrors(): BinaryFileResponse|RedirectResponse
    {
        $this->authorize('create', Customer::class);

        $errors = session(self::SESSION_ERRORS_KEY);

        if (empty($errors)) {
            return back()->with('flash.error', 'Tidak ada data error untuk di-download.');
        }

        // Re-flash agar kalau user reload bisa download lagi
        session()->flash(self::SESSION_ERRORS_KEY, $errors);

        return Excel::download(
            new CustomerImportErrorExport($errors),
            'customer-import-errors.xlsx',
        );
    }
}
