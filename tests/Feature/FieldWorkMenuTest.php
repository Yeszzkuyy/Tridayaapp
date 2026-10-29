<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Instalasi;
use App\Models\Project;
use App\Models\Survey;
use App\Models\User;
use App\Models\WorkType;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FieldWorkMenuTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function makeProject(string $name = 'PT Field Work'): Project
    {
        $customer = Customer::firstOrCreate(['name' => $name]);
        $workType = WorkType::firstOrCreate(['name' => 'Instalasi & Aktivasi']);

        return Project::withoutEvents(fn () => Project::create([
            'customer_id' => $customer->id,
            'work_type_id' => $workType->id,
            'project_name' => 'Project '.$name,
        ]));
    }

    public function test_all_technician_roles_see_every_installation_and_survey(): void
    {
        $owner = $this->userWithRole('technician');
        $other = $this->userWithRole('technician');
        $project = $this->makeProject();

        Instalasi::create([
            'project_id' => $project->id,
            'location' => 'Jakarta Selatan',
            'technician_pic' => $owner->name,
            'status' => 'completed',
            'completed_at' => now(),
            'completed_by' => $owner->id,
        ]);

        Survey::create([
            'title' => 'Survey Genset',
            'customer_id' => $project->customer_id,
            'project_id' => $project->id,
            'sales_user_id' => $other->id,
            'pic' => $other->name,
            'status' => 'scheduled',
        ]);

        foreach (['technician', 'lead-technician', 'prakerin-technician'] as $role) {
            $viewer = $this->userWithRole($role);

            $this->actingAs($viewer)
                ->get(route('teknisi.field-work.index'))
                ->assertOk()
                ->assertSee('Project PT Field Work')
                ->assertSee('Survey Genset');
        }
    }

    public function test_tab_and_search_filters_work(): void
    {
        $technician = $this->userWithRole('technician');
        $project = $this->makeProject();

        Instalasi::create([
            'project_id' => $project->id,
            'location' => 'Bandung',
            'status' => 'on_progress',
        ]);

        Survey::create([
            'title' => 'Survey Bandung',
            'customer_id' => $project->customer_id,
            'project_id' => $project->id,
            'sales_user_id' => $technician->id,
            'status' => 'scheduled',
        ]);

        $this->actingAs($technician)
            ->get(route('teknisi.field-work.index', ['tab' => 'survey']))
            ->assertOk()
            ->assertSee('Survey Bandung')
            ->assertDontSee('>Installation</span>', false);

        $this->actingAs($technician)
            ->get(route('teknisi.field-work.index', ['q' => 'Bandung', 'tab' => 'installation']))
            ->assertOk()
            ->assertSee('Bandung');
    }

    public function test_add_buttons_are_only_shown_to_managers(): void
    {
        $manager = $this->userWithRole('technician');
        $readOnly = $this->userWithRole('prakerin-technician');

        $this->actingAs($manager)
            ->get(route('teknisi.field-work.index'))
            ->assertOk()
            ->assertSee('Add Survey')
            ->assertSee('Add Installation');

        $this->actingAs($readOnly)
            ->get(route('teknisi.field-work.index'))
            ->assertOk()
            ->assertDontSee('Add Survey')
            ->assertDontSee('Add Installation');
    }

    public function test_menu_still_works_when_only_surveys_exist(): void
    {
        $technician = $this->userWithRole('technician');
        $project = $this->makeProject();

        Survey::create([
            'title' => 'Survey Tanpa Instalasi',
            'customer_id' => $project->customer_id,
            'project_id' => $project->id,
            'sales_user_id' => $technician->id,
            'status' => 'scheduled',
        ]);

        $this->actingAs($technician)
            ->get(route('teknisi.field-work.index'))
            ->assertOk()
            ->assertSee('Survey Tanpa Instalasi');
    }

    public function test_menu_still_works_when_only_installations_exist(): void
    {
        $technician = $this->userWithRole('technician');
        $project = $this->makeProject();

        Instalasi::create([
            'project_id' => $project->id,
            'location' => 'Surabaya',
            'status' => 'on_progress',
        ]);

        $this->actingAs($technician)
            ->get(route('teknisi.field-work.index'))
            ->assertOk()
            ->assertSee('Surabaya');
    }

    public function test_sales_cannot_open_the_technician_field_work_menu(): void
    {
        $sales = $this->userWithRole('sales');

        $this->actingAs($sales)
            ->get(route('teknisi.field-work.index'))
            ->assertForbidden();
    }
}
