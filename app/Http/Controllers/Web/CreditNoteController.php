<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\CustomerReturn\ApplyCreditNoteRequest;
use App\Models\CreditNote;
use App\Models\Invoice;
use App\Services\CustomerReturn\CreditNoteService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class CreditNoteController extends Controller
{
    public function __construct(private readonly CreditNoteService $service) {}

    public function index(Request $request): InertiaResponse
    {
        $this->authorize('viewAny', CreditNote::class);

        $query = CreditNote::query()
            ->with(['customer:id,code,name', 'customerReturn:id,return_number'])
            ->orderByDesc('cn_date')
            ->orderByDesc('id');

        if ($search = trim((string) $request->input('q'))) {
            $query->where(function ($q) use ($search): void {
                $q->where('cn_number', 'like', "%{$search}%")
                    ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$search}%"));
            });
        }
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }
        if ($customerId = $request->input('customer_id')) {
            $query->where('customer_id', $customerId);
        }

        $totals = CreditNote::query()
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw('COALESCE(SUM(amount), 0) AS total_amount')
            ->selectRaw('COALESCE(SUM(remaining_amount), 0) AS total_remaining')
            ->first();

        return Inertia::render('CreditNotes/Index', [
            'creditNotes' => $query->paginate(25)->withQueryString(),
            'filters' => [
                'q' => $request->input('q'),
                'status' => $request->input('status'),
                'customer_id' => $request->input('customer_id'),
            ],
            'stats' => [
                'total' => (int) ($totals->total ?? 0),
                'total_amount' => (float) ($totals->total_amount ?? 0),
                'total_remaining' => (float) ($totals->total_remaining ?? 0),
            ],
        ]);
    }

    public function show(CreditNote $creditNote): InertiaResponse
    {
        $this->authorize('view', $creditNote);

        $creditNote->load([
            'customer:id,code,name',
            'customerReturn:id,return_number,return_date,status',
            'applications.invoice:id,invoice_number,total,outstanding,status',
            'applications.appliedBy:id,name',
            'creator:id,name',
        ]);

        $openInvoices = Invoice::query()
            ->where('customer_id', $creditNote->customer_id)
            ->where('outstanding', '>', 0)
            ->orderByDesc('invoice_date')
            ->get(['id', 'invoice_number', 'total', 'outstanding']);

        return Inertia::render('CreditNotes/Show', [
            'creditNote' => $creditNote,
            'openInvoices' => $openInvoices,
            'canApply' => request()->user()?->can('apply', $creditNote) ?? false,
        ]);
    }

    public function apply(ApplyCreditNoteRequest $request, CreditNote $creditNote): RedirectResponse
    {
        $this->authorize('apply', $creditNote);

        $invoice = Invoice::query()->findOrFail($request->validated('invoice_id'));

        $this->service->applyToInvoice(
            $creditNote,
            $invoice,
            (float) $request->validated('amount'),
            $request->user(),
        );

        return back()->with('flash.success', "CN {$creditNote->cn_number} di-apply ke {$invoice->invoice_number}.");
    }
}
