<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ChecksForcePasswordChange
{
    /**
     * Allowed routes while force_password_change=true:
     * the change-password screen itself and the logout endpoint.
     */
    private const ALLOWED_ROUTES = [
        'password.change.show',
        'password.change.store',
        'logout',
    ];

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->force_password_change && ! in_array($request->route()?->getName(), self::ALLOWED_ROUTES, true)) {
            return redirect()->route('password.change.show');
        }

        return $next($request);
    }
}
