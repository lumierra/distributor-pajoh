<?php

namespace App\Services\CustomerReturn;

use App\Models\CreditNote;
use App\Models\CreditNoteApplication;
use App\Models\CustomerReturn;
use App\Models\Invoice;
use App\Models\User;
use App\Services\Billing\InvoiceService;
use App\Services\Numbering\NumberingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreditNoteService
{
    public function __construct(
        private readonly NumberingService $numbering,
        private readonly InvoiceService $invoiceService,
    ) {}

    public function createFromReturn(CustomerReturn $cr, User $by): CreditNote
    {
        return DB::transaction(function () use ($cr, $by): CreditNote {
            $cn = new CreditNote([
                'customer_return_id' => $cr->id,
                'customer_id' => $cr->customer_id,
                'cn_date' => now()->toDateString(),
                'amount' => (float) $cr->total_value,
                'applied_amount' => 0,
                'remaining_amount' => (float) $cr->total_value,
                'status' => CreditNote::STATUS_OPEN,
                'fiscal_year' => (int) now()->format('Y'),
                'created_by' => $by->id,
            ]);
            $cn->cn_number = $this->numbering->next('credit_note');
            $cn->save();

            return $cn;
        });
    }

    /**
     * Apply credit note ke invoice. Validasi:
     *  - CN belum closed
     *  - amount > 0
     *  - amount <= CN remaining
     *  - amount <= invoice outstanding
     */
    public function applyToInvoice(CreditNote $cn, Invoice $invoice, float $amount, User $by): CreditNoteApplication
    {
        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount' => 'Amount harus > 0.']);
        }

        return DB::transaction(function () use ($cn, $invoice, $amount, $by): CreditNoteApplication {
            $cn = CreditNote::query()->lockForUpdate()->findOrFail($cn->id);
            $invoice = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);

            if ($cn->customer_id !== $invoice->customer_id) {
                throw ValidationException::withMessages(['invoice_id' => 'CN dan invoice harus customer yang sama.']);
            }
            if (! $cn->canBeApplied()) {
                throw ValidationException::withMessages(['status' => 'CN sudah closed atau remaining 0.']);
            }
            if ($amount > (float) $cn->remaining_amount + 0.0001) {
                throw ValidationException::withMessages(['amount' => 'Amount melebihi sisa CN.']);
            }
            if ($amount > (float) $invoice->outstanding + 0.0001) {
                throw ValidationException::withMessages(['amount' => 'Amount melebihi outstanding invoice.']);
            }

            $app = CreditNoteApplication::create([
                'credit_note_id' => $cn->id,
                'invoice_id' => $invoice->id,
                'amount' => $amount,
                'applied_at' => now(),
                'applied_by' => $by->id,
            ]);

            $newApplied = (float) $cn->applied_amount + $amount;
            $newRemaining = max(0, (float) $cn->amount - $newApplied);
            $newStatus = $newRemaining <= 0.0001 ? CreditNote::STATUS_CLOSED : CreditNote::STATUS_APPLIED;

            $cn->update([
                'applied_amount' => $newApplied,
                'remaining_amount' => $newRemaining,
                'status' => $newStatus,
            ]);

            $this->invoiceService->applyCreditNote($invoice, $amount, $by);

            // Kalau CN sudah closed dan dari CR, tandai CR sebagai credited.
            if ($newStatus === CreditNote::STATUS_CLOSED) {
                $cn->customerReturn?->update(['status' => CustomerReturn::STATUS_CREDITED]);
            }

            return $app;
        });
    }
}
