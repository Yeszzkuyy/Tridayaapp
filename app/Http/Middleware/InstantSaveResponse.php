<?php

namespace App\Http\Middleware;

use App\Support\InstantSave;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Simpan cepat: ubah redirect + flash menjadi JSON untuk request AJAX dari
 * modul Estimator, Produk/Pricelist, dan Sales Request.
 *
 * Tujuannya supaya front-end bisa menampilkan toast sukses/gagal SEBELUM
 * halaman berikutnya selesai dimuat. Pesan flash langsung dihapus dari sesi
 * agar tidak muncul dua kali setelah navigasi.
 *
 * Middleware ini harus berada DI LUAR FlashActionToast: flash bawaan baru
 * dipasang saat response kembali, lalu dibaca di sini.
 */
class InstantSaveResponse
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $this->shouldConvert($request, $response)) {
            return $response;
        }

        /** @var RedirectResponse $response */
        $session = $request->session();
        $variant = match (true) {
            $session->has('error') => 'error',
            $session->has('warning') => 'warning',
            default => 'success',
        };

        $message = $session->get($variant) ?? $session->get('success');
        $session->forget(['success', 'warning', 'error']);

        return new JsonResponse([
            'ok' => $variant !== 'error',
            'variant' => $variant,
            'message' => $message !== null
                ? (string) $message
                : __('Saved successfully.'),
            'redirect' => $response->getTargetUrl(),
        ]);
    }

    private function shouldConvert(Request $request, Response $response): bool
    {
        if (! $response instanceof RedirectResponse) {
            return false;
        }

        if ($response->headers->has('X-No-Instant-Save')) {
            return false;
        }

        if ($request->isMethodSafe() || ! $request->expectsJson()) {
            return false;
        }

        return InstantSave::enabledFor($request);
    }
}
