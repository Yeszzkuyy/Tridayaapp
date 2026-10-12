<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RoleMatrixTest extends TestCase
{
    use RefreshDatabase;

    private function loginAs(string $role): User
    {
        $this->seed(RoleAndPermissionSeeder::class);
        $user = User::factory()->create();
        $user->assignRole($role);
        return $user;
    }

    public function test_deleted_hub_roles_are_gone(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);

        $this->assertFalse(Role::whereIn('name', ['manage-marketing', 'manage-technical', 'manage-admin'])->exists());
        $this->assertTrue(Role::whereIn('name', ['management', 'lead-marketing', 'prakerin-technician', 'prakerin-admin'])->count() === 4);
    }

    public function test_management_sees_all_recaps_but_cannot_mutate(): void
    {
        $this->actingAs($this->loginAs('management'));

        foreach (['marketing.dashboard', 'sales.dashboard', 'teknisi.dashboard', 'monitoring.index', 'leads.monitoring', 'manage-sales.index'] as $route) {
            $this->get(route($route))->assertOk();
        }

        $this->post(route('leads.store'), [])->assertForbidden();
        $this->post(route('admin.invoices.store'), [])->assertForbidden();
        $this->post(route('sales.meetings.store'), [])->assertForbidden();
    }

    public function test_cross_division_read_only(): void
    {
        // Sales baca admin, tidak bisa tulis.
        $this->actingAs($this->loginAs('sales'));
        $this->get(route('admin.invoices.index'))->assertOk();
        $this->post(route('admin.invoices.store'), [])->assertForbidden();

        // Admin baca sales, tidak bisa tulis.
        $this->actingAs($this->loginAs('admin'));
        $this->get(route('sales.dashboard'))->assertOk();
        $this->post(route('sales.meetings.store'), [])->assertForbidden();

        // Lead technician baca sales + admin.
        $this->actingAs($this->loginAs('lead-technician'));
        $this->get(route('sales.dashboard'))->assertOk();
        $this->get(route('admin.invoices.index'))->assertOk();
    }

    public function test_lead_marketing_reads_technician_documents(): void
    {
        $this->actingAs($this->loginAs('lead-marketing'));

        $this->get(route('marketing.dashboard'))->assertOk();
        $this->get(route('teknisi.documents.index'))->assertOk();
        $this->post(route('teknisi.surveys.store'), [])->assertForbidden();
    }

    public function test_prakerin_read_only_in_own_division(): void
    {
        $this->actingAs($this->loginAs('prakerin-technician'));
        $this->get(route('teknisi.dashboard'))->assertOk();
        $this->post(route('teknisi.surveys.store'), [])->assertForbidden();

        $this->actingAs($this->loginAs('prakerin-admin'));
        $this->get(route('admin.invoices.index'))->assertOk();
        $this->post(route('admin.invoices.store'), [])->assertForbidden();
    }

    public function test_pure_management_sidebar_shows_only_recap_items(): void
    {
        $html = $this->actingAs($this->loginAs('management'))
            ->get(route('dashboard'))
            ->assertOk()
            ->getContent();

        foreach ([
            '/teknisi/dashboard', '/projects', '/teknisi/jadwal', '/teknisi/instalasis',
            '/marketing/dashboard', '/leads', '/partners',
            '/sales/dashboard',
        ] as $link) {
            $this->assertStringContainsString($link, $html, "$link harus terlihat untuk management");
        }

        foreach ([
            '/teknisi/surveys', '/teknisi/sizing-projects', '/teknisi/request-hargas',
            '/teknisi/documents', '/whatsapp-center', '/leads/pipeline',
            '/sales/my-leads', '/leads/monitoring',
        ] as $link) {
            $this->assertStringNotContainsString($link, $html, "$link harus disembunyikan untuk management");
        }
    }

    public function test_management_cannot_mutate_listed_menus(): void
    {
        $this->actingAs($this->loginAs('management'));

        $this->post(route('teknisi.instalasis.store'), [])->assertForbidden();
        $this->post(route('partners.store'), [])->assertForbidden();
        $this->post(route('sales.follow-ups.store'), [])->assertForbidden();
    }
    public function test_multi_role_management_sales_works_sales_but_recap_others(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);
        $user = User::factory()->create();
        $user->assignRole(['management', 'sales']);

        $html = $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->getContent();

        // Sales: menu kerja penuh (own-lead).
        foreach (['/sales/my-leads'] as $link) {
            $this->assertStringContainsString($link, $html);
        }
        // Teknisi & marketing: recap saja.
        foreach (['/teknisi/dashboard', '/projects', '/marketing/dashboard', '/leads', '/partners'] as $link) {
            $this->assertStringContainsString($link, $html);
        }
        foreach (['/teknisi/surveys', '/whatsapp-center', '/leads/monitoring'] as $link) {
            $this->assertStringNotContainsString($link, $html);
        }
    }

    public function test_ceo_and_inside_sales_unchanged(): void
    {
        $this->actingAs($this->loginAs('ceo'));
        $this->get(route('monitoring.index'))->assertOk();
        $this->post(route('leads.store'), [])->assertForbidden();

        $this->actingAs($this->loginAs('inside-sales'));
        $this->get(route('lead-tasks.index'))->assertOk();
    }
}
