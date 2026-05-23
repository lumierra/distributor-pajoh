<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Role\StoreRoleRequest;
use App\Http\Requests\Role\UpdatePermissionsRequest;
use App\Http\Requests\Role\UpdateRoleRequest;
use App\Models\Menu;
use App\Models\Role;
use App\Models\RoleMenu;
use App\Services\Permission\PermissionResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class RoleController extends Controller
{
    public function __construct(private readonly PermissionResolver $resolver) {}

    public function index(): Response
    {
        $this->authorize('viewAny', Role::class);

        $roles = Role::query()
            ->withCount('users')
            ->orderBy('sort_order')
            ->get();

        return Inertia::render('Roles/Index', [
            'roles' => $roles,
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Role::class);

        return Inertia::render('Roles/Edit', [
            'role' => null,
        ]);
    }

    public function store(StoreRoleRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['is_system'] = false;
        $data['is_active'] = $data['is_active'] ?? true;

        $role = Role::create($data);

        return redirect()
            ->route('roles.permissions.edit', $role)
            ->with('flash.success', "Role {$role->name} dibuat.");
    }

    public function edit(Role $role): Response
    {
        $this->authorize('view', $role);

        return Inertia::render('Roles/Edit', [
            'role' => $role,
            'canUpdate' => request()->user()?->can('update', $role) ?? false,
            'canDelete' => request()->user()?->can('delete', $role) ?? false,
        ]);
    }

    public function update(UpdateRoleRequest $request, Role $role): RedirectResponse
    {
        $role->update($request->validated());

        return back()->with('flash.success', 'Role diperbarui.');
    }

    public function destroy(Role $role): RedirectResponse
    {
        $this->authorize('delete', $role);

        $role->delete();

        return redirect()->route('roles.index')->with('flash.success', 'Role dihapus.');
    }

    public function editPermissions(Role $role): Response
    {
        $this->authorize('updatePermissions', $role);

        $menus = Menu::query()->orderBy('order')->get(['id', 'parent_id', 'code', 'label', 'order']);
        $permissions = RoleMenu::query()
            ->where('role_id', $role->id)
            ->get()
            ->keyBy('menu_id');

        return Inertia::render('Roles/Permissions', [
            'role' => $role,
            'menus' => $menus,
            'permissions' => $permissions,
        ]);
    }

    public function updatePermissions(UpdatePermissionsRequest $request, Role $role): RedirectResponse
    {
        DB::transaction(function () use ($request, $role): void {
            $rows = collect($request->validated('permissions'));
            $menuIds = $rows->pluck('menu_id')->all();

            // Hapus permission yang tidak ada di payload (full reset semantik).
            RoleMenu::where('role_id', $role->id)
                ->whereNotIn('menu_id', $menuIds)
                ->delete();

            foreach ($rows as $row) {
                $allFalse = ! $row['can_view']
                    && ! $row['can_create']
                    && ! $row['can_update']
                    && ! $row['can_delete']
                    && ! $row['can_approve']
                    && ! $row['can_export'];

                if ($allFalse) {
                    RoleMenu::where('role_id', $role->id)
                        ->where('menu_id', $row['menu_id'])
                        ->delete();

                    continue;
                }

                RoleMenu::updateOrCreate(
                    ['role_id' => $role->id, 'menu_id' => $row['menu_id']],
                    [
                        'can_view' => $row['can_view'],
                        'can_create' => $row['can_create'],
                        'can_update' => $row['can_update'],
                        'can_delete' => $row['can_delete'],
                        'can_approve' => $row['can_approve'],
                        'can_export' => $row['can_export'],
                    ],
                );
            }

            $this->resolver->invalidateRole($role);
        });

        return back()->with('flash.success', 'Permission role diperbarui.');
    }
}
