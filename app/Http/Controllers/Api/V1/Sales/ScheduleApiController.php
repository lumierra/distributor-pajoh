<?php

namespace App\Http\Controllers\Api\V1\Sales;

use App\Http\Controllers\Controller;
use App\Services\Sales\SalesScheduleResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ScheduleApiController extends Controller
{
    public function __construct(private readonly SalesScheduleResolver $resolver) {}

    public function today(Request $request): JsonResponse
    {
        $user = $request->user();
        $date = $request->input('date') ? Carbon::parse($request->input('date')) : now();

        $schedules = $this->resolver->todayFor($user, $date)->map(fn ($s) => [
            'id' => $s->id,
            'pattern' => $s->pattern,
            'visit_time' => $s->visit_time,
            'customer' => [
                'id' => $s->customer?->id,
                'code' => $s->customer?->code,
                'name' => $s->customer?->name,
                'address' => $s->customer?->address,
                'phone' => $s->customer?->phone,
                'latitude' => $s->customer?->latitude,
                'longitude' => $s->customer?->longitude,
            ],
            'notes' => $s->notes,
        ]);

        return response()->json([
            'date' => $date->toDateString(),
            'count' => $schedules->count(),
            'schedules' => $schedules,
        ]);
    }
}
