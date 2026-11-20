<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Support\SessionRevoker;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class PasswordController extends Controller
{
    /**
     * Update the user's password.
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validateWithBag('updatePassword', [
            'current_password' => ['required', 'current_password'],
            'password' => ['required', Password::defaults(), 'confirmed'],
        ]);

        $user = $request->user();

        $user->forceFill([
            'password' => Hash::make($validated['password']),
            'password_changed_at' => now(),
            'remember_token' => Str::random(60),
        ])->save();

        SessionRevoker::othersFor($user, $request->session()->getId());

        return back()->with('status', 'password-updated');
    }
}
