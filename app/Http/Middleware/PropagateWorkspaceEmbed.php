<?php

namespace App\Http\Middleware;

use App\Support\PageContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Menjaga redirect di dalam workspace tetap memakai layout embed.
 *
 * Request dari iframe bisa kehilangan header Sec-Fetch-Dest (browser lama,
 * proxy, atau navigasi history). Karena itu penanda embed juga dibawa lewat
 * query/hidden input; middleware ini menyalinnya ke Location redirect agar
 * halaman setelah simpan/proses tidak berubah menjadi layout penuh.
 */
class PropagateWorkspaceEmbed
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! PageContext::isEmbed($request) || ! $response->isRedirect()) {
            return $response;
        }

        $location = $response->headers->get('Location');

        if (! $location) {
            return $response;
        }

        $parts = parse_url($location);

        if ($parts === false) {
            return $response;
        }

        // Redirect ke domain lain (mis. OAuth) tidak boleh diubah.
        if (isset($parts['host']) && strcasecmp($parts['host'], $request->getHost()) !== 0) {
            return $response;
        }

        $query = [];

        if (! empty($parts['query'])) {
            parse_str($parts['query'], $query);
        }

        $query['embed'] = '1';

        $path = $parts['path'] ?? '/';

        if (! isset($parts['host']) && ! str_starts_with($path, '/')) {
            $path = '/'.ltrim($path, '/');
        }

        $newLocation = $path.'?'.http_build_query($query);

        if (isset($parts['fragment'])) {
            $newLocation .= '#'.$parts['fragment'];
        }

        if (isset($parts['scheme'], $parts['host'])) {
            $newLocation = $parts['scheme'].'://'.$parts['host']
                .(isset($parts['port']) ? ':'.$parts['port'] : '')
                .$newLocation;
        } elseif (str_starts_with($location, '//') && isset($parts['host'])) {
            $newLocation = '//'.$parts['host'].$newLocation;
        }

        $response->headers->set('Location', $newLocation);

        return $response;
    }
}
