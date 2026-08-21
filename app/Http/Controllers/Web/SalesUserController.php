<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\StoreSalesUserRequest;
use App\Http\Requests\User\UpdateSalesUserRequest;
use App\Models\Customer;
use App\Models\PaymentRequest;
use App\Models\Role;
use App\Models\SalesOrder;
use App\Models\SalesVisit;
use App\Models\User;
use App\Models\UserDevice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Manajemen khusus user role=sales. Pisah dari UserController standar karena:
 *  - butuh kolom tambahan di list (default area, monthly target, device status)
 *  - saat create bisa pre-register device awal (MAC, UUID, name)
 *  - role otomatis di-set ke 'sales' (admin tidak perlu pilih)
 */
class SalesUserController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', User::class);

        $salesRoleId = Role::ofCode(Role::CODE_SALES)->value('id');

        $query = User::query()
            ->with('role:id,code,name')
            ->where('role_id', $salesRoleId)
            ->orderBy('name');

        if ($search = trim((string) $request->input('q'))) {
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($request->filled('active')) {
            $query->where('is_active', $request->boolean('active'));
        }

        $totals = User::query()
            ->where('role_id', $salesRoleId)
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw('SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) AS active')
            ->selectRaw('SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) AS inactive')
            ->first();

        $withDevice = UserDevice::query()
            ->whereIn('user_id', User::query()->where('role_id', $salesRoleId)->pluck('id'))
            ->where('status', UserDevice::STATUS_ACTIVE)
            ->distinct('user_id')
            ->count('user_id');

        $paginated = $query->paginate(20)->withQueryString();
        $actor = $request->user();

        // Decorate setiap row dengan ringkasan device aktif + jumlah product group
        $paginated->getCollection()->loadCount([
            'devices as active_devices_count' => function ($q): void {
                $q->where('status', UserDevice::STATUS_ACTIVE);
            },
            'productGroups as product_groups_count',
        ]);

        // Eager-load device aktif terbaru untuk pre-fill modal edit
        $paginated->getCollection()->load(['devices' => function ($q): void {
            $q->where('status', UserDevice::STATUS_ACTIVE)
                ->orderByDesc('registered_at')
                ->limit(1);
        }]);

        $paginated->getCollection()->transform(function (User $u) use ($actor): User {
            $u->setAttribute('permissions_summary', [
                'canUpdate' => $actor?->can('update', $u) ?? false,
                'canResetPassword' => $actor?->can('resetPassword', $u) ?? false,
                'canForceLogout' => $actor?->can('forceLogout', $u) ?? false,
                'canDelete' => $actor?->can('delete', $u) ?? false,
                'canManageOverrides' => $actor?->can('updateMenuOverrides', $u) ?? false,
            ]);
            $u->setAttribute('primary_device', $u->devices->first());

            return $u;
        });

        return Inertia::render('SalesUsers/Index', [
            'users' => $paginated,
            'filters' => [
                'q' => $request->input('q'),
                'active' => $request->input('active'),
            ],
            'stats' => [
                'total' => (int) ($totals->total ?? 0),
                'active' => (int) ($totals->active ?? 0),
                'inactive' => (int) ($totals->inactive ?? 0),
                'with_device' => $withDevice,
            ],
        ]);
    }

    public function store(StoreSalesUserRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $salesRoleId = Role::ofCode(Role::CODE_SALES)->value('id');

        return DB::transaction(function () use ($data, $salesRoleId, $request): RedirectResponse {
            $userData = [
                'name' => $data['name'],
                'username' => $data['username'],
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'] ?? null,
                'role_id' => $salesRoleId,
                'is_active' => $data['is_active'] ?? true,
                'force_password_change' => true,
                'password' => ! empty($data['password']) ? $data['password'] : '12345678',
            ];

            $user = User::create($userData);

            // Pre-register device kalau admin set (OS default Android utk sales lapangan)
            if (! empty($data['device_uuid'])) {
                UserDevice::create([
                    'user_id' => $user->id,
                    'device_uuid' => $data['device_uuid'],
                    'mac_address' => $data['mac_address'] ?? null,
                    'device_name' => $data['device_name'] ?? null,
                    'os' => 'Android',
                    'status' => UserDevice::STATUS_ACTIVE,
                    'registered_at' => now(),
                    'registered_by' => $request->user()?->id,
                    'notes' => 'Pre-registered saat create user sales.',
                ]);
            }

            return redirect()
                ->route('sales-users.index')
                ->with(
                    'flash.success',
                    "Sales {$user->name} berhasil dibuat".(! empty($data['device_uuid'])
                        ? ' & device awal ter-register.'
                        : '. Klik Edit untuk melengkapi data karyawan.'),
                );
        });
    }

    public function update(UpdateSalesUserRequest $request, User $user): RedirectResponse
    {
        $data = $request->validated();

        return DB::transaction(function () use ($data, $user, $request): RedirectResponse {
            $userPayload = [
                'name' => $data['name'],
                'username' => $data['username'],
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'] ?? null,
            ];
            if (array_key_exists('is_active', $data)) {
                $userPayload['is_active'] = (bool) $data['is_active'];
            }
            if (! empty($data['password'])) {
                $userPayload['password'] = $data['password'];
                $userPayload['force_password_change'] = true;
            }

            $user->update($userPayload);

            // Device update / pre-register saat edit.
            if (! empty($data['device_uuid'])) {
                $existing = UserDevice::query()
                    ->where('user_id', $user->id)
                    ->where('device_uuid', $data['device_uuid'])
                    ->first();

                if ($existing) {
                    $existing->update([
                        'device_name' => $data['device_name'] ?? $existing->device_name,
                        'mac_address' => $data['mac_address'] ?? $existing->mac_address,
                        'status' => UserDevice::STATUS_ACTIVE,
                    ]);
                } else {
                    UserDevice::create([
                        'user_id' => $user->id,
                        'device_uuid' => $data['device_uuid'],
                        'mac_address' => $data['mac_address'] ?? null,
                        'device_name' => $data['device_name'] ?? null,
                        'os' => 'Android',
                        'status' => UserDevice::STATUS_ACTIVE,
                        'registered_at' => now(),
                        'registered_by' => $request->user()?->id,
                        'notes' => 'Registered/updated via edit form.',
                    ]);
                }
            }

            return back()->with('flash.success', 'Data sales diperbarui.');
        });
    }

    /**
     * Hapus user sales — hanya boleh kalau tidak ada transaksi yang masih
     * mengikat ke sales ini (SO, kunjungan, payment request, atau customer
     * yang di-assign). DB sudah restrictOnDelete untuk SO/Visit/PaymentRequest,
     * tapi dicek eksplisit di sini supaya errornya jadi pesan yang jelas
     * (bukan QueryException mentah), dan customer assignment ikut dicek
     * karena itu nullOnDelete di DB (tidak diblokir otomatis).
     */
    public function destroy(User $user): RedirectResponse
    {
        $this->authorize('delete', $user);

        $blockers = [];

        if (SalesOrder::query()->where('sales_id', $user->id)->exists()) {
            $blockers[] = 'Sales Order';
        }
        if (SalesVisit::query()->where('sales_id', $user->id)->exists()) {
            $blockers[] = 'Riwayat Kunjungan';
        }
        if (PaymentRequest::query()->where('sales_id', $user->id)->exists()) {
            $blockers[] = 'Payment Request';
        }
        if (Customer::query()->where('assigned_sales_id', $user->id)->exists()) {
            $blockers[] = 'Customer yang di-assign';
        }

        if ($blockers !== []) {
            return back()->with(
                'flash.error',
                "User {$user->name} tidak bisa dihapus karena masih punya data terkait: "
                    .implode(', ', $blockers).'. Nonaktifkan (suspend) saja.',
            );
        }

        $user->delete();

        return redirect()
            ->route('sales-users.index')
            ->with('flash.success', "Sales {$user->name} dihapus.");
    }
}
