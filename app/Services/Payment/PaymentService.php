<?php

namespace App\Services\Payment;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentRequest;
use App\Models\User;
use App\Services\Billing\InvoiceService;
use App\Services\Numbering\NumberingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Payment lifecycle:
 *  - createFromRequest (saat kasir verify): bikin Payment row, apply ke invoice
 *    via InvoiceService.applyPayment (kecuali giro yang status=pending_clearing)
 *  - clearGiro: giro yang cair → apply ke invoice
 *  - bounceGiro: giro tidak cair → reverse apply (kalau sudah ter-apply) atau
 *    mark bounced (kalau belum ter-apply)
 */
class PaymentService
{
    public function __construct(
        private readonly NumberingService $numbering,
        private readonly InvoiceService $invoiceService,
    ) {}

    /**
     * Kasir verify payment request → create Payment + apply ke invoice.
     */
    public function createFromRequest(PaymentRequest $request, User $by): Payment
    {
        if (! $request->canBeVerified()) {
            throw ValidationException::withMessages([
                'status' => 'Payment request tidak bisa di-verify pada status saat ini.',
            ]);
        }

        if ($request->payment !== null) {
            throw ValidationException::withMessages([
                'payment_request_id' => 'Payment untuk request ini sudah dibuat (idempotency).',
            ]);
        }

        return DB::transaction(function () use ($request, $by): Payment {
            /** @var Invoice $invoice */
            $invoice = $request->invoice()->lockForUpdate()->first();

            $amount = (float) $request->amount;
            $outstanding = (float) $invoice->outstanding;
            $applied = min($amount, $outstanding);
            $overpayment = max(0.0, $amount - $outstanding);

            $isGiro = $request->method === PaymentRequest::METHOD_GIRO;
            $status = $isGiro ? Payment::STATUS_PENDING_CLEARING : Payment::STATUS_POSTED;

            $payment = new Payment([
                'payment_request_id' => $request->id,
                'invoice_id' => $invoice->id,
                'invoice_snapshot' => [
                    'invoice_number' => $invoice->invoice_number,
                    'total' => (float) $invoice->total,
                    'outstanding_before' => $outstanding,
                ],
                'customer_id' => $invoice->customer_id,
                'amount' => $amount,
                'applied_amount' => $isGiro ? 0.0 : $applied,
                'overpayment_amount' => $isGiro ? 0.0 : $overpayment,
                'method' => $request->method,
                'reference_no' => $request->reference_no,
                'bank_name' => $request->bank_name,
                'giro_due_date' => $request->giro_due_date,
                'paid_at' => $request->paid_at,
                'verified_at' => now(),
                'status' => $status,
                'fiscal_year' => (int) now()->format('Y'),
                'proof_image_path' => $request->proof_image_path,
                'notes' => $request->notes,
                'recorded_by' => $by->id,
            ]);
            $payment->payment_number = $this->numbering->next('payment');
            $payment->save();

            // Verify request
            $request->update([
                'status' => PaymentRequest::STATUS_VERIFIED,
                'verified_at' => now(),
                'verified_by' => $by->id,
            ]);

            // Apply ke invoice (kecuali giro yang nunggu cair)
            if (! $isGiro && $applied > 0) {
                $this->invoiceService->applyPayment($invoice, $applied, $by);
                $payment->update(['applied_to_invoice_at' => now()]);
            }

            return $payment->refresh();
        });
    }

    /**
     * Giro cair → apply ke invoice + status cleared.
     */
    public function clearGiro(Payment $payment, ?User $by = null): Payment
    {
        if (! $payment->canBeCleared()) {
            throw ValidationException::withMessages([
                'status' => 'Payment tidak dalam status pending_clearing.',
            ]);
        }

        return DB::transaction(function () use ($payment, $by): Payment {
            /** @var Invoice $invoice */
            $invoice = $payment->invoice()->lockForUpdate()->first();

            $amount = (float) $payment->amount;
            $outstanding = (float) $invoice->outstanding;
            $applied = min($amount, $outstanding);
            $overpayment = max(0.0, $amount - $outstanding);

            $payment->update([
                'status' => Payment::STATUS_CLEARED,
                'cleared_at' => now(),
                'cleared_by' => $by?->id,
                'applied_amount' => $applied,
                'overpayment_amount' => $overpayment,
                'applied_to_invoice_at' => now(),
            ]);

            if ($applied > 0) {
                $this->invoiceService->applyPayment($invoice, $applied, $by);
            }

            return $payment->refresh();
        });
    }

    /**
     * Giro tidak cair → reverse apply (kalau sudah ter-apply) + mark bounced.
     */
    public function bounceGiro(Payment $payment, string $reason, ?User $by = null): Payment
    {
        if (! $payment->canBeBounced()) {
            throw ValidationException::withMessages([
                'status' => 'Payment tidak dalam status pending_clearing.',
            ]);
        }

        return DB::transaction(function () use ($payment, $reason, $by): Payment {
            // Untuk giro yang belum ter-apply (status pending_clearing), tidak
            // perlu reverse — cuma update status saja. Reverse hanya kalau
            // di masa depan ada flow giro yang apply dulu lalu bounce.
            if ((float) $payment->applied_amount > 0) {
                $this->invoiceService->reversePayment(
                    $payment->invoice()->first(),
                    (float) $payment->applied_amount,
                    $by,
                );
            }

            $payment->update([
                'status' => Payment::STATUS_BOUNCED,
                'bounced_at' => now(),
                'bounced_by' => $by?->id,
                'bounce_reason' => $reason,
                'applied_amount' => 0,
                'overpayment_amount' => 0,
            ]);

            return $payment->refresh();
        });
    }
}
