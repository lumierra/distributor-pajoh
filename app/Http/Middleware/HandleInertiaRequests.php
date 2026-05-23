<?php

namespace App\Http\Middleware;

use App\Services\Permission\MenuTreeBuilder;
use App\Services\Permission\PermissionResolver;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function __construct(
        private readonly MenuTreeBuilder $menuTreeBuilder,
        private readonly PermissionResolver $permissionResolver,
    ) {}

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => fn () => $request->user() ? [
                    'id' => $request->user()->id,
                    'name' => $request->user()->name,
                    'username' => $request->user()->username,
                    'role' => $request->user()->role?->code,
                    'is_superadmin' => $request->user()->isSuperadmin(),
                    'force_password_change' => $request->user()->force_password_change,
                ] : null,
            ],
            'menuTree' => fn () => $request->user()
                ? $this->menuTreeBuilder->build($request->user())
                : [],
            'permissions' => fn () => $request->user()
                ? $this->permissionResolver->buildCache($request->user())
                : (object) [],
            'company' => [
                'name' => fn () => setting('company.name', 'Pajoh Distributor'),
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('flash.success'),
                'error' => fn () => $request->session()->get('flash.error'),
            ],
            'csrf_token' => fn () => csrf_token(),
        ];
    }
}
