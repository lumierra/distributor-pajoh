<?php

use App\Http\Middleware\ChecksForcePasswordChange;
use App\Http\Middleware\EnsureMenuAccess;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            HandleInertiaRequests::class,
            ChecksForcePasswordChange::class,
        ]);

        $middleware->statefulApi();

        $middleware->trustProxies(at: '*', headers: Request::HEADER_X_FORWARDED_FOR
            | Request::HEADER_X_FORWARDED_HOST
            | Request::HEADER_X_FORWARDED_PORT
            | Request::HEADER_X_FORWARDED_PROTO);

        $middleware->alias([
            'menu' => EnsureMenuAccess::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        /**
         * Request Inertia yang kena AuthorizationException / abort(403|404|...)
         * jangan sampai jatuh ke halaman error bawaan Laravel — itu bikin
         * keluar dari SPA. Redirect back dengan flash.error supaya tetap
         * muncul sebagai toast (lihat AppLayout.vue showFlashToast()).
         */
        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->header('X-Inertia')) {
                return null;
            }

            $status = match (true) {
                $e instanceof AuthorizationException => 403,
                $e instanceof HttpExceptionInterface => $e->getStatusCode(),
                default => null,
            };

            if ($status === null || $status < 400 || $status >= 500) {
                return null;
            }

            $message = $e->getMessage();
            if ($message === '' || $status === 404) {
                $message = match ($status) {
                    403 => 'Anda tidak punya akses untuk melakukan aksi ini.',
                    404 => 'Data yang dicari tidak ditemukan.',
                    default => 'Permintaan gagal diproses.',
                };
            }

            return back()->with('flash.error', $message);
        });
    })->create();
