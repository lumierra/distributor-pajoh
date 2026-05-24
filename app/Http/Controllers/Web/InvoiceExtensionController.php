<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Payment\ApproveExtensionRequest;
use App\Http\Requests\Payment\RejectExtensionRequest;
use App\Http\Requests\Payment\RequestExtensionRequest;
use App\Models\Invoice;
use App\Models\InvoiceExtensionLog;
use App\Services\Payment\InvoiceExtensionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class InvoiceExtensionController extends Controller
{
    public function __construct(private readonly InvoiceExtensionService $service) {}

    public function index(Request $request): InertiaResponse
    {
        $this->authorize('viewAny', InvoiceExtensionLog::class);

        $query = InvoiceExtensionLog::query()
            ->with([
                'invoice:id,invoice_number,total,outstanding,due_date',
                'invoice.customer:id,code,name',
                'requester:id,name',
                'reviewer:id,name',
            ])
            ->orderByDesc('requested_at');

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        return Inertia::render('InvoiceExtensions/Index', [
            'extensions' => $query->paginate(25)->withQueryString(),
            'filters' => [
                'status' => $request->input('status'),
            ],
        ]);
    }

    public function store(RequestExtensionRequest $request, Invoice $invoice): RedirectResponse
    {
        $log = $this->service->requestExtension($invoice, $request->validated(), $request->user());

        return redirect()
            ->route('invoices.show', $invoice)
            ->with('flash.success', "Extension request #{$log->id} dikirim ke admin.");
    }

    public function approve(ApproveExtensionRequest $request, InvoiceExtensionLog $invoiceExtensionLog): RedirectResponse
    {
        $this->service->approve(
            $invoiceExtensionLog,
            $request->validated('approved_new_due_date'),
            $request->user(),
        );

        return back()->with('flash.success', 'Extension disetujui. Due date invoice di-update.');
    }

    public function reject(RejectExtensionRequest $request, InvoiceExtensionLog $invoiceExtensionLog): RedirectResponse
    {
        $this->service->reject(
            $invoiceExtensionLog,
            $request->validated('rejection_reason'),
            $request->user(),
        );

        return back()->with('flash.success', 'Extension ditolak.');
    }

    public function cancel(Request $request, InvoiceExtensionLog $invoiceExtensionLog): RedirectResponse
    {
        $this->authorize('cancel', $invoiceExtensionLog);

        $this->service->cancel($invoiceExtensionLog, $request->user());

        return back()->with('flash.success', 'Extension request dibatalkan.');
    }
}
