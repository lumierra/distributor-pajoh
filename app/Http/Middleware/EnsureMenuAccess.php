<?php

namespace App\Http\Middleware;

use App\Models\Menu;
use App\Services\Permission\PermissionResolver;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureMenuAccess
{
    public function __construct(private readonly PermissionResolver $resolver) {}

    /**
     * Guard a route by mapping the matched route → menu code → menu.can_view.
     *
     * Usage:
     *   Route::get('/users', ...)->middleware('menu:master.user');
     *   Route::resource('roles', ...)->middleware('menu:master.role');
     *
     * The menu code is taken from the middleware parameter; if omitted, falls
     * back to the route's `defaults('menu')` value. Without either, the
     * middleware is a no-op (so routes that aren't menu-bound — e.g. profile —
     * still resolve normally).
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, ?string $menuCode = null, string $action = 'view'): Response
    {
        $user = $request->user();
        if (! $user) {
            return redirect()->route('login');
        }

        $code = $menuCode ?? $request->route()?->defaults['menu'] ?? null;
        if ($code === null) {
            return $next($request);
        }

        if (! Menu::query()->where('code', $code)->exists()) {
            abort(404);
        }

        if (! $this->resolver->resolve($user, $action, $code)) {
            abort(403, "Anda tidak punya akses ke menu [{$code}].");
        }

        return $next($request);
    }
}
