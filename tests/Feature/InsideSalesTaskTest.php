<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\LeadTask;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InsideSalesTaskTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(string $role): User
    {
        $this->seed(RoleAndPermissionSeeder::class);
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function makeLead(?User $assignee = null): Lead
    {
        $customer = Customer::create(['name' => 'PT InSales']);
        LeadActivity::query()->delete();

        return Lead::create([
            'customer_id' => $customer->id,
            'pt_group' => 'NTI',
            'segment' => 'vendor',
            'status' => 'cool',
            'assigned_to' => $assignee?->id,
            'incoming_date' => '2026-09-01',
        ]);
    }

    public function test_assign_first_vs_reassign_logged_distinctly(): void
    {
        $mgmt = $this->userWithRole('management');
        $salesA = User::factory()->create();
        $salesA->assignRole('sales');
        $salesB = User::factory()->create();
        $salesB->assignRole('sales');
        $lead = $this->makeLead();

        // assign pertama
        $this->actingAs($mgmt)->post(route('manage-sales.assign', $lead), ['assigned_to' => $salesA->id])
            ->assertRedirect();
        $this->assertDatabaseHas('lead_activities', ['lead_id' => $lead->id, 'action' => 'assigned']);

        // reassign ke sales lain
        $this->actingAs($mgmt)->post(route('manage-sales.assign', $lead), ['assigned_to' => $salesB->id])
            ->assertRedirect();
        $this->assertDatabaseHas('lead_activities', ['lead_id' => $lead->id, 'action' => 'reassigned']);

        $res = $this->actingAs($this->userWithRole('marketing'))->get(route('leads.show', $lead->fresh()));
        $res->assertOk();
    }

    public function test_sales_requests_task_and_inside_sales_works_it(): void
    {
        $sales = $this->userWithRole('sales');
        $inside = User::factory()->create();
        $inside->assignRole('inside-sales');
        $lead = $this->makeLead($sales);

        // sales request task
        $res = $this->actingAs($sales)->post(route('lead-tasks.store'), [
            'lead_id' => $lead->id,
            'title' => 'Buatkan proposal teknis',
            'description' => 'Requirement customer terlampir.',
            'assigned_to' => $inside->id,
            'due_date' => '2026-10-05',
            'priority' => 'high',
        ]);
        $res->assertRedirect();
        $task = LeadTask::where('lead_id', $lead->id)->first();
        $this->assertNotNull($task);
        $this->assertSame('todo', $task->status);
        $this->assertDatabaseHas('lead_activities', ['lead_id' => $lead->id, 'action' => 'task_created']);

        // inside sales lihat task + update status
        $this->actingAs($inside)->get(route('lead-tasks.show', $task))->assertOk();
        $this->actingAs($inside)->put(route('lead-tasks.update', $task), [
            'title' => $task->title,
            'status' => 'in_progress',
        ])->assertRedirect();
        $this->assertSame('in_progress', $task->fresh()->status);
        $this->assertDatabaseHas('lead_activities', ['lead_id' => $lead->id, 'action' => 'task_status_changed']);

        // komentar dua arah
        $this->actingAs($inside)->post(route('lead-tasks.comments.store', $task), ['body' => 'Draft proposal sudah selesai.'])
            ->assertRedirect();
        $this->actingAs($sales)->post(route('lead-tasks.comments.store', $task), ['body' => 'Terima kasih, saya teruskan ke customer.'])
            ->assertRedirect();
        $this->assertSame(2, $task->comments()->count());
    }

    public function test_task_auth_boundaries(): void
    {
        $sales = $this->userWithRole('sales');
        $otherSales = User::factory()->create();
        $otherSales->assignRole('sales');
        $marketing = User::factory()->create();
        $marketing->assignRole('marketing');
        $lead = $this->makeLead($sales);

        // sales lain tidak bisa request untuk lead ini
        $this->actingAs($otherSales)->post(route('lead-tasks.store'), [
            'lead_id' => $lead->id,
            'title' => 'x',
        ])->assertForbidden();

        // assign ke non inside-sales ditolak
        $this->actingAs($sales)->post(route('lead-tasks.store'), [
            'lead_id' => $lead->id,
            'title' => 'x',
            'assigned_to' => $otherSales->id,
        ])->assertStatus(422);

        // marketing tidak dapat akses task sales
        $task = LeadTask::create(['lead_id' => $lead->id, 'title' => 't', 'created_by' => $sales->id]);
        $this->actingAs($marketing)->get(route('lead-tasks.show', $task))->assertForbidden();
    }

    public function test_inside_sales_sees_lead_with_own_task(): void
    {
        $sales = $this->userWithRole('sales');
        $inside = User::factory()->create();
        $inside->assignRole('inside-sales');
        $lead = $this->makeLead($sales);
        LeadTask::create(['lead_id' => $lead->id, 'title' => 't', 'assigned_to' => $inside->id, 'created_by' => $sales->id]);

        $this->actingAs($inside)->get(route('leads.show', $lead))->assertOk();
        $this->actingAs($inside)->get(route('lead-tasks.index'))->assertOk();
    }
}
