<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class ActivityLogController extends Controller
{
    public function index(Request $request): InertiaResponse
    {
        $this->ensureCanView($request);

        $query = ActivityLog::query()
            ->with('user:id,name')
            ->orderByDesc('created_at');

        if ($search = trim((string) $request->input('q'))) {
            $query->where(function ($q) use ($search): void {
                $q->where('model_label', 'like', "%{$search}%")
                    ->orWhere('user_name_snapshot', 'like', "%{$search}%");
            });
        }
        if ($userId = $request->input('user_id')) {
            $query->where('user_id', $userId);
        }
        if ($action = $request->input('action')) {
            $query->where('action', $action);
        }
        if ($modelType = $request->input('model_type')) {
            $query->where('model_type', 'like', "%{$modelType}%");
        }
        if ($from = $request->input('from')) {
            $query->whereDate('created_at', '>=', $from);
        }
        if ($to = $request->input('to')) {
            $query->whereDate('created_at', '<=', $to);
        }

        return Inertia::render('ActivityLogs/Index', [
            'logs' => $query->paginate(50)->withQueryString(),
            'filters' => [
                'q' => $request->input('q'),
                'user_id' => $request->input('user_id'),
                'action' => $request->input('action'),
                'model_type' => $request->input('model_type'),
                'from' => $request->input('from'),
                'to' => $request->input('to'),
            ],
            'users' => User::query()->orderBy('name')->limit(500)->get(['id', 'name']),
            'actions' => ActivityLog::query()->distinct()->pluck('action')->sort()->values(),
        ]);
    }

    public function show(ActivityLog $activityLog): InertiaResponse
    {
        $this->ensureCanView(request());

        $activityLog->load('user:id,name,username');

        return Inertia::render('ActivityLogs/Show', [
            'log' => $activityLog,
        ]);
    }

    /**
     * Endpoint untuk fetch activity logs by model type + id (untuk tab "Activity" di detail page).
     */
    public function forModel(Request $request): JsonResponse
    {
        $this->ensureCanView($request);

        $data = $request->validate([
            'model_type' => 'required|string',
            'model_id' => 'required|integer',
        ]);

        $logs = ActivityLog::query()
            ->with('user:id,name')
            ->where('model_type', $data['model_type'])
            ->where('model_id', $data['model_id'])
            ->orderByDesc('created_at')
            ->limit(100)
            ->get();

        return response()->json($logs);
    }

    private function ensureCanView(Request $request): void
    {
        $user = $request->user();
        if ($user === null || (! $user->isSuperadmin() && ! $user->canView('audit.activity'))) {
            abort(403);
        }
    }
}
