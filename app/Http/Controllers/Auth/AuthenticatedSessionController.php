<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }

    /**
     * Redirect the user to the Google authentication page.
     */
    public function redirectToGoogle(): RedirectResponse
    {
        if (!config('services.google.client_id')) {
            return redirect()->route('login')->with('status', __('Google Workspace OAuth is being prepared. Please sign in with your email and password.'));
        }

        return \Laravel\Socialite\Facades\Socialite::driver('google')->redirect();
    }

    /**
     * Obtain the user information from Google.
     */
    public function handleGoogleCallback(): RedirectResponse
    {
        if (!config('services.google.client_id')) {
            return redirect()->route('login');
        }

        try {
            $googleUser = \Laravel\Socialite\Facades\Socialite::driver('google')->user();

            // Skenario 1: Karyawan sedang login dan menautkan akun Google dari profil
            if (Auth::check()) {
                /** @var \App\Models\User $currentUser */
                $currentUser = Auth::user();
                $currentUser->update([
                    'google_id' => $googleUser->getId(),
                    'google_avatar' => $googleUser->getAvatar(),
                ]);

                return redirect()->route('profile.edit')->with('status', 'google-linked');
            }

            // Skenario 2: Login via Google
            // Cari akun berdasarkan google_id yang sudah ditautkan ATAU email yang cocok
            $user = \App\Models\User::where('google_id', $googleUser->getId())
                ->orWhere('email', $googleUser->getEmail())
                ->first();

            if (! $user) {
                return redirect()->route('login')->withErrors([
                    'email' => __('No account found matching Google account :email. Please sign in with your password first or contact your administrator.', [
                        'email' => $googleUser->getEmail(),
                    ]),
                ]);
            }

            // Tautkan google_id secara otomatis jika sebelumnya belum tersimpan
            if (! $user->google_id) {
                $user->update([
                    'google_id' => $googleUser->getId(),
                    'google_avatar' => $googleUser->getAvatar(),
                ]);
            }

            Auth::login($user);
            request()->session()->regenerate();

            return redirect()->intended(route('dashboard', absolute: false));
        } catch (\Throwable $e) {
            report($e);
            return redirect()->route('login')->withErrors([
                'email' => __('Failed to sign in with Google. Please try again.'),
            ]);
        }
    }
}
