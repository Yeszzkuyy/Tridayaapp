<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Instalasi;
use App\Models\Project;
use App\Models\User;
use App\Models\WorkType;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tombol "Buat Dokumen BAST" di menu Instalasi: dropdown 4 pilihan template
 * Google Drive per perusahaan (MGK/NTI/TPS/WANI) dari config services.bast-templates.
 * Murni tautan statis yang dibuka di tab baru — tanpa Google Drive API.
 */
class InstalasiBastButtonTest extends TestCase
{
    use RefreshDatabase;

    private const TEMPLATES = [
        'MGK' => 'https://docs.google.com/document/d/aaa-mgk/copy',
        'NTI' => 'https://docs.google.com/document/d/bbb-nti/copy',
        'TPS' => 'https://docs.google.com/document/d/ccc-tps/copy',
        'WANI' => 'https://docs.google.com/document/d/ddd-wani/copy',
    ];

    private function userWithRole(string $role): User
    {
        $this->seed(RoleAndPermissionSeeder::class);
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function makeInstalasi(): Instalasi
    {
        // ProjectObserver menulis ProjectActivity dengan auth()->id(); sediakan
        // aktor bila belum ada agar fixture tidak melanggar NOT NULL.
        if (! auth()->check()) {
            $this->actingAs(User::factory()->create());
        }

        $customer = Customer::create(['name' => 'PT BAST Uji']);
        $workType = WorkType::create(['name' => 'Instalasi']);
        $project = Project::create([
            'customer_id' => $customer->id,
            'work_type_id' => $workType->id,
            'project_name' => 'Project BAST Uji',
        ]);

        return Instalasi::create([
            'project_id' => $project->id,
            'location' => 'Jakarta',
            'status' => 'scheduled',
        ]);
    }

    private function useTemplates(array $overrides = []): void
    {
        config(['services.bast-templates' => array_merge(self::TEMPLATES, $overrides)]);
    }

    public function test_index_shows_bast_dropdown_with_four_company_templates(): void
    {
        $this->useTemplates();
        $technician = $this->userWithRole('technician');

        $html = $this->actingAs($technician)->get(route('teknisi.instalasis.index'))->assertOk()->getContent();

        $this->assertStringContainsString('Create BAST Document', $html);
        foreach (self::TEMPLATES as $company => $url) {
            $this->assertStringContainsString($url, $html);
            $this->assertStringContainsString('>'.$company.'</span>', $html);
        }
        // Selalu dibuka di tab baru supaya halaman website tetap terbuka.
        $this->assertStringContainsString('target="_blank"', $html);
        $this->assertStringContainsString('rel="noopener"', $html);
        // Item pilihan punya animasi hover halus (slide + panah fade-in).
        $this->assertStringContainsString('group-hover/bast-item', $html);
    }

    public function test_show_page_shows_bast_dropdown_with_four_company_templates(): void
    {
        $this->useTemplates();
        $technician = $this->userWithRole('technician');
        $instalasi = $this->makeInstalasi();

        $html = $this->actingAs($technician)->get(route('teknisi.instalasis.show', $instalasi))->assertOk()->getContent();

        $this->assertStringContainsString('Create BAST Document', $html);
        foreach (self::TEMPLATES as $company => $url) {
            $this->assertStringContainsString($url, $html);
            $this->assertStringContainsString('>'.$company.'</span>', $html);
        }
    }

    public function test_template_without_link_is_hidden(): void
    {
        $this->useTemplates(['TPS' => null]);
        $technician = $this->userWithRole('technician');

        $html = $this->actingAs($technician)->get(route('teknisi.instalasis.index'))->assertOk()->getContent();

        $this->assertStringNotContainsString('>TPS</span>', $html);
        $this->assertStringContainsString('>MGK</span>', $html);
        $this->assertStringContainsString('>NTI</span>', $html);
        $this->assertStringContainsString('>WANI</span>', $html);
    }

    public function test_button_hidden_when_all_templates_empty(): void
    {
        config(['services.bast-templates' => [
            'MGK' => null, 'NTI' => null, 'TPS' => null, 'WANI' => null,
        ]]);
        $technician = $this->userWithRole('technician');

        $html = $this->actingAs($technician)->get(route('teknisi.instalasis.index'))->assertOk()->getContent();

        $this->assertStringNotContainsString('Create BAST Document', $html);
    }
}
