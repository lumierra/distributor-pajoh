<?php

namespace App\Services\Payment;

use App\Models\Invoice;
use App\Models\PaymentRequest;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentRequestService
{
    /**
     * Sales (atau admin atas nama sales) buat payment request draft.
     *
     * @param  array<string, mixed>  $data
     */
    public function createDraft(array $data, User $by): PaymentRequest
    {
        return DB::transaction(function () use ($data, $by): PaymentRequest {
            /** @var Invoice $invoice */
            $invoice = Invoice::query()->findOrFail($data['invoice_id']);

            $this->assertInvoiceOk($invoice);

            $amount = (float) $data['amount'];
            if ($amount <= 0) {
                throw ValidationException::withMessages(['amount' => 'Amount harus > 0.']);
            }

            // Method validation
            $method = $data['method'];
            if (! in_array($method, PaymentRequest::METHODS, true)) {
                throw ValidationException::withMessages(['method' => 'Method tidak valid.']);
            }

            if ($method === PaymentRequest::METHOD_GIRO && empty($data['giro_due_date'])) {
                throw ValidationException::withMessages(['giro_due_date' => 'Giro wajib ada tanggal jatuh tempo.']);
            }

            $paidAt = $data['paid_at'] instanceof CarbonInterface
                ? $data['paid_at']
                : Carbon::parse($data['paid_at']);

            return PaymentRequest::create([
                'invoice_id' => $invoice->id,
                'customer_id' => $invoice->customer_id,
                'customer_snapshot' => $invoice->customer_snapshot,
                'sales_id' => $data['sales_id'] ?? $by->id,
                'amount' => $amount,
                'method' => $method,
                'reference_no' => $data['reference_no'] ?? null,
                'bank_name' => $data['bank_name'] ?? null,
                'giro_due_date' => $data['giro_due_date'] ?? null,
                'paid_at' => $paidAt,
                'proof_image_path' => $data['proof_image_path'] ?? null,
                'notes' => $data['notes'] ?? null,
                'status' => PaymentRequest::STATUS_DRAFT,
                'created_by' => $by->id,
            ]);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateDraft(PaymentRequest $request, array $data, User $by): PaymentRequest
    {
        if (! $request->canBeEdited()) {
            throw ValidationException::withMessages(['status' => 'Payment request tidak bisa diedit pada status saat ini.']);
        }

        $paidAt = $data['paid_at'] instanceof CarbonInterface
            ? $data['paid_at']
            : Carbon::parse($data['paid_at']);

        $method = $data['method'];
        if ($method === PaymentRequest::METHOD_GIRO && empty($data['giro_due_date'])) {
            throw ValidationException::withMessages(['giro_due_date' => 'Giro wajib ada tanggal jatuh tempo.']);
        }

        $update = [
            'amount' => (float) $data['amount'],
            'method' => $method,
            'reference_no' => $data['reference_no'] ?? null,
            'bank_name' => $data['bank_name'] ?? null,
            'giro_due_date' => $data['giro_due_date'] ?? null,
            'paid_at' => $paidAt,
            'notes' => $data['notes'] ?? null,
            'updated_by' => $by->id,
        ];

        if (array_key_exists('proof_image_path', $data) && $data['proof_image_path'] !== null) {
            $update['proof_image_path'] = $data['proof_image_path'];
        }

        // Reset rejection flag saat di-edit setelah ditolak
        if ($request->status === PaymentRequest::STATUS_REJECTED) {
            $update['status'] = PaymentRequest::STATUS_DRAFT;
            $update['rejected_at'] = null;
            $update['rejected_by'] = null;
            $update['rejection_reason'] = null;
        }

        $request->update($update);

        return $request->refresh();
    }

    public function submit(PaymentRequest $request, User $by): PaymentRequest
    {
        if (! $request->canBeSubmitted()) {
            throw ValidationException::withMessages(['status' => 'Payment request tidak bisa di-submit.']);
        }

        $request->update([
            'status' => PaymentRequest::STATUS_SUBMITTED,
            'submitted_at' => now(),
            'rejected_at' => null,
            'rejected_by' => null,
            'rejection_reason' => null,
        ]);

        return $request->refresh();
    }

    public function reject(PaymentRequest $request, string $reason, User $by): PaymentRequest
    {
        if (! $request->canBeRejected()) {
            throw ValidationException::withMessages(['status' => 'Payment request tidak bisa di-reject pada status saat ini.']);
        }

        $request->update([
            'status' => PaymentRequest::STATUS_REJECTED,
            'rejected_at' => now(),
            'rejected_by' => $by->id,
            'rejection_reason' => $reason,
        ]);

        return $request->refresh();
    }

    public function cancel(PaymentRequest $request, User $by): PaymentRequest
    {
        if (! $request->canBeCancelled()) {
            throw ValidationException::withMessages(['status' => 'Payment request tidak bisa di-cancel pada status saat ini.']);
        }

        $request->update([
            'status' => PaymentRequest::STATUS_CANCELLED,
            'cancelled_at' => now(),
            'cancelled_by' => $by->id,
        ]);

        return $request->refresh();
    }

    private function assertInvoiceOk(Invoice $invoice): void
    {
        if ($invoice->status === Invoice::STATUS_PAID) {
            throw ValidationException::withMessages([
                'invoice_id' => 'Invoice sudah lunas, tidak butuh payment.',
            ]);
        }
    }
}
