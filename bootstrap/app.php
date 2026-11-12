<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        // api: __DIR__.'/../routes/api.php',  // ← KOMENTARIN INI
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Register middleware alias untuk Spatie
        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
        ]);

        // Preferensi bahasa per-user (Profil > Setting > Bahasa).
        $middleware->web(append: [
            \App\Http\Middleware\SetLocale::class,
            \App\Http\Middleware\SetSecurityHeaders::class,
            \App\Http\Middleware\EnsureUserHasRole::class,
            \App\Http\Middleware\FlashActionToast::class,
        ]);

        // Percayai proxy (nginx/ngrok) agar Laravel melihat skema https asli
        // dari header X-Forwarded-Proto, bukan hanya http dari request internal.
        $middleware->trustProxies(at: '*');

        // Webhook gateway WhatsApp (Green API & Meta) dipanggil dari luar tanpa
        // sesi/CSRF — kecualikan dari validasi token CSRF.
        $middleware->validateCsrfTokens(except: [
            'api/whatsapp/webhook',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Token CSRF basi (sesi kedaluwarsa/hilang) pada request logout
        // atau dari user yang sudah guest: antar ke login, bukan 419.
        // Mismatch lain saat masih login tetap 419 (proteksi CSRF utuh).
        $exceptions->render(function (\Illuminate\Session\TokenMismatchException $e, \Illuminate\Http\Request $request) {
            if ($request->routeIs('logout') || $request->user() === null) {
                if ($request->expectsJson()) {
                    return response()->json(['message' => __('Sesi berakhir. Silakan login kembali.')], 419);
                }

                return redirect()->route('login')->with('status', __('Sesi berakhir. Silakan login kembali.'));
            }

            return null;
        });

        // Catat penolakan aturan bisnis (abort(422, '...')) agar pemicunya bisa
        // dilacak dari log. ValidationException tidak lewat jalur ini, jadi log
        // tidak dibanjiri error validasi form biasa.
        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $e, \Illuminate\Http\Request $request) {
            if ($e->getStatusCode() !== 422) {
                return null;
            }

            \Illuminate\Support\Facades\Log::warning('422 ditolak: '.$e->getMessage(), [
                'url' => $request->fullUrl(),
                'method' => $request->method(),
                'user_id' => $request->user()?->id,
            ]);

            return null;
        });
    })
    ->withSchedule(function (\Illuminate\Console\Scheduling\Schedule $schedule) {
        // Polling pesan masuk & status WhatsApp (Green API) — untuk localhost tanpa webhook publik
        $schedule->command('whatsapp:receive')->everyThirtySeconds()
            ->withoutOverlapping();

        // Bot WhatsApp: balas otomatis customer yang belum dijawab
        $schedule->command('whatsapp:bot')->everyThirtySeconds()
            ->withoutOverlapping();
    })
    ->create();