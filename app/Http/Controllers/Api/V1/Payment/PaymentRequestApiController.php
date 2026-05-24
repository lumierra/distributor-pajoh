<?php

namespace App\Http\Controllers\Api\V1\Payment;

use App\Http\Controllers\Controller;
use App\Http\Requests\Payment\StorePaymentRequestRequest;
use App\Http\Requests\Payment\UpdatePaymentRequestRequest;
use App\Models\Invoice;
use App\Models\PaymentRequest;
use App\Services\Payment\PaymentRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Mobile endpoints untuk sales lapor pembayaran dari lapangan:
 *  - List pending invoices (yang bisa di-bayar) per customer
 *  - List own payment requests
 *  - Create / Update draft
 *  - Submit untuk verifikasi kasir
 *  - Cancel draft
 */
class PaymentRequestApiController extends Controller
{
    public function __construct(private readonly PaymentRequestService $service) {}

    /**
     * GET /api/v1/sales/payment-requests
     * Sales lihat payment requests miliknya.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = PaymentRequest::query()
            ->with(['invoice:id,invoice_number,total,outstanding', 'customer:id,code,name'])
            ->where('sales_id', $user->id)
            ->orderByDesc('paid_at');

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        return response()->json([
            'data' => $query->paginate(25),
        ]);
    }

    /**
     * GET /api/v1/sales/payment-requests/invoices
     * List invoices yang bisa di-bayar (open/partial_paid/overdue) untuk
     * customer-customer yang relevan ke sales ini.
     */
    public function payableInvoices(Request $request): JsonResponse
    {
        $query = Invoice::query()
            ->openOrPartial()
            ->with(['customer:id,code,name,phone'])
            ->orderByDesc('invoice_date')
            ->limit(100);

        if ($customerId = $request->input('customer_id')) {
            $query->where('customer_id', $customerId);
        }

        return response()->json([
            'data' => $query->get(['id', 'invoice_number', 'customer_id', 'total', 'outstanding', 'due_date', 'status', 'is_cash']),
        ]);
    }

    public function show(Request $request, PaymentRequest $paymentRequest): JsonResponse
    {
        $this->authorize('view', $paymentRequest);

        $paymentRequest->load(['invoice', 'customer', 'verifier:id,name']);

        return response()->json(['data' => $paymentRequest]);
    }

    public function store(StorePaymentRequestRequest $request): JsonResponse
    {
        $data = $request->validated();

        if ($request->hasFile('proof_image')) {
            $data['proof_image_path'] = $this->storeProof($request->file('proof_image'));
        }

        $req = $this->service->createDraft($data, $request->user());

        return response()->json([
            'message' => 'Payment request created (draft).',
            'data' => $req,
        ], 201);
    }

    public function update(UpdatePaymentRequestRequest $request, PaymentRequest $paymentRequest): JsonResponse
    {
        $data = $request->validated();

        if ($request->hasFile('proof_image')) {
            $data['proof_image_path'] = $this->storeProof($request->file('proof_image'));
        }

        $this->service->updateDraft($paymentRequest, $data, $request->user());

        return response()->json(['data' => $paymentRequest->fresh()]);
    }

    public function submit(Request $request, PaymentRequest $paymentRequest): JsonResponse
    {
        $this->authorize('submit', $paymentRequest);

        $this->service->submit($paymentRequest, $request->user());

        return response()->json(['message' => 'Submitted', 'data' => $paymentRequest->fresh()]);
    }

    public function cancel(Request $request, PaymentRequest $paymentRequest): JsonResponse
    {
        $this->authorize('cancel', $paymentRequest);

        $this->service->cancel($paymentRequest, $request->user());

        return response()->json(['message' => 'Cancelled', 'data' => $paymentRequest->fresh()]);
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
