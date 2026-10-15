<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

class SettingsController extends Controller
{
    /**
     * Display the application preference form.
     */
    public function edit(Request $request): View
    {
        return view('settings.edit', [
            'user' => $request->user(),
        ]);
    }

    /**
     * Persist the user's application preferences.
     */
    public function update(Request $request): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'theme' => ['required', 'in:light,dark,system'],
            'accent' => ['sometimes', 'in:ocean,terracotta,purple,emerald'],
            'locale' => ['required', 'in:id,en'],
            'notify_email' => ['sometimes', 'boolean'],
            'notify_system' => ['sometimes', 'boolean'],
            'notify_push' => ['sometimes', 'boolean'],
        ]);

        $user = $request->user();
        $user->preferences = array_merge($user->preferences ?? [], [
            'theme' => $data['theme'],
            'locale' => $data['locale'],
            'notify_email' => $request->boolean('notify_email'),
            'notify_system' => $request->boolean('notify_system'),
            'notify_push' => $request->boolean('notify_push'),
            'accent' => $data['accent'] ?? $user->preference('accent', 'ocean'),
        ]);
        $user->save();

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json(['ok' => true, 'message' => __('Pengaturan berhasil disimpan.')]);
        }

        return Redirect::route('settings.edit')->with('status', 'settings-updated');
    }

    /**
     * Live-sync appearance from the client (no page reload).
     */
    public function appearance(Request $request): JsonResponse
    {
        $data = $request->validate([
            'theme' => ['required', 'in:light,dark,system'],
            'accent' => ['required', 'in:ocean,terracotta,purple,emerald'],
        ]);

        $user = $request->user();
        $user->preferences = array_merge($user->preferences ?? [], [
            'theme' => $data['theme'],
            'accent' => $data['accent'],
        ]);
        $user->save();

        return response()->json(['ok' => true]);
    }

    /**
     * Password-gated area: change password and delete account.
     *
     * Selalu terkunci tiap kunjungan (GET): hanya request tepat setelah
     * konfirmasi sukses (flash sekali pakai) yang melihat isi halaman.
     */
    public function advanced(Request $request): View
    {
        return view('settings.advanced', [
            'user' => $request->user(),
            'unlocked' => (bool) $request->session()->get('advanced_unlocked', false),
        ]);
    }

    /**
     * Konfirmasi password inline untuk membuka halaman advanced.
     */
    public function confirmAdvanced(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        return Redirect::route('settings.advanced')->with('advanced_unlocked', true);
    }
}
