<?php

namespace App\Http\Controllers\Api\V1\Sales;

use App\Http\Controllers\Controller;
use App\Http\Requests\SalesVisit\RequestBypassRequest;
use App\Models\Customer;
use App\Services\Sales\BypassService;
use Illuminate\Http\JsonResponse;

class BypassApiController extends Controller
{
    public function __construct(private readonly BypassService $service) {}

    public function store(RequestBypassRequest $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validated();
        $customer = Customer::query()->findOrFail($data['customer_id']);

        $req = $this->service->requestBypass($user, $customer, $data);

        return response()->json([
            'id' => $req->id,
            'status' => $req->status,
            'message' => 'Bypass request dikirim ke admin. Tunggu approval.',
        ], 201);
    }
}
