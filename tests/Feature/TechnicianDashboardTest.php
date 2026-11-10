<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Lead;
use App\Models\Project;
use App\Models\ProjectActivity;
use App\Models\ProjectStatus;
use App\Models\Survey;
use App\Models\TechnicalRequest;
use App\Models\TechnicianSchedule;
use App\Models\User;
use App\Models\WorkType;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TechnicianDashboardTest extends TestCase
{
    use RefreshDatabase;

    private function authorizedUser(): User
    {
        $this->seed(RoleAndPermissionSeeder::class);
        $user = User::factory()->create();
        $user->givePermissionTo('view-technician');

        return $user;
    }

    private function projectWith(string $name, int $statusId): Project
    {
        $customer = Customer::create(['name' => 'PT ABC Indonesia']);
        $workType = WorkType::create(['name' => 'Instalasi']);

        return Project::create([
            'customer_id' => $customer->id,
            'work_type_id' => $workType->id,
            'project_status_id' => $statusId,
            'pic_engineer' => 'Naufal',
            'project_name' => $name,
        ]);
    }

    public function test_dashboard_shows_stats_progress_project_activity_and_active_technician(): void
    {
        $user = $this->authorizedUser();

        $naufal = User::factory()->create(['name' => 'Naufal']);
        $naufal->assignRole('technician');
        $budi = User::factory()->create(['name' => 'Budi']);
        $budi->assignRole('technician');

        $open = ProjectStatus::create(['name' => 'Open', 'color' => 'blue', 'sort_order' => 1]);
        $done = ProjectStatus::create(['name' => 'Done', 'color' => 'green', 'sort_order' => 2]);

        $this->actingAs($user);
        $project = $this->projectWith('Instalasi CCTV', $open->id);
        $this->projectWith('Maintenance Server', $done->id);

        ProjectActivity::create([
            'project_id' => $project->id,
            'user_id' => $naufal->id,
            'activity_date' => now()->subHour(),
            'title' => 'menambahkan catatan',
        ]);

        $this->actingAs($user)
            ->get(route('teknisi.dashboard'))
            ->assertOk()
            ->assertSee(__('Dashboard Teknisi'))
            ->assertSee(__('Total Teknisi'))
            ->assertSee(__('Teknisi Aktif'))
            ->assertSee(__('Project Berjalan'))
            ->assertSee(__('Project Selesai'))
            ->assertSee(__('Progress Pekerjaan'))
            ->assertSee('Instalasi CCTV')
            ->assertSee('PT ABC Indonesia')
            ->assertSee('Maintenance Server')
            ->assertSee('menambahkan catatan')
            ->assertSee(__('Buka Jadwal'))
            ->assertDontSee('Jadwal Hari Ini');
    }

    public function test_technician_matched_despite_case_whitespace_and_support_list(): void
    {
        $user = $this->authorizedUser();

        $andi = User::factory()->create(['name' => 'Andi Pratama']);
        $andi->assignRole('technician');
        User::factory()->create(['name' => 'Sari'])->assignRole('technician');

        $open = ProjectStatus::create(['name' => 'Open', 'color' => 'blue', 'sort_order' => 1]);

        $this->actingAs($user);

        // pic_engineer beda kapital/spasi, support_technicians berupa daftar koma
        Project::create([
            'customer_id' => Customer::create(['name' => 'PT ABC'])->id,
            'work_type_id' => WorkType::create(['name' => 'Instalasi'])->id,
            'project_status_id' => $open->id,
            'pic_engineer' => ' andi pratama ',
            'support_technicians' => 'Rudi, Sari ,Joko',
            'project_name' => 'Instalasi Jaringan',
        ]);

        $this->actingAs($user)
            ->get(route('teknisi.dashboard'))
            ->assertOk()
            ->assertSee('Andi Pratama')
            ->assertSee('Sari')
            ->assertSee('Instalasi Jaringan');
    }

    public function test_technician_without_running_project_is_not_active(): void
    {
        $user = $this->authorizedUser();

        $inactive = User::factory()->create(['name' => 'Dedi']);
        $inactive->assignRole('technician');

        $this->actingAs($user)
            ->get(route('teknisi.dashboard'))
            ->assertOk()
            ->assertSee(__('Teknisi Aktif'))
            ->assertSee(__('Belum ada').' '.__('teknisi aktif'));
    }

    public function test_technician_dashboard_removes_filler_text_fixes_grammar_and_deduplicates_activity(): void
    {
        $user = $this->authorizedUser();

        $naufal = User::factory()->create(['name' => 'Naufal']);
        $naufal->assignRole('technician');

        $open = ProjectStatus::create(['name' => 'Open', 'color' => 'blue', 'sort_order' => 1]);

        $this->actingAs($user);
        $project = $this->projectWith('Upgrade Core Switch', $open->id);

        // Simulate duplicate legacy log entry for the same project creation
        ProjectActivity::create([
            'project_id' => $project->id,
            'user_id' => $naufal->id,
            'activity_date' => now(),
            'title' => 'Project Dibuat',
        ]);

        $this->actingAs($user)
            ->get(route('teknisi.dashboard'))
            ->assertOk()
            ->assertSee('1 '.__('ongoing project'))
            ->assertDontSee('1 '.__('ongoing projects'))
            ->assertSee('1 '.__('Recent Activities'))
            ->assertSee(__('Created a project'))
            ->assertDontSee('Project Dibuat')
            ->assertDontSee('Latest figures from technician operational data.')
            ->assertDontSee('Technical Division Overview')
            ->assertDontSee('Project distribution by status.');
    }

    public function test_manual_schedule_marks_technician_active_and_shows_in_field_jobs(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);
        $rina = User::factory()->create(['name' => 'Rina']);
        $rina->assignRole('technician');

        $schedule = TechnicianSchedule::create([
            'user_id' => $rina->id,
            'title' => 'Instalasi Server Rina',
            'start_at' => now()->addHour(),
            'end_at' => now()->addHours(3),
            'status' => 'scheduled',
        ]);
        $schedule->technicians()->attach($rina->id);

        $this->actingAs($rina)
            ->get(route('teknisi.dashboard'))
            ->assertOk()
            ->assertSee(__('Active').' (1)')
            ->assertSee('Rina')
            ->assertSee('Instalasi Server Rina')
            ->assertSee('1 '.__('field job'));
    }

    public function test_multi_technician_schedule_counts_each_engineer_once(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);
        $viewer = User::factory()->create();
        $viewer->assignRole('technician');

        $yoga = User::factory()->create(['name' => 'Yoga']);
        $yoga->assignRole('technician');
        $fajar = User::factory()->create(['name' => 'Fajar']);
        $fajar->assignRole('technician');

        $schedule = TechnicianSchedule::create([
            'user_id' => $viewer->id,
            'title' => 'Instalasi Jaringan Kantor',
            'start_at' => now()->addHour(),
            'end_at' => now()->addHours(3),
            'status' => 'scheduled',
        ]);
        $schedule->technicians()->sync([$yoga->id, $fajar->id]);

        $this->actingAs($viewer)
            ->get(route('teknisi.dashboard'))
            ->assertOk()
            ->assertSee(__('Active').' (2)')
            ->assertSee('Yoga')
            ->assertSee('Fajar')
            ->assertSee(__('Idle').' (1)');
    }

    public function test_completed_or_expired_manual_schedule_is_not_active(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);
        $dedi = User::factory()->create(['name' => 'Dedi']);
        $dedi->assignRole('technician');

        $expired = TechnicianSchedule::create([
            'user_id' => $dedi->id,
            'title' => 'Jadwal Lewat',
            'start_at' => now()->subHours(4),
            'end_at' => now()->subHours(2),
            'status' => 'scheduled',
        ]);
        $expired->technicians()->attach($dedi->id);

        $completed = TechnicianSchedule::create([
            'user_id' => $dedi->id,
            'title' => 'Jadwal Selesai',
            'start_at' => now()->subHour(),
            'end_at' => now()->addHour(),
            'status' => 'completed',
        ]);
        $completed->technicians()->attach($dedi->id);

        $this->actingAs($dedi)
            ->get(route('teknisi.dashboard'))
            ->assertOk()
            ->assertSee(__('Active').' (0)')
            ->assertSee(__('Idle').' (1)')
            ->assertDontSee('Jadwal Lewat')
            ->assertDontSee('Jadwal Selesai');
    }

    public function test_request_and_auto_schedule_are_counted_once(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);
        $tono = User::factory()->create(['name' => 'Tono']);
        $tono->assignRole('technician');
        $sales = User::factory()->create(['name' => 'Sales Uji']);
        $sales->assignRole('sales');

        $lead = $this->leadFor($sales);

        $request = TechnicalRequest::create([
            'lead_id' => $lead->id,
            'requested_by' => $sales->id,
            'request_type' => 'troubleshooting',
            'title' => 'Cek Jaringan Kantor',
            'target_date' => now()->addDay()->toDateString(),
            'status' => 'assigned',
            'assigned_technician_id' => $tono->id,
        ]);

        $schedule = TechnicianSchedule::create([
            'user_id' => $sales->id,
            'technical_request_id' => $request->id,
            'title' => 'Jadwal Otomatis Cek Jaringan',
            'start_at' => now()->addDay()->setTime(9, 0),
            'end_at' => now()->addDay()->setTime(11, 0),
            'status' => 'scheduled',
        ]);
        $schedule->technicians()->attach($tono->id);

        $this->actingAs($tono)
            ->get(route('teknisi.dashboard'))
            ->assertOk()
            ->assertSee(__('Active').' (1)')
            ->assertSee('Cek Jaringan Kantor')
            ->assertDontSee('Jadwal Otomatis Cek Jaringan')
            ->assertSee('1 '.__('field job'));
    }

    public function test_survey_request_is_counted_once_through_its_survey(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);
        $tono = User::factory()->create(['name' => 'Tono']);
        $tono->assignRole('technician');
        $sales = User::factory()->create(['name' => 'Sales Uji']);
        $sales->assignRole('sales');

        $lead = $this->leadFor($sales);

        $request = TechnicalRequest::create([
            'lead_id' => $lead->id,
            'requested_by' => $sales->id,
            'request_type' => 'survey',
            'title' => 'Request Survey Lokasi',
            'target_date' => now()->addDay()->toDateString(),
            'status' => 'assigned',
            'assigned_technician_id' => $tono->id,
        ]);

        Survey::create([
            'technical_request_id' => $request->id,
            'title' => 'Survey Lokasi Gudang',
            'customer_id' => $lead->customer_id,
            'technician_user_id' => $tono->id,
            'survey_date' => now()->addDay()->toDateString(),
            'status' => 'scheduled',
        ]);

        $schedule = TechnicianSchedule::create([
            'user_id' => $sales->id,
            'technical_request_id' => $request->id,
            'title' => 'Jadwal Otomatis Survey',
            'start_at' => now()->addDay()->setTime(9, 0),
            'end_at' => now()->addDay()->setTime(11, 0),
            'status' => 'scheduled',
        ]);
        $schedule->technicians()->attach($tono->id);

        $this->actingAs($tono)
            ->get(route('teknisi.dashboard'))
            ->assertOk()
            ->assertSee(__('Active').' (1)')
            ->assertSee('Survey Lokasi Gudang')
            ->assertDontSee('Request Survey Lokasi')
            ->assertDontSee('Jadwal Otomatis Survey')
            ->assertSee('1 '.__('field job'));
    }

    public function test_pending_project_marks_pic_and_support_engineers_active(): void
    {
        $user = $this->authorizedUser();

        $pic = User::factory()->create(['name' => 'Yoga']);
        $pic->assignRole('technician');
        $support = User::factory()->create(['name' => 'Fajar']);
        $support->assignRole('technician');

        $pending = ProjectStatus::create(['name' => 'Pending', 'color' => 'orange', 'sort_order' => 1]);

        $this->actingAs($user);
        $project = $this->projectWith('Survey Awal Gudang', $pending->id);
        $project->update(['teknisi_user_id' => $pic->id]);
        $project->supportUsers()->attach($support->id);

        $this->actingAs($user)
            ->get(route('teknisi.dashboard'))
            ->assertOk()
            ->assertSee(__('Active').' (2)')
            ->assertSee('Yoga')
            ->assertSee('Fajar')
            ->assertSee('Survey Awal Gudang');
    }

    private function leadFor(User $sales): Lead
    {
        return Lead::create([
            'name' => 'Prospek Uji',
            'customer_id' => Customer::create(['name' => 'PT Uji Dashboard'])->id,
            'assigned_to' => $sales->id,
            'status' => 'warm',
            'pt_group' => 'NTI',
        ]);
    }
}
