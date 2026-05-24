<?php

namespace App\Services\Payment;

use App\Models\Invoice;
use App\Models\InvoiceExtensionLog;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Extension due_date workflow:
 *  - Sales request perpanjangan (pending)
 *  - Admin approve (apply ke invoice.due_date, snapshot original_due_date)
 *  - Admin reject
 */
class InvoiceExtensionService
{
    /**
     * @param  array{new_due_date:string|CarbonInterface, reason:string}  $data
     */
    public function requestExtension(Invoice $invoice, array $data, User $by): InvoiceExtensionLog
    {
        if ($invoice->status === Invoice::STATUS_PAID) {
            throw ValidationException::withMessages([
                'invoice_id' => 'Invoice sudah lunas, tidak butuh extension.',
            ]);
        }

        $newDue = $data['new_due_date'] instanceof CarbonInterface
            ? $data['new_due_date']
            : Carbon::parse($data['new_due_date']);

        $currentDue = $invoice->due_date instanceof CarbonInterface
            ? $invoice->due_date
            : Carbon::parse($invoice->due_date);

        if ($newDue->lte($currentDue)) {
            throw ValidationException::withMessages([
                'new_due_date' => 'New due date harus lebih dari due_date saat ini.',
            ]);
        }

        // Block multiple pending request untuk invoice yang sama.
        $hasPending = InvoiceExtensionLog::query()
            ->where('invoice_id', $invoice->id)
            ->where('status', InvoiceExtensionLog::STATUS_PENDING)
            ->exists();

        if ($hasPending) {
            throw ValidationException::withMessages([
                'invoice_id' => 'Sudah ada extension request pending untuk invoice ini.',
            ]);
        }

        return InvoiceExtensionLog::create([
            'invoice_id' => $invoice->id,
            'requested_by' => $by->id,
            'requested_at' => now(),
            'old_due_date' => $currentDue,
            'new_due_date_requested' => $newDue,
            'request_reason' => $data['reason'],
            'status' => InvoiceExtensionLog::STATUS_PENDING,
        ]);
    }

    /**
     * Admin approve (boleh modify new due date).
     */
    public function approve(InvoiceExtensionLog $log, ?string $approvedNewDueDate, User $by): InvoiceExtensionLog
    {
        if ($log->status !== InvoiceExtensionLog::STATUS_PENDING) {
            throw ValidationException::withMessages([
                'status' => 'Extension log tidak dalam status pending.',
            ]);
        }

        return DB::transaction(function () use ($log, $approvedNewDueDate, $by): InvoiceExtensionLog {
            $approved = $approvedNewDueDate !== null
                ? Carbon::parse($approvedNewDueDate)
                : $log->new_due_date_requested;

            /** @var Invoice $invoice */
            $invoice = $log->invoice()->lockForUpdate()->first();

            // Snapshot original_due_date kalau belum ada (extension pertama)
            $invoice->update([
                'original_due_date' => $invoice->original_due_date ?? $invoice->due_date,
                'due_date' => $approved,
            ]);

            // Resolve status (kalau dari overdue → bisa balik ke open/partial_paid)
            $this->refreshInvoiceStatus($invoice);

            $log->update([
                'status' => InvoiceExtensionLog::STATUS_APPROVED,
                'reviewed_at' => now(),
                'reviewed_by' => $by->id,
                'approved_new_due_date' => $approved,
            ]);

            return $log->refresh();
        });
    }

    public function reject(InvoiceExtensionLog $log, string $reason, User $by): InvoiceExtensionLog
    {
        if ($log->status !== InvoiceExtensionLog::STATUS_PENDING) {
            throw ValidationException::withMessages([
                'status' => 'Extension log tidak dalam status pending.',
            ]);
        }

        $log->update([
            'status' => InvoiceExtensionLog::STATUS_REJECTED,
            'reviewed_at' => now(),
            'reviewed_by' => $by->id,
            'rejection_reason' => $reason,
        ]);

        return $log->refresh();
    }

    public function cancel(InvoiceExtensionLog $log, User $by): InvoiceExtensionLog
    {
        if ($log->status !== InvoiceExtensionLog::STATUS_PENDING) {
            throw ValidationException::withMessages([
                'status' => 'Hanya pending yang bisa di-cancel.',
            ]);
        }

        $log->update([
            'status' => InvoiceExtensionLog::STATUS_CANCELLED,
            'reviewed_at' => now(),
            'reviewed_by' => $by->id,
        ]);

        return $log->refresh();
    }

    private function refreshInvoiceStatus(Invoice $invoice): void
    {
        $invoice->refresh();

        // Re-resolve berdasar due_date baru
        $outstanding = (float) $invoice->outstanding;
        $paid = (float) $invoice->paid_amount;
        $overdue = $invoice->due_date->isPast();

        if ($outstanding <= 0.0001) {
            // tetap paid
            return;
        }

        if ($overdue) {
            $invoice->update(['status' => Invoice::STATUS_OVERDUE]);
        } elseif ($paid > 0) {
            $invoice->update([
                'status' => Invoice::STATUS_PARTIAL_PAID,
                'overdue_set_at' => null,
            ]);
        } else {
            $invoice->update([
                'status' => Invoice::STATUS_OPEN,
                'overdue_set_at' => null,
            ]);
        }
    }
}
