<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\PageContext;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * Pengujian mode Multi-Tab Workspace (keep-alive di navbar):
 * - Request biasa merender shell penuh dengan barisan tab (#workspace-tabs)
 *   dan wadah iframe (#workspace-stage).
 * - Request embed (?embed=1, X-Workspace-Embed: 1, atau Sec-Fetch-Dest: iframe)
 *   merender layout embed tanpa sidebar/navbar/Notification Center.
 * - Keamanan (login, role gate, X-Frame-Options: SAMEORIGIN) tetap berlaku.
 * - Kill switch config('workspace.enabled' => false) mengembalikan pill + breadcrumb.
 */
class WorkspaceTabsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    private function adminUser(): User
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }

    public function test_normal_request_renders_workspace_shell_with_tab_bar(): void
    {
        $admin = $this->adminUser();

        $res = $this->actingAs($admin)->get(route('admin.purchasing.index'));

        $res->assertOk()
            ->assertSee('id="workspace-tabs"', false)
            ->assertSee('id="workspace-stage"', false)
            ->assertSee('id="sidebar"', false)
            ->assertDontSee('class="app-header-badge', false);
    }

    public function test_embed_query_renders_embed_layout_without_sidebar_or_header(): void
    {
        $admin = $this->adminUser();

        $res = $this->actingAs($admin)->get(route('admin.purchasing.index', ['embed' => 1]));

        $res->assertOk()
            ->assertSee('data-embed="1"', false)
            ->assertSee(__('Purchasing Handoff'))
            ->assertDontSee('id="sidebar"', false)
            ->assertDontSee('id="workspace-tabs"', false)
            ->assertDontSee('data-notification-center', false)
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN');
    }

    public function test_sec_fetch_dest_iframe_triggers_embed_layout_automatically(): void
    {
        $admin = $this->adminUser();

        $res = $this->actingAs($admin)
            ->withHeaders(['Sec-Fetch-Dest' => 'iframe'])
            ->get(route('admin.hub'));

        $res->assertOk()
            ->assertSee('data-embed="1"', false)
            ->assertDontSee('id="sidebar"', false);
    }

    public function test_embed_mode_still_enforces_authentication_and_authorization(): void
    {
        // Guest tetap diarahkan ke login
        $this->get(route('admin.hub', ['embed' => 1]))
            ->assertRedirect(route('login'));

        // Role yang tidak punya izin tetap ditolak (403)
        $marketing = User::factory()->create();
        $marketing->assignRole('marketing');

        $this->actingAs($marketing)
            ->get(route('admin.hub', ['embed' => 1]))
            ->assertForbidden();
    }

    public function test_disabling_workspace_restores_classic_badge_and_ignores_embed(): void
    {
        config(['workspace.enabled' => false]);
        $admin = $this->adminUser();

        $res = $this->actingAs($admin)->get(route('admin.purchasing.index', ['embed' => 1]));

        $res->assertOk()
            ->assertSee('app-header-badge', false)
            ->assertDontSee('id="workspace-tabs"', false)
            ->assertDontSee('data-embed="1"', false);
    }

    public function test_page_context_builds_contextual_department_tab_title(): void
    {
        $admin = $this->adminUser();
        $req = Request::create('/admin/purchasing', 'GET');
        $req->setUserResolver(fn () => $admin);
        $route = app('router')->getRoutes()->match($req);
        $req->setRouteResolver(fn () => $route);

        $ctx = PageContext::forRequest($req);

        $this->assertSame('Admin • '.__('Customer PO'), $ctx['tabTitle']);
        $this->assertSame('receipt', $ctx['sectionIcon']);
    }
}
