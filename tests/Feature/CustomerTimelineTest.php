<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Project;
use App\Models\SalesSchedule;
use App\Models\Lead;
use App\Models\FollowUp;
use App\Models\User;
use App\Models\WorkType;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CustomerTimelineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    private function sales(): User
    {
        $user = User::factory()->create(['name' => 'Sales Timeline']);
        $user->assignRole('sales');

        return $user;
    }

    public function test_timeline_gabung_followup_meeting_urut_waktu_serta_tab_lain_tampil(): void
    {
        $sales = $this->sales();
        $this->actingAs($sales);

        $customer = Customer::create(['name' => 'PT Timeline', 'pt_group' => 'NTI']);
        $workType = WorkType::create(['name' => 'Instalasi']);
        $project = Project::create([
            'customer_id' => $customer->id,
            'work_type_id' => $workType->id,
            'project_name' => 'Project Timeline',
        ]);
        $lead = Lead::create([
            'customer_id' => $customer->id,
            'pt_group' => 'NTI',
            'segment' => 'vendor',
            'status' => 'hot',
        ]);

        FollowUp::create([
            'customer_id' => $customer->id,
            'description' => 'Telepon minggu lalu',
            'type' => 'call',
            'follow_up_date' => now()->subWeek()->toDateString(),
            'created_by' => $sales->id,
        ]);
        SalesSchedule::create([
            'lead_id' => $lead->id,
            'assigned_to' => $sales->id,
            'created_by' => $sales->id,
            'title' => 'Visit kantor',
            'type' => 'visit',
            'start_at' => now()->subDays(2),
            'status' => 'done',
        ]);
        Storage::fake('private');
        $this->post(route('project-documents.store', $project), [
            'file' => UploadedFile::fake()->create('boq.pdf', 100, 'application/pdf'),
        ])->assertRedirect();
        $this->post(route('customer-contacts.store', $customer), [
            'name' => 'Budi PIC',
            'position' => 'Manager',
        ])->assertRedirect();

        $response = $this->get(route('customers.show', $customer));
        $response->assertOk();
        $response->assertSee('Telepon minggu lalu', false);
        $response->assertSee('Visit kantor', false);
        $response->assertSee('boq.pdf', false);
        $response->assertSee('Budi PIC', false);

        // PIC baru tercatat pelakunya.
        $this->assertDatabaseHas('customer_contacts', [
            'name' => 'Budi PIC',
            'created_by' => $sales->id,
        ]);
    }

    public function test_timeline_kosong_tidak_error(): void
    {
        $sales = $this->sales();
        $this->actingAs($sales);

        $customer = Customer::create(['name' => 'PT Kosong', 'pt_group' => 'NTI']);

        $this->get(route('customers.show', $customer))->assertOk();
    }

    public function test_timeline_hanya_project_followup_meeting(): void
    {
        $sales = $this->sales();
        $this->actingAs($sales);

        $customer = Customer::create(['name' => 'PT Lingkup', 'pt_group' => 'NTI']);
        $workType = WorkType::create(['name' => 'Instalasi']);
        Project::create([
            'customer_id' => $customer->id,
            'work_type_id' => $workType->id,
            'project_name' => 'Project Lingkup',
        ]);
        FollowUp::create([
            'customer_id' => $customer->id,
            'description' => 'Telepon lingkup',
            'type' => 'call',
            'follow_up_date' => now()->toDateString(),
            'created_by' => $sales->id,
        ]);
        Storage::fake('private');
        $this->post(route('customer-documents.store', $customer), [
            'file' => UploadedFile::fake()->create('lingkup.pdf', 100, 'application/pdf'),
        ])->assertRedirect();
        $this->post(route('customer-contacts.store', $customer), [
            'name' => 'PIC Lingkup',
        ])->assertRedirect();

        $response = $this->get(route('customers.show', $customer));
        $response->assertOk();

        // Fokus ke segmen tab Activity (timeline) saja.
        $html = $response->getContent();
        $activitySeg = substr($html, (int) strpos($html, 'Activity Timeline'));

        // Ada di timeline.
        $this->assertStringContainsString('Projects Created', $activitySeg);
        $this->assertStringContainsString('Telepon lingkup', $activitySeg);

        // Tidak ada di timeline (tetap tampil di tab masing-masing).
        $this->assertStringNotContainsString('PIC Lingkup', $activitySeg);
        $this->assertStringNotContainsString('lingkup.pdf', $activitySeg);
        $this->assertStringNotContainsString(__('Invoice created'), $activitySeg);
    }

    public function test_tab_tidak_bersarang_di_dalam_tab_lain(): void
    {
        $sales = $this->sales();
        $this->actingAs($sales);

        $customer = Customer::create(['name' => 'PT Tab', 'pt_group' => 'NTI']);

        $html = $this->get(route('customers.show', $customer))->assertOk()->getContent();

        libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $dom->loadHTML($html);
        $xp = new \DOMXPath($dom);

        $tabDivs = $xp->query("//div[starts-with(@x-show, 'tab ===')]");
        $this->assertGreaterThan(0, $tabDivs->length, 'Ketemu div tab di halaman customer.');

        foreach ($tabDivs as $div) {
            $nested = [];
            $parent = $div->parentNode;
            while ($parent instanceof \DOMElement) {
                if ($parent->hasAttribute('x-show') && str_contains($parent->getAttribute('x-show'), 'tab ===')) {
                    $nested[] = $parent->getAttribute('x-show');
                }
                $parent = $parent->parentNode;
            }

            $this->assertSame(
                [],
                $nested,
                "Tab '{$div->getAttribute('x-show')}' bersarang di dalam " . implode(', ', $nested)
                . ' — kontennya tidak akan pernah tampil.'
            );
        }

        libxml_clear_errors();
    }
}
