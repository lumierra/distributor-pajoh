<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Invoice;
use App\Services\Billing\InvoicePdfRenderer;
use App\Services\Billing\InvoiceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class InvoiceController extends Controller
{
    public function __construct(
        private readonly InvoiceService $service,
        private readonly InvoicePdfRenderer $pdf,
    ) {}

    public function index(Request $request): InertiaResponse
    {
        $this->authorize('viewAny', Invoice::class);

        $query = Invoice::query()
            ->with([
                'customer:id,code,name',
                'salesOrder:id,so_number',
                'deliveryOrder:id,do_number',
                'sales:id,name',
            ])
            ->orderByDesc('invoice_date')
            ->orderByDesc('id');

        if ($search = trim((string) $request->input('q'))) {
            $query->where(function ($q) use ($search): void {
                $q->where('invoice_number', 'like', "%{$search}%")
                    ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('salesOrder', fn ($s) => $s->where('so_number', 'like', "%{$search}%"));
            });
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($customerId = $request->input('customer_id')) {
            $query->where('customer_id', $customerId);
        }

        if ($year = $request->input('year')) {
            $query->where('fiscal_year', (int) $year);
        }

        if ($request->boolean('overdue_only')) {
            $query->where('status', Invoice::STATUS_OVERDUE);
        }

        $totals = Invoice::query()
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS open', [Invoice::STATUS_OPEN])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS partial', [Invoice::STATUS_PARTIAL_PAID])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS paid', [Invoice::STATUS_PAID])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS overdue', [Invoice::STATUS_OVERDUE])
            ->selectRaw('COALESCE(SUM(outstanding), 0) AS total_outstanding')
            ->first();

        return Inertia::render('Invoices/Index', [
            'invoices' => $query->paginate(25)->withQueryString(),
            'customers' => Customer::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->limit(500)
                ->get(['id', 'code', 'name']),
            'filters' => [
                'q' => $request->input('q'),
                'status' => $request->input('status'),
                'customer_id' => $request->input('customer_id'),
                'year' => $request->input('year'),
                'overdue_only' => $request->boolean('overdue_only'),
            ],
            'stats' => [
                'total' => (int) ($totals->total ?? 0),
                'open' => (int) ($totals->open ?? 0),
                'partial' => (int) ($totals->partial ?? 0),
                'paid' => (int) ($totals->paid ?? 0),
                'overdue' => (int) ($totals->overdue ?? 0),
                'total_outstanding' => (float) ($totals->total_outstanding ?? 0),
            ],
        ]);
    }

    public function show(Invoice $invoice): InertiaResponse
    {
        $this->authorize('view', $invoice);

        $invoice->load([
            'customer:id,code,name,phone,whatsapp,address,city,province,npwp',
            'salesOrder:id,so_number,so_date',
            'deliveryOrder:id,do_number,do_date,delivered_at,receiver_name',
            'sales:id,name',
            'items',
        ]);

        return Inertia::render('Invoices/Show', [
            'invoice' => $invoice,
            'canRegeneratePdf' => request()->user()?->can('regeneratePdf', $invoice) ?? false,
        ]);
    }

    public function downloadPdf(Invoice $invoice): Response
    {
        $this->authorize('downloadPdf', $invoice);

        // Auto-generate kalau PDF belum ada
        if ($invoice->pdf_path === null || ! Storage::disk('local')->exists($invoice->pdf_path)) {
            $this->pdf->generate($invoice);
            $invoice->refresh();
        }

        abort_if(
            $invoice->pdf_path === null || ! Storage::disk('local')->exists($invoice->pdf_path),
            404,
            'PDF tidak tersedia.',
        );

        return response()->file(
            Storage::disk('local')->path($invoice->pdf_path),
            ['Content-Type' => 'application/pdf'],
        );
    }

    public function regeneratePdf(Invoice $invoice): RedirectResponse
    {
        $this->authorize('regeneratePdf', $invoice);

        $this->pdf->generate($invoice);

        return back()->with('flash.success', "PDF faktur {$invoice->invoice_number} di-regenerate.");
    }

    /**
     * Manual trigger untuk daily overdue check. Biasanya dipanggil scheduler;
     * di MVP admin bisa trigger manual.
     */
    public function markOverdue(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('markOverdue', Invoice::class), 403);

        $count = $this->service->markOverdueDue();

        return back()->with('flash.success', "{$count} invoice di-tandai overdue.");
    }
}
