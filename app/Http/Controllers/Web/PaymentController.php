<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Payment\BounceGiroRequest;
use App\Models\Customer;
use App\Models\Payment;
use App\Services\Payment\PaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class PaymentController extends Controller
{
    public function __construct(private readonly PaymentService $service) {}

    public function index(Request $request): InertiaResponse
    {
        $this->authorize('viewAny', Payment::class);

        $query = Payment::query()
            ->with([
                'invoice:id,invoice_number',
                'customer:id,code,name',
                'recorder:id,name',
                'paymentRequest:id,sales_id',
                'paymentRequest.sales:id,name',
            ])
            ->orderByDesc('paid_at')
            ->orderByDesc('id');

        if ($search = trim((string) $request->input('q'))) {
            $query->where(function ($q) use ($search): void {
                $q->where('payment_number', 'like', "%{$search}%")
                    ->orWhereHas('invoice', fn ($i) => $i->where('invoice_number', 'like', "%{$search}%"))
                    ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$search}%"));
            });
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($method = $request->input('method')) {
            $query->where('method', $method);
        }

        if ($customerId = $request->input('customer_id')) {
            $query->where('customer_id', $customerId);
        }

        $totals = Payment::query()
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS posted', [Payment::STATUS_POSTED])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS pending_clearing', [Payment::STATUS_PENDING_CLEARING])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS cleared', [Payment::STATUS_CLEARED])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS bounced', [Payment::STATUS_BOUNCED])
            ->selectRaw('COALESCE(SUM(CASE WHEN status IN (?,?) THEN amount ELSE 0 END), 0) AS total_amount', [
                Payment::STATUS_POSTED, Payment::STATUS_CLEARED,
            ])
            ->first();

        return Inertia::render('Payments/Index', [
            'payments' => $query->paginate(25)->withQueryString(),
            'customers' => Customer::query()->where('is_active', true)->orderBy('name')->limit(500)->get(['id', 'code', 'name']),
            'filters' => [
                'q' => $request->input('q'),
                'status' => $request->input('status'),
                'method' => $request->input('method'),
                'customer_id' => $request->input('customer_id'),
            ],
            'stats' => [
                'total' => (int) ($totals->total ?? 0),
                'posted' => (int) ($totals->posted ?? 0),
                'pending_clearing' => (int) ($totals->pending_clearing ?? 0),
                'cleared' => (int) ($totals->cleared ?? 0),
                'bounced' => (int) ($totals->bounced ?? 0),
                'total_amount' => (float) ($totals->total_amount ?? 0),
            ],
        ]);
    }

    public function show(Payment $payment): InertiaResponse
    {
        $this->authorize('view', $payment);

        $payment->load([
            'invoice:id,invoice_number,total,outstanding,paid_amount,status,due_date',
            'invoice.customer:id,code,name,phone',
            'customer:id,code,name,phone',
            'paymentRequest.sales:id,name',
            'recorder:id,name',
            'clearer:id,name',
            'bouncer:id,name',
        ]);

        return Inertia::render('Payments/Show', [
            'payment' => $payment,
            'canClear' => $payment->canBeCleared() && (request()->user()?->can('clearGiro', $payment) ?? false),
            'canBounce' => $payment->canBeBounced() && (request()->user()?->can('bounceGiro', $payment) ?? false),
        ]);
    }

    public function clearGiro(Request $request, Payment $payment): RedirectResponse
    {
        $this->authorize('clearGiro', $payment);

        $this->service->clearGiro($payment, $request->user());

        return back()->with('flash.success', "Giro {$payment->payment_number} cair & ter-apply ke invoice.");
    }

    public function bounceGiro(BounceGiroRequest $request, Payment $payment): RedirectResponse
    {
        $this->service->bounceGiro($payment, $request->validated('bounce_reason'), $request->user());

        return back()->with('flash.success', "Giro {$payment->payment_number} di-mark bounced.");
    }
}
