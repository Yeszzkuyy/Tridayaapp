<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    /**
     * Handle an incoming request.
     *
     * Isolates authenticated users who do not have any Spatie roles yet
     * to the pending approval screen until an administrator assigns them a role.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user) {
            $hasNoAccess = $user->roles->isEmpty() && $user->permissions->isEmpty();

            if ($hasNoAccess) {
                // Rute yang diizinkan saat belum ada role (auth lifecycle, legal, pending, logout)
                if ($request->routeIs(
                    'pending.approval',
                    'logout',
                    'privacy.policy',
                    'terms.service',
                    'verification.*',
                    'password.*',
                )) {
                    return $next($request);
                }

                if ($request->isMethod('GET') && ! $request->expectsJson()) {
                    return redirect()->route('pending.approval');
                }

                abort(403, __('Your account is pending role assignment by an administrator.'));
            }

            // Jika user SUDAH memiliki role/permission tapi membuka halaman pending approval
            if ($request->routeIs('pending.approval')) {
                return redirect()->route('dashboard');
            }
        }

        return $next($request);
    }
}
