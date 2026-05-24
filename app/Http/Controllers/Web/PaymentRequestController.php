<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Payment\RejectPaymentRequestRequest;
use App\Http\Requests\Payment\StorePaymentRequestRequest;
use App\Http\Requests\Payment\UpdatePaymentRequestRequest;
use App\Models\Invoice;
use App\Models\PaymentRequest;
use App\Models\User;
use App\Services\Payment\PaymentRequestService;
use App\Services\Payment\PaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class PaymentRequestController extends Controller
{
    public function __construct(
        private readonly PaymentRequestService $service,
        private readonly PaymentService $paymentService,
    ) {}

    public function index(Request $request): InertiaResponse
    {
        $this->authorize('viewAny', PaymentRequest::class);

        $query = PaymentRequest::query()
            ->with(['invoice:id,invoice_number,total,outstanding', 'customer:id,code,name', 'sales:id,name', 'verifier:id,name'])
            ->orderByDesc('paid_at')
            ->orderByDesc('id');

        $user = $request->user();
        if ($user && ! $user->isSuperadmin() && $user->hasRole('sales')) {
            $query->where('sales_id', $user->id);
        }

        if ($search = trim((string) $request->input('q'))) {
            $query->where(function ($q) use ($search): void {
                $q->whereHas('invoice', fn ($i) => $i->where('invoice_number', 'like', "%{$search}%"))
                    ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$search}%"))
                    ->orWhere('reference_no', 'like', "%{$search}%");
            });
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($method = $request->input('method')) {
            $query->where('method', $method);
        }

        $totals = PaymentRequest::query()
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS draft', [PaymentRequest::STATUS_DRAFT])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS submitted', [PaymentRequest::STATUS_SUBMITTED])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS verified', [PaymentRequest::STATUS_VERIFIED])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS rejected', [PaymentRequest::STATUS_REJECTED])
            ->first();

        return Inertia::render('PaymentRequests/Index', [
            'paymentRequests' => $query->paginate(25)->withQueryString(),
            'filters' => [
                'q' => $request->input('q'),
                'status' => $request->input('status'),
                'method' => $request->input('method'),
            ],
            'stats' => [
                'total' => (int) ($totals->total ?? 0),
                'draft' => (int) ($totals->draft ?? 0),
                'submitted' => (int) ($totals->submitted ?? 0),
                'verified' => (int) ($totals->verified ?? 0),
                'rejected' => (int) ($totals->rejected ?? 0),
            ],
        ]);
    }

    public function create(Request $request): InertiaResponse
    {
        $this->authorize('create', PaymentRequest::class);

        $openInvoices = Invoice::query()
            ->openOrPartial()
            ->with(['customer:id,code,name'])
            ->orderByDesc('invoice_date')
            ->limit(200)
            ->get(['id', 'invoice_number', 'customer_id', 'total', 'outstanding', 'due_date', 'is_cash']);

        $selectedInvoice = null;
        if ($invId = $request->input('invoice_id')) {
            $selectedInvoice = Invoice::query()
                ->with('customer:id,code,name,phone')
                ->find((int) $invId);
        }

        return Inertia::render('PaymentRequests/Create', [
            'openInvoices' => $openInvoices,
            'selectedInvoice' => $selectedInvoice,
            'salesUsers' => User::query()
                ->whereHas('role', fn ($q) => $q->where('code', 'sales'))
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }

    public function store(StorePaymentRequestRequest $request): RedirectResponse
    {
        $data = $request->validated();

        if ($request->hasFile('proof_image')) {
            $data['proof_image_path'] = $this->storeProof($request->file('proof_image'));
        }

        $req = $this->service->createDraft($data, $request->user());

        return redirect()
            ->route('payment-requests.show', $req)
            ->with('flash.success', 'Payment request dibuat (draft).');
    }

    public function show(PaymentRequest $paymentRequest): InertiaResponse
    {
        $this->authorize('view', $paymentRequest);

        $paymentRequest->load([
            'invoice:id,invoice_number,total,outstanding,paid_amount,due_date,is_cash',
            'invoice.customer:id,code,name,phone,address',
            'customer:id,code,name,phone',
            'sales:id,name',
            'verifier:id,name',
            'rejecter:id,name',
            'payment:id,payment_number,status,amount,applied_amount,paid_at',
        ]);

        return Inertia::render('PaymentRequests/Show', [
            'paymentRequest' => $paymentRequest,
            'canEdit' => $paymentRequest->canBeEdited() && (request()->user()?->can('update', $paymentRequest) ?? false),
            'canSubmit' => $paymentRequest->canBeSubmitted() && (request()->user()?->can('submit', $paymentRequest) ?? false),
            'canCancel' => $paymentRequest->canBeCancelled() && (request()->user()?->can('cancel', $paymentRequest) ?? false),
            'canVerify' => $paymentRequest->canBeVerified() && (request()->user()?->can('verify', $paymentRequest) ?? false),
            'canReject' => $paymentRequest->canBeRejected() && (request()->user()?->can('reject', $paymentRequest) ?? false),
        ]);
    }

    public function edit(PaymentRequest $paymentRequest): InertiaResponse
    {
        $this->authorize('update', $paymentRequest);
        abort_unless($paymentRequest->canBeEdited(), 422, 'Payment request tidak bisa diedit.');

        $paymentRequest->load(['invoice:id,invoice_number,total,outstanding']);

        return Inertia::render('PaymentRequests/Edit', [
            'paymentRequest' => $paymentRequest,
        ]);
    }

    public function update(UpdatePaymentRequestRequest $request, PaymentRequest $paymentRequest): RedirectResponse
    {
        $data = $request->validated();

        if ($request->hasFile('proof_image')) {
            $data['proof_image_path'] = $this->storeProof($request->file('proof_image'));
        }

        $this->service->updateDraft($paymentRequest, $data, $request->user());

        return redirect()
            ->route('payment-requests.show', $paymentRequest)
            ->with('flash.success', 'Payment request diperbarui.');
    }

    public function submit(Request $request, PaymentRequest $paymentRequest): RedirectResponse
    {
        $this->authorize('submit', $paymentRequest);

        $this->service->submit($paymentRequest, $request->user());

        return back()->with('flash.success', 'Payment request disubmit untuk verifikasi kasir.');
    }

    public function cancel(Request $request, PaymentRequest $paymentRequest): RedirectResponse
    {
        $this->authorize('cancel', $paymentRequest);

        $this->service->cancel($paymentRequest, $request->user());

        return back()->with('flash.success', 'Payment request dibatalkan.');
    }

    public function verify(Request $request, PaymentRequest $paymentRequest): RedirectResponse
    {
        $this->authorize('verify', $paymentRequest);

        $payment = $this->paymentService->createFromRequest($paymentRequest, $request->user());

        return redirect()
            ->route('payments.show', $payment)
            ->with('flash.success', "Verified. Payment {$payment->payment_number} ter-create.");
    }

    public function reject(RejectPaymentRequestRequest $request, PaymentRequest $paymentRequest): RedirectResponse
    {
        $this->service->reject($paymentRequest, $request->validated('rejection_reason'), $request->user());

        return back()->with('flash.success', 'Payment request ditolak. Sales akan diberitahu.');
    }

    private function storeProof($file): string
    {
        $ext = $file->getClientOriginalExtension() ?: 'jpg';
        $filename = Str::uuid()->toString().'.'.$ext;
        $year = now()->format('Y');
        $path = "payment_requests/{$year}/{$filename}";

        Storage::disk('public')->putFileAs(
            dirname($path),
            $file,
            basename($path),
        );

        return $path;
    }
}
