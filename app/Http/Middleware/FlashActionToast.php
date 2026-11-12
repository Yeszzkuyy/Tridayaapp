<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Jaring pengaman feedback aksi: setiap request mutasi (POST/PUT/PATCH/DELETE)
 * yang berhasil diarahkan kembali tetapi belum punya pesan sendiri otomatis
 * diberi pesan sukses, supaya user selalu melihat pop up setelah menyimpan,
 * mengubah, mengirim, atau menghapus data.
 *
 * Controller yang sudah memberi pesan spesifik (`with('success', ...)`,
 * `with('warning', ...)`, `with('error', ...)`) tetap menang; middleware ini
 * tidak menimpa. Request AJAX/JSON, Livewire, notifikasi, dan autentikasi
 * dilewati agar tidak memunculkan pop up yang tidak relevan.
 */
class FlashActionToast
{
    /** Route yang tidak perlu pop up (sudah punya alur sendiri). */
    private const SKIP_ROUTES = [
        'login',
        'logout',
        'register',
        'password.email',
        'verification.send',
        'verification.verify',
        'two-factor.login',
        'two-factor.enable',
        'two-factor.confirm',
        'two-factor.disable',
        'two-factor.qr-code',
        'two-factor.secret-key',
        'two-factor.recovery-codes',
        'two-factor.regenerate-recovery-codes',
    ];

    /** Awalan route yang selalu dilewati. */
    private const SKIP_PREFIXES = [
        'notifications.',
        'livewire.',
        'whatsapp.',
        'sanctum.',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $this->shouldFlash($request, $response)) {
            return $response;
        }

        $request->session()->flash('success', self::messageFor($request->route()?->getName()));

        return $response;
    }

    private function shouldFlash(Request $request, Response $response): bool
    {
        if (! $response instanceof RedirectResponse) {
            return false;
        }

        if ($request->isMethodSafe() || $request->ajax() || $request->expectsJson()) {
            return false;
        }

        if (! $request->user()) {
            return false;
        }

        $route = $request->route()?->getName();

        if (! $route || in_array($route, self::SKIP_ROUTES, true)) {
            return false;
        }

        foreach (self::SKIP_PREFIXES as $prefix) {
            if (str_starts_with($route, $prefix)) {
                return false;
            }
        }

        $session = $request->session();

        // Sudah ada pesan eksplisit atau sedang menampilkan error validasi.
        if ($session->has('success') || $session->has('warning') || $session->has('error')) {
            return false;
        }

        if ($session->has('errors')) {
            return false;
        }

        return true;
    }

    /**
     * Pesan default mengikuti jenis aksi pada nama route.
     * Dipisah statis supaya mudah diuji tanpa memanggil middleware.
     */
    public static function messageFor(?string $routeName): string
    {
        $action = str_contains((string) $routeName, '.')
            ? substr((string) $routeName, strrpos((string) $routeName, '.') + 1)
            : (string) $routeName;

        return match ($action) {
            'store', 'create', 'save' => __('Saved successfully.'),
            'update', 'edit', 'change' => __('Changes saved successfully.'),
            'destroy', 'delete', 'remove' => __('Deleted successfully.'),
            'accept', 'approve', 'confirm', 'promote' => __('Confirmed successfully.'),
            'reject', 'decline' => __('Rejected successfully.'),
            'cancel' => __('Cancelled successfully.'),
            'send', 'submit', 'reply' => __('Sent successfully.'),
            'upload' => __('Uploaded successfully.'),
            'assign' => __('Assigned successfully.'),
            'restore' => __('Restored successfully.'),
            'archive' => __('Archived successfully.'),
            default => __('Action completed successfully.'),
        };
    }
}
