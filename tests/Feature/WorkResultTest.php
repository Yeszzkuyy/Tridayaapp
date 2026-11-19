<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Project;
use App\Models\Survey;
use App\Models\User;
use App\Models\WorkType;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkResultTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(string $role): User
    {
        // Seeder menghapus semua assignment role, jadi cukup sekali per test.
        if (! \Spatie\Permission\Models\Role::where('name', 'sales')->exists()) {
            $this->seed(RoleAndPermissionSeeder::class);
        }

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function makeSurvey(User $sales, string $title = 'Survey Uji'): Survey
    {
        // ProjectObserver menulis ProjectActivity dengan auth()->id().
        $this->actingAs($sales);

        $customer = Customer::create(['name' => 'PT Hasil Kerja']);
        $workType = WorkType::create(['name' => 'Instalasi']);

        $project = Project::create([
            'customer_id' => $customer->id,
            'work_type_id' => $workType->id,
            'project_name' => 'Project Hasil Kerja',
            'sales_user_id' => $sales->id,
        ]);

        return Survey::create([
            'title' => $title,
            'project_id' => $project->id,
            'customer_id' => $customer->id,
            'sales_user_id' => $sales->id,
            'location' => 'Jakarta',
            'status' => 'completed',
        ]);
    }

    public function test_sales_only_sees_own_work_results(): void
    {
        $owner = $this->userWithRole('sales');
        $other = $this->userWithRole('sales');

        $this->makeSurvey($owner, 'Survey Milik Sendiri');
        $this->makeSurvey($other, 'Survey Milik Lain');

        $response = $this->actingAs($owner)
            ->get(route('teknisi.work-results.index'))
            ->assertOk();

        $this->assertSame(
            ['Survey Milik Sendiri'],
            $response->viewData('surveys')->pluck('title')->all()
        );
    }

    public function test_admin_can_read_work_results(): void
    {
        $sales = $this->userWithRole('sales');
        $admin = $this->userWithRole('admin');

        $this->makeSurvey($sales, 'Survey Untuk Admin');

        $this->actingAs($admin)
            ->get(route('teknisi.work-results.index'))
            ->assertOk()
            ->assertSee('Survey Untuk Admin');
    }

    public function test_technician_can_read_work_results(): void
    {
        $sales = $this->userWithRole('sales');
        $technician = $this->userWithRole('technician');

        $this->makeSurvey($sales, 'Survey Untuk Teknisi');

        $this->actingAs($technician)
            ->get(route('teknisi.work-results.index'))
            ->assertOk()
            ->assertSee('Survey Untuk Teknisi');
    }
}
