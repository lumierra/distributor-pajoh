<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\ResetPasswordRequest;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateMenuOverridesRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Models\Menu;
use App\Models\PasswordResetLog;
use App\Models\Role;
use App\Models\User;
use App\Models\UserMenuOverride;
use App\Services\Auth\SessionInvalidatorService;
use App\Services\Permission\PermissionResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function __construct(
        private readonly PermissionResolver $resolver,
        private readonly SessionInvalidatorService $sessionInvalidator,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', User::class);

        $query = User::query()
            ->with('role:id,code,name')
            ->orderBy('name');

        if ($search = trim((string) $request->input('q'))) {
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($roleCode = $request->input('role')) {
            $query->whereHas('role', fn ($q) => $q->where('code', $roleCode));
        }

        if ($request->filled('active')) {
            $query->where('is_active', $request->boolean('active'));
        }

        $totals = User::query()
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw('SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) AS active')
            ->selectRaw('SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) AS inactive')
            ->first();
        $superadmin = User::ofRole(Role::CODE_SUPERADMIN)->count();

        return Inertia::render('Users/Index', [
            'users' => $query->paginate(20)->withQueryString(),
            'roles' => Role::query()->orderBy('sort_order')->get(['id', 'code', 'name']),
            'filters' => [
                'q' => $request->input('q'),
                'role' => $request->input('role'),
                'active' => $request->input('active'),
            ],
            'stats' => [
                'total' => (int) ($totals->total ?? 0),
                'active' => (int) ($totals->active ?? 0),
                'inactive' => (int) ($totals->inactive ?? 0),
                'superadmin' => $superadmin,
            ],
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', User::class);

        return Inertia::render('Users/Edit', [
            'user' => null,
            'roles' => $this->assignableRoles(),
            'canUpdate' => true,
            'canResetPassword' => false,
            'canForceLogout' => false,
            'canDelete' => false,
            'canManageOverrides' => false,
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['force_password_change'] = true;
        $data['is_active'] = $data['is_active'] ?? true;

        $user = User::create($data);

        return redirect()
            ->route('users.edit', $user)
            ->with('flash.success', "User {$user->name} berhasil dibuat.");
    }

    public function edit(User $user): Response
    {
        $this->authorize('view', $user);

        $user->load('role:id,code,name');
        $actor = request()->user();

        return Inertia::render('Users/Edit', [
            'user' => $user,
            'roles' => $this->assignableRoles(),
            'canUpdate' => $actor?->can('update', $user) ?? false,
            'canResetPassword' => $actor?->can('resetPassword', $user) ?? false,
            'canForceLogout' => $actor?->can('forceLogout', $user) ?? false,
            'canDelete' => $actor?->can('delete', $user) ?? false,
            'canManageOverrides' => $actor?->can('updateMenuOverrides', $user) ?? false,
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $user->update($request->validated());

        return back()->with('flash.success', 'Data user diperbarui.');
    }

    public function destroy(User $user): RedirectResponse
    {
        $this->authorize('delete', $user);

        $user->delete();

        return redirect()
            ->route('users.index')
            ->with('flash.success', "User {$user->name} dihapus.");
    }

    public function resetPassword(ResetPasswordRequest $request, User $user): RedirectResponse
    {
        DB::transaction(function () use ($request, $user): void {
            $user->forceFill([
                'password' => $request->input('new_password'),       // hashed via cast
                'force_password_change' => true,
                'password_changed_at' => null,
            ])->save();

            PasswordResetLog::create([
                'user_id' => $user->id,
                'reset_by_user_id' => $request->user()->id,
                'reset_at' => now(),
                'ip' => (string) ($request->ip() ?? '0.0.0.0'),
                'user_agent' => substr((string) $request->userAgent(), 0, 512),
                'reason' => $request->input('reason'),
            ]);

            $this->sessionInvalidator->fullLogout($user);
        });

        return back()->with('flash.success', 'Password user direset & semua sesi dihapus.');
    }

    public function forceLogout(Request $request, User $user): RedirectResponse
    {
        $this->authorize('forceLogout', $user);

        $result = $this->sessionInvalidator->fullLogout($user);

        return back()->with(
            'flash.success',
            "Force logout: {$result['sessions']} sesi web & {$result['tokens']} token mobile dicabut.",
        );
    }

    public function toggleActive(Request $request, User $user): RedirectResponse
    {
        $this->authorize('toggleActive', $user);

        $user->forceFill(['is_active' => ! $user->is_active])->save();

        return back()->with(
            'flash.success',
            $user->is_active ? 'User diaktifkan.' : 'User dinonaktifkan.',
        );
    }

    public function menuOverrides(User $user): Response
    {
        $this->authorize('updateMenuOverrides', $user);

        return Inertia::render('Users/MenuOverrides', [
            'user' => $user->load('role:id,code,name'),
            'menus' => Menu::query()->orderBy('order')->get(['id', 'parent_id', 'code', 'label', 'order']),
            'overrides' => UserMenuOverride::query()
                ->where('user_id', $user->id)
                ->get()
                ->keyBy('menu_id'),
        ]);
    }

    public function updateMenuOverrides(UpdateMenuOverridesRequest $request, User $user): RedirectResponse
    {
        DB::transaction(function () use ($request, $user): void {
            $rows = collect($request->validated('overrides'));
            $menuIds = $rows->pluck('menu_id')->all();

            UserMenuOverride::where('user_id', $user->id)
                ->whereNotIn('menu_id', $menuIds)
                ->delete();

            foreach ($rows as $row) {
                $hasValue = ($row['can_view'] ?? null) !== null
                    || ($row['can_create'] ?? null) !== null
                    || ($row['can_update'] ?? null) !== null
                    || ($row['can_delete'] ?? null) !== null
                    || ($row['can_approve'] ?? null) !== null
                    || ($row['can_export'] ?? null) !== null
                    || ! empty($row['note']);

                if (! $hasValue) {
                    UserMenuOverride::where('user_id', $user->id)
                        ->where('menu_id', $row['menu_id'])
                        ->delete();

                    continue;
                }

                UserMenuOverride::updateOrCreate(
                    ['user_id' => $user->id, 'menu_id' => $row['menu_id']],
                    [
                        'can_view' => $row['can_view'] ?? null,
                        'can_create' => $row['can_create'] ?? null,
                        'can_update' => $row['can_update'] ?? null,
                        'can_delete' => $row['can_delete'] ?? null,
                        'can_approve' => $row['can_approve'] ?? null,
                        'can_export' => $row['can_export'] ?? null,
                        'note' => $row['note'] ?? null,
                    ],
                );
            }

            $this->resolver->invalidateCache($user);
        });

        return back()->with('flash.success', 'Menu override disimpan.');
    }

    private function assignableRoles(): Collection
    {
        $query = Role::query()->active()->orderBy('sort_order');

        if (! request()->user()?->isSuperadmin()) {
            $query->where('code', '!=', Role::CODE_SUPERADMIN);
        }

        return $query->get(['id', 'code', 'name']);
    }
}
