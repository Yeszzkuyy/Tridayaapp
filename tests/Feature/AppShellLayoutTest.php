<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Mengunci perilaku app shell (§7.1/§7.2):
 * - Shell luar terkunci ke viewport, scroll hanya di dalam iframe konten.
 * - Rail sidebar tidak boleh menonjol keluar viewport (scroll horizontal).
 * - Scrollbar nav sidebar disembunyikan walau daftar menu tetap bisa digulir.
 */
class AppShellLayoutTest extends TestCase
{
    use RefreshDatabase;

    private function layoutSource(): string
    {
        return file_get_contents(resource_path('views/layouts/app.blade.php'));
    }

    public function test_app_shell_is_locked_to_viewport_and_content_area_owns_the_scroll(): void
    {
        $layout = $this->layoutSource();

        $this->assertStringContainsString('html,body{height:100%;overflow:hidden}', $layout);
        $this->assertStringContainsString(
            '.app-wrapper{display:flex;width:100%;height:100vh;height:100dvh;min-height:0;overflow:hidden}',
            $layout,
        );
        $this->assertStringContainsString(
            '.workspace-stage{display:flex;flex-direction:column;flex:1 1 0;min-height:0;min-width:0;overflow:hidden}',
            $layout,
        );
        $this->assertStringContainsString(
            '.workspace-pane{position:relative;display:flex;flex-direction:column;flex:1 1 0;min-width:0;min-height:0;overflow:hidden}',
            $layout,
        );
        $this->assertStringContainsString(
            '.workspace-pane iframe{flex:1 1 0;min-height:0;width:100%;height:100%;display:block}',
            $layout,
        );
    }

    public function test_sidebar_rail_is_fixed_inside_the_viewport(): void
    {
        $layout = $this->layoutSource();

        $this->assertStringContainsString(
            '.sb-rail{position:fixed;top:0;bottom:0;left:var(--sb-w);',
            $layout,
        );
        $this->assertStringNotContainsString('.sb-rail{position:absolute;', $layout);
    }

    public function test_sidebar_navigation_hides_its_scrollbar_but_stays_scrollable(): void
    {
        $admin = $this->makeUserWithRole();

        $res = $this->actingAs($admin)->get(route('dashboard'));

        $res->assertOk();
        $html = $res->getContent();
        $this->assertMatchesRegularExpression(
            '/id="sidebar-navigation"[^>]*class="[^"]*no-scrollbar[^"]*overflow-y-auto[^"]*"/',
            $html,
        );

        $css = file_get_contents(resource_path('css/sidebar-nav.css'));
        $this->assertStringContainsString('scrollbar-width: none;', $css);
        $this->assertStringContainsString('#sidebar-navigation::-webkit-scrollbar', $css);
        $this->assertStringNotContainsString('scrollbar-gutter: stable;', $css);
    }
}
