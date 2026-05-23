<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\LoginHistory;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LoginHistoryController extends Controller
{
    public function index(Request $request): Response
    {
        // Permission ditegakkan oleh middleware `menu:audit.login_history`.
        $query = LoginHistory::query()
            ->with('user:id,name,username')
            ->orderByDesc('login_at');

        if ($search = trim((string) $request->input('q'))) {
            $query->where(function ($q) use ($search): void {
                $q->where('username_input', 'like', "%{$search}%")
                    ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$search}%"));
            });
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', (int) $request->input('user_id'));
        }

        if ($channel = $request->input('channel')) {
            $query->where('channel', $channel);
        }

        if ($request->filled('status')) {
            $query->where('is_successful', $request->boolean('status'));
        }

        if ($from = $request->input('from')) {
            $query->where('login_at', '>=', $from);
        }
        if ($to = $request->input('to')) {
            $query->where('login_at', '<=', $to.' 23:59:59');
        }

        return Inertia::render('LoginHistory/Index', [
            'rows' => $query->paginate(50)->withQueryString(),
            'users' => User::query()->orderBy('name')->get(['id', 'name', 'username']),
            'filters' => [
                'q' => $request->input('q'),
                'user_id' => $request->input('user_id'),
                'channel' => $request->input('channel'),
                'status' => $request->input('status'),
                'from' => $request->input('from'),
                'to' => $request->input('to'),
            ],
        ]);
    }
}
