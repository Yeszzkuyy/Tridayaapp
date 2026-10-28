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

        if ($request->user() && $request->user()->roles->isEmpty() && $request->user()->permissions->isEmpty()) {
            return redirect()->route('pending.approval');
        }

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
     * Display the pending approval view for users waiting for role assignment.
     */
    public function pendingApproval(): View|RedirectResponse
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        if ($user && ($user->roles->isNotEmpty() || $user->permissions->isNotEmpty())) {
            return redirect()->route('dashboard');
        }

        return view('auth.pending-approval', compact('user'));
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

            // Skenario 2: Login / Auto-provision via Google
            // Cari akun berdasarkan google_id yang sudah ditautkan ATAU email yang cocok
            $user = \App\Models\User::where('google_id', $googleUser->getId())
                ->orWhere('email', $googleUser->getEmail())
                ->first();

            if (! $user) {
                // Auto-create user baru (tanpa role Spatie)
                $name = $googleUser->getName() ?: ($googleUser->getNickname() ?: explode('@', $googleUser->getEmail())[0]);
                $user = \App\Models\User::create([
                    'name' => $name,
                    'email' => $googleUser->getEmail(),
                    'google_id' => $googleUser->getId(),
                    'google_avatar' => $googleUser->getAvatar(),
                    'password' => \Illuminate\Support\Facades\Hash::make(\Illuminate\Support\Str::random(32)),
                    'email_verified_at' => now(),
                    'role' => 'guest',
                ]);

                // Beritahu seluruh Super Admin secara realtime bahwa ada akun baru yang butuh role
                try {
                    $superAdmins = \App\Models\User::role('super-admin')->get();
                    foreach ($superAdmins as $admin) {
                        $admin->notify(new \App\Notifications\NewUserPendingApprovalNotification($user));
                    }
                } catch (\Throwable $notifEx) {
                    report($notifEx);
                }
            } else {
                // Tautkan google_id / google_avatar jika sebelumnya belum tersimpan
                $updates = [];
                if (! $user->google_id) {
                    $updates['google_id'] = $googleUser->getId();
                }
                if (! $user->google_avatar && $googleUser->getAvatar()) {
                    $updates['google_avatar'] = $googleUser->getAvatar();
                }
                if (! empty($updates)) {
                    $user->update($updates);
                }
            }

            Auth::login($user);
            request()->session()->regenerate();

            if ($user->roles->isEmpty() && $user->permissions->isEmpty()) {
                return redirect()->route('pending.approval');
            }

            return redirect()->intended(route('dashboard', absolute: false));
        } catch (\Throwable $e) {
            report($e);
            return redirect()->route('login')->withErrors([
                'email' => __('Failed to sign in with Google. Please try again.'),
            ]);
        }
    }
}
