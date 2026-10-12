<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validated();

        if ($request->hasFile('avatar')) {
            if ($user->avatar) {
                Storage::disk('public')->delete($user->avatar);
            }
            $data['avatar'] = $this->storeNormalizedAvatar($request->file('avatar'));
        } else {
            unset($data['avatar']);
        }

        $user->fill($data);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        return Redirect::route('settings.advanced')->with('status', 'profile-updated');
    }

    /**
     * Update only the user's avatar.
     */
    public function updateAvatar(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'avatar' => ['required', 'image', 'max:5120', \App\Rules\SecureFile::images()],
        ]);

        $user = $request->user();

        if ($user->avatar) {
            Storage::disk('public')->delete($user->avatar);
        }

        $user->avatar = $this->storeNormalizedAvatar($request->file('avatar'));
        $user->save();

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    /**
     * Simpan avatar sebagai persegi 256px (cover, tengah): cukup untuk
     * semua ukuran tampil (36–112px, retina 2x = 224px). GIF disimpan
     * asli agar animasi tidak rusak; gambar kecil (<=256px) juga
     * disimpan asli supaya tidak blur karena upscale.
     */
    private function storeNormalizedAvatar(UploadedFile $file): string
    {
        $mime = $file->getMimeType();

        if ($mime === 'image/gif') {
            return $file->store('avatars', 'public');
        }

        $src = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($file->getRealPath()),
            'image/png' => @imagecreatefrompng($file->getRealPath()),
            'image/webp' => @imagecreatefromwebp($file->getRealPath()),
            default => false,
        };

        if ($src === false) {
            // finfo bilang gambar tapi GD tak bisa parse (= korup/polyglot):
            // tolak, jangan simpan mentah ke public.
            abort(422, __('File gambar tidak valid atau rusak.'));
        }

        // Koreksi orientasi EXIF (foto HP) sebelum crop.
        if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
            $exif = @exif_read_data($file->getRealPath());
            $src = match ((int) ($exif['Orientation'] ?? 1)) {
                3 => imagerotate($src, 180, 0),
                6 => imagerotate($src, -90, 0),
                8 => imagerotate($src, 90, 0),
                default => $src,
            };
        }

        $w = imagesx($src);
        $h = imagesy($src);
        $side = min($w, $h);

        if ($side <= 256) {
            imagedestroy($src);

            return $file->store('avatars', 'public');
        }

        $dst = imagecreatetruecolor(256, 256);

        if (in_array($mime, ['image/png', 'image/webp'], true)) {
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
        }

        imagecopyresampled($dst, $src, 0, 0, (int) (($w - $side) / 2), (int) (($h - $side) / 2), 256, 256, $side, $side);
        imagedestroy($src);

        $ext = $mime === 'image/png' ? 'png' : ($mime === 'image/webp' ? 'webp' : 'jpg');
        $path = 'avatars/'.Str::random(40).'.'.$ext;

        ob_start();
        match ($ext) {
            'png' => imagepng($dst, null, 6),
            'webp' => imagewebp($dst, null, 85),
            default => imagejpeg($dst, null, 85),
        };
        $encoded = (string) ob_get_clean();
        imagedestroy($dst);

        Storage::disk('public')->put($path, $encoded);

        return $path;
    }

    /**
     * Simpan cover/banner profil: lebar maksimal 1600px (proporsi terjaga),
     * gambar kecil disimpan asli agar tidak blur. GIF disimpan asli
     * agar animasi tidak rusak.
     */
    public function updateCover(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'cover' => ['required', 'image', 'max:8192', \App\Rules\SecureFile::images()],
        ]);

        $user = $request->user();

        if ($user->cover) {
            Storage::disk('public')->delete($user->cover);
        }

        $user->cover = $this->storeNormalizedCover($request->file('cover'));
        $user->save();

        return Redirect::route('profile.edit')->with('status', 'cover-updated');
    }

    /**
     * Normalisasi cover: resize ke lebar <=1600px (tengah atas, rasio asli),
     * agar banner tajam di kartu profil tanpa membebani halaman.
     */
    private function storeNormalizedCover(UploadedFile $file): string
    {
        $mime = $file->getMimeType();

        if ($mime === 'image/gif') {
            return $file->store('covers', 'public');
        }

        $src = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($file->getRealPath()),
            'image/png' => @imagecreatefrompng($file->getRealPath()),
            'image/webp' => @imagecreatefromwebp($file->getRealPath()),
            default => false,
        };

        if ($src === false) {
            abort(422, __('File gambar tidak valid atau rusak.'));
        }

        if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
            $exif = @exif_read_data($file->getRealPath());
            $src = match ((int) ($exif['Orientation'] ?? 1)) {
                3 => imagerotate($src, 180, 0),
                6 => imagerotate($src, -90, 0),
                8 => imagerotate($src, 90, 0),
                default => $src,
            };
        }

        $w = imagesx($src);
        $h = imagesy($src);

        if ($w <= 1600) {
            imagedestroy($src);

            return $file->store('covers', 'public');
        }

        $targetW = 1600;
        $targetH = (int) round($h * ($targetW / $w));
        $dst = imagecreatetruecolor($targetW, $targetH);

        if (in_array($mime, ['image/png', 'image/webp'], true)) {
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
        }

        imagecopyresampled($dst, $src, 0, 0, 0, 0, $targetW, $targetH, $w, $h);
        imagedestroy($src);

        $ext = $mime === 'image/png' ? 'png' : ($mime === 'image/webp' ? 'webp' : 'jpg');
        $path = 'covers/'.Str::random(40).'.'.$ext;

        ob_start();
        match ($ext) {
            'png' => imagepng($dst, null, 6),
            'webp' => imagewebp($dst, null, 85),
            default => imagejpeg($dst, null, 85),
        };
        $encoded = (string) ob_get_clean();
        imagedestroy($dst);

        Storage::disk('public')->put($path, $encoded);

        return $path;
    }

    /**
     * Hapus cover/banner profil.
     */
    public function removeCover(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->cover) {
            Storage::disk('public')->delete($user->cover);
            $user->cover = null;
            $user->save();
        }

        return Redirect::route('profile.edit')->with('status', 'cover-removed');
    }

    /**
     * Remove the user's avatar.
     */
    public function removeAvatar(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->avatar) {
            Storage::disk('public')->delete($user->avatar);
            $user->avatar = null;
            $user->save();
        }

        return Redirect::route('profile.edit')->with('status', 'avatar-removed');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        if ($reason = $user->deletionBlockReason()) {
            return Redirect::route('profile.edit')->withErrors(['password' => $reason], 'userDeletion');
        }

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
