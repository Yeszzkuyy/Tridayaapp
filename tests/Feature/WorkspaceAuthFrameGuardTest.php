<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Sesi habis -> request dari panel tab workspace dialihkan ke halaman auth.
 * Halaman auth tidak boleh hidup di dalam iframe panel (login kecil dengan
 * sidebar/navbar induk masih menempel sampai user me-refresh manual).
 *
 * Pengaman terdiri dari dua lapis:
 * 1. Script di <head> semua halaman auth: langsung pindah ke halaman penuh
 *    bila terdeteksi berjalan di dalam iframe.
 * 2. Shell workspace: setiap panel selesai memuat, URL auth memicu breakout
 *    ke halaman penuh (jaring pengaman bila script halaman tidak jalan).
 */
class WorkspaceAuthFrameGuardTest extends TestCase
{
    private const AUTH_VIEWS = [
        'auth/login.blade.php',
        'auth/register.blade.php',
        'auth/forgot-password.blade.php',
        'auth/reset-password.blade.php',
        'auth/verify-email.blade.php',
        'auth/confirm-password.blade.php',
        'auth/pending-approval.blade.php',
    ];

    public function test_every_auth_view_includes_the_frame_guard(): void
    {
        foreach (self::AUTH_VIEWS as $view) {
            $content = file_get_contents(resource_path('views/'.$view));

            $this->assertStringContainsString(
                "@include('layouts.partials.workspace-frame-guard')",
                $content,
                $view.' harus memuat pengaman iframe workspace.',
            );
        }
    }

    public function test_login_page_renders_the_frame_guard_script(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('data-workspace-auth-guard', false)
            ->assertSee("type: 'login'", false);
    }

    public function test_workspace_shell_breaks_out_when_a_tab_lands_on_auth_page(): void
    {
        $js = file_get_contents(resource_path('js/workspace-tabs.js'));

        $this->assertStringContainsString('breakOutOfWorkspace', $js);
        $this->assertStringContainsString('isAuthPath', $js);

        foreach (['/login', '/verify-email', '/pending-approval'] as $path) {
            $this->assertStringContainsString("'{$path}'", $js);
        }
    }
}
