<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Project;
use App\Models\User;
use App\Models\WorkType;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectDetailDocumentsTest extends TestCase
{
    use RefreshDatabase;

    public function test_project_detail_links_to_its_documents(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);
        $user = User::factory()->create();
        $user->givePermissionTo('view-technician');
        $this->actingAs($user);

        $customer = Customer::create(['name' => 'PT Uji Project']);
        $workType = WorkType::create(['name' => 'Instalasi']);
        $project = Project::create([
            'customer_id' => $customer->id,
            'work_type_id' => $workType->id,
            'project_name' => 'Project Uji Dokumen',
        ]);

        $this->actingAs($user)
            ->get(route('projects.show', $project))
            ->assertOk()
            ->assertSee(route('project-documents.index', $project), false);
    }
}
