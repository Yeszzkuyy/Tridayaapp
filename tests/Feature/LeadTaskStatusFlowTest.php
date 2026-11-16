<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Lead;
use App\Models\LeadTask;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Status request otomatis On Progress saat Inside Sales unggah penawaran, plus tombol Selesai. */
class LeadTaskStatusFlowTest extends TestCase
{
    use RefreshDatabase;

    private bool $seeded = false;

    private function userWithRole(string $role): User
    {
        if (! $this->seeded) {
            $this->seed(RoleAndPermissionSeeder::class);
            $this->seeded = true;
        }

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function makeLead(User $sales): Lead
    {
        $customer = Customer::create(['name' => 'PT Status '.uniqid()]);

        return Lead::create([
            'customer_id' => $customer->id,
            'pt_group' => 'NTI',
            'segment' => 'vendor',
            'status' => 'cool',
            'assigned_to' => $sales->id,
            'incoming_date' => now()->toDateString(),
        ]);
    }

    private function makeTask(User $sales, User $inside, string $status = 'todo'): LeadTask
    {
        $lead = $this->makeLead($sales);

        return LeadTask::create([
            'lead_id' => $lead->id,
            'title' => 'Buatkan penawaran',
            'description' => 'Butuh penawaran lengkap.',
            'assigned_to' => $inside->id,
            'created_by' => $sales->id,
            'status' => $status,
        ]);
    }

    private function uploadQuotation(User $inside, LeadTask $task): void
    {
        $this->actingAs($inside)
            ->post(route('lead-tasks.attachments.store', $task), [
                'attachment' => UploadedFile::fake()->create('penawaran.pdf', 120, 'application/pdf'),
            ])
            ->assertRedirect();
    }

    public function test_quotation_upload_moves_todo_request_to_in_progress(): void
    {
        Storage::fake('private');
        $sales = $this->userWithRole('sales');
        $inside = $this->userWithRole('inside-sales');
        $task = $this->makeTask($sales, $inside);

        $this->uploadQuotation($inside, $task);

        $this->assertSame('in_progress', $task->fresh()->status);
        $this->assertDatabaseHas('lead_activities', [
            'lead_id' => $task->lead_id,
            'action' => 'task_status_changed',
        ]);
    }

    public function test_quotation_upload_keeps_ahead_status(): void
    {
        Storage::fake('private');
        $sales = $this->userWithRole('sales');
        $inside = $this->userWithRole('inside-sales');
        $task = $this->makeTask($sales, $inside, 'review');

        $this->uploadQuotation($inside, $task);

        $this->assertSame('review', $task->fresh()->status);
    }

    public function test_assignee_can_mark_request_done(): void
    {
        $sales = $this->userWithRole('sales');
        $inside = $this->userWithRole('inside-sales');
        $task = $this->makeTask($sales, $inside, 'in_progress');

        $this->actingAs($inside)
            ->patch(route('lead-tasks.complete', $task))
            ->assertRedirect();

        $this->assertSame('done', $task->fresh()->status);
        $this->assertDatabaseHas('lead_activities', [
            'lead_id' => $task->lead_id,
            'action' => 'task_status_changed',
        ]);

        // Klik kedua tetap aman (tidak mengubah apa pun).
        $this->actingAs($inside)
            ->patch(route('lead-tasks.complete', $task))
            ->assertRedirect();
        $this->assertSame('done', $task->fresh()->status);
    }

    public function test_sales_creator_cannot_mark_request_done(): void
    {
        $sales = $this->userWithRole('sales');
        $inside = $this->userWithRole('inside-sales');
        $task = $this->makeTask($sales, $inside, 'in_progress');

        $this->actingAs($sales)
            ->patch(route('lead-tasks.complete', $task))
            ->assertForbidden();

        $this->assertSame('in_progress', $task->fresh()->status);
    }

    public function test_done_button_only_appears_for_assignee(): void
    {
        $sales = $this->userWithRole('sales');
        $inside = $this->userWithRole('inside-sales');
        $task = $this->makeTask($sales, $inside, 'in_progress');
        $completeUrl = route('lead-tasks.complete', $task);

        $this->actingAs($inside)
            ->get(route('lead-tasks.show', $task))
            ->assertOk()
            ->assertSeeText(__('Mark as Done'));

        // Inside Sales bekerja dari workspace-nya sendiri (bukan hub Sales).
        $this->actingAs($inside)
            ->get(route('inside-sales.dashboard'))
            ->assertOk()
            ->assertSee($completeUrl, false);

        // Sales pembuat request melihat status, tetapi tidak mendapat tombol Selesai.
        $this->actingAs($sales)
            ->get(route('sales.requests.index', ['tab' => 'inside-sales']))
            ->assertOk()
            ->assertDontSee($completeUrl, false);
    }

    public function test_request_list_view_action_has_text_label(): void
    {
        $sales = $this->userWithRole('sales');
        $inside = $this->userWithRole('inside-sales');
        $this->makeTask($sales, $inside);

        $this->actingAs($inside)
            ->get(route('inside-sales.dashboard'))
            ->assertOk()
            ->assertSeeText(__('View'));
    }
}
