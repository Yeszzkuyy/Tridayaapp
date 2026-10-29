<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\LeadTask;
use App\Models\Proposal;
use App\Models\User;
use App\Notifications\LeadTaskNotification;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
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

    public function test_task_detail_back_link_points_to_request_hub_inside_sales_tab(): void
    {
        $sales = $this->userWithRole('sales');
        $lead = $this->makeLead($sales);

        $this->actingAs($sales)->post(route('lead-tasks.store'), [
            'lead_id' => $lead->id,
            'title' => 'Buatkan penawaran',
        ])->assertRedirect();

        $task = LeadTask::where('title', 'Buatkan penawaran')->firstOrFail();

        $this->actingAs($sales)
            ->get(route('lead-tasks.show', $task))
            ->assertOk()
            ->assertSee(route('sales.requests.index', ['tab' => 'inside-sales']), false);
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

    public function test_inside_sales_uploads_quotation_and_requester_is_notified(): void
    {
        Storage::fake('private');
        Notification::fake();

        $sales = $this->userWithRole('sales');
        $inside = User::factory()->create();
        $inside->assignRole('inside-sales');
        $lead = $this->makeLead($sales);
        $task = LeadTask::create([
            'lead_id' => $lead->id,
            'title' => 'Buatkan quotation',
            'assigned_to' => $inside->id,
            'created_by' => $sales->id,
        ]);

        $this->actingAs($inside)->post(route('lead-tasks.attachments.store', $task), [
            'attachment' => UploadedFile::fake()->create('quotation.pdf', 120, 'application/pdf'),
        ])->assertRedirect(route('lead-tasks.show', $task));

        $attachment = $task->attachments()->first();
        $this->assertNotNull($attachment);
        $this->assertSame('quotation.pdf', $attachment->original_name);
        $this->assertSame($inside->id, $attachment->uploaded_by);
        Storage::disk('private')->assertExists($attachment->file_path);

        // Peminta (pembuat task) diberi notifikasi quotation baru.
        Notification::assertSentTo($sales, LeadTaskNotification::class);
    }

    public function test_quotation_upload_keeps_every_version(): void
    {
        Storage::fake('private');

        $sales = $this->userWithRole('sales');
        $inside = User::factory()->create();
        $inside->assignRole('inside-sales');
        $lead = $this->makeLead($sales);
        $task = LeadTask::create([
            'lead_id' => $lead->id,
            'title' => 'Buatkan quotation',
            'assigned_to' => $inside->id,
            'created_by' => $sales->id,
        ]);

        foreach (['quotation-v1.pdf', 'quotation-v2.pdf'] as $name) {
            $this->actingAs($inside)->post(route('lead-tasks.attachments.store', $task), [
                'attachment' => UploadedFile::fake()->create($name, 90, 'application/pdf'),
            ])->assertRedirect();
        }

        $this->assertCount(2, $task->attachments()->get());
        foreach ($task->attachments as $attachment) {
            Storage::disk('private')->assertExists($attachment->file_path);
        }
    }

    public function test_other_sales_cannot_upload_or_download_quotation(): void
    {
        Storage::fake('private');

        $sales = $this->userWithRole('sales');
        $inside = User::factory()->create();
        $inside->assignRole('inside-sales');
        $otherSales = User::factory()->create();
        $otherSales->assignRole('sales');
        $lead = $this->makeLead($sales);
        $task = LeadTask::create([
            'lead_id' => $lead->id,
            'title' => 'Buatkan quotation',
            'assigned_to' => $inside->id,
            'created_by' => $sales->id,
        ]);

        $this->actingAs($inside)->post(route('lead-tasks.attachments.store', $task), [
            'attachment' => UploadedFile::fake()->create('quotation.pdf', 80, 'application/pdf'),
        ])->assertRedirect();

        $attachment = $task->attachments()->firstOrFail();

        $this->actingAs($otherSales)->post(route('lead-tasks.attachments.store', $task), [
            'attachment' => UploadedFile::fake()->create('lain.pdf', 80, 'application/pdf'),
        ])->assertForbidden();

        $this->actingAs($otherSales)
            ->get(route('lead-tasks.attachments.download', [$task, $attachment]))
            ->assertForbidden();
    }

    public function test_requester_can_download_quotation(): void
    {
        Storage::fake('private');

        $sales = $this->userWithRole('sales');
        $inside = User::factory()->create();
        $inside->assignRole('inside-sales');
        $lead = $this->makeLead($sales);
        $task = LeadTask::create([
            'lead_id' => $lead->id,
            'title' => 'Buatkan quotation',
            'assigned_to' => $inside->id,
            'created_by' => $sales->id,
        ]);

        $this->actingAs($inside)->post(route('lead-tasks.attachments.store', $task), [
            'attachment' => UploadedFile::fake()->create('quotation.pdf', 80, 'application/pdf'),
        ])->assertRedirect();

        $attachment = $task->attachments()->firstOrFail();

        $this->actingAs($sales)
            ->get(route('lead-tasks.attachments.download', [$task, $attachment]))
            ->assertOk()
            ->assertDownload('quotation.pdf');

        // Kartu lampiran tampil di halaman detail task.
        $this->actingAs($sales)->get(route('lead-tasks.show', $task))
            ->assertOk()
            ->assertSee('quotation.pdf');
    }

    public function test_accepting_quotation_file_creates_draft_quotation(): void
    {
        Storage::fake('private');

        $sales = $this->userWithRole('sales');
        $inside = User::factory()->create();
        $inside->assignRole('inside-sales');
        $lead = $this->makeLead($sales);
        $task = LeadTask::create([
            'lead_id' => $lead->id,
            'title' => 'Buatkan quotation CCTV',
            'assigned_to' => $inside->id,
            'created_by' => $sales->id,
        ]);

        $this->actingAs($inside)->post(route('lead-tasks.attachments.store', $task), [
            'attachment' => UploadedFile::fake()->create('quotation-final.pdf', 120, 'application/pdf'),
        ])->assertRedirect();

        $attachment = $task->attachments()->firstOrFail();

        $this->actingAs($sales)
            ->post(route('lead-tasks.attachments.accept', [$task, $attachment]))
            ->assertRedirect();

        $attachment->refresh();
        $this->assertNotNull($attachment->accepted_at);
        $this->assertNotNull($attachment->proposal_id);

        $proposal = Proposal::findOrFail($attachment->proposal_id);
        $this->assertSame($lead->id, $proposal->lead_id);
        $this->assertSame('draft', $proposal->status);
        $this->assertSame($lead->customer->name, $proposal->customer);
        $this->assertStringStartsWith('Q/', $proposal->proposal_number);
        // Quotation berbasis file: tidak ada item placeholder, file terlampir.
        $this->assertCount(0, $proposal->items()->get());
        $this->assertCount(1, $proposal->attachments()->get());

        // Muncul di menu Quotation milik sales tersebut + file terlampir.
        $this->actingAs($sales)->get(route('sales.proposals.index'))
            ->assertOk()
            ->assertSee($proposal->proposal_number);

        $this->actingAs($sales)->get(route('sales.proposals.show', $proposal))
            ->assertOk()
            ->assertSee('quotation-final.pdf');

        // Accept dua kali ditolak.
        $this->actingAs($sales)
            ->post(route('lead-tasks.attachments.accept', [$task, $attachment]))
            ->assertStatus(422);
    }

    public function test_inside_sales_and_other_sales_cannot_accept_quotation(): void
    {
        Storage::fake('private');

        $sales = $this->userWithRole('sales');
        $inside = User::factory()->create();
        $inside->assignRole('inside-sales');
        $otherSales = User::factory()->create();
        $otherSales->assignRole('sales');
        $lead = $this->makeLead($sales);
        $task = LeadTask::create([
            'lead_id' => $lead->id,
            'title' => 'Buatkan quotation',
            'assigned_to' => $inside->id,
            'created_by' => $sales->id,
        ]);

        $this->actingAs($inside)->post(route('lead-tasks.attachments.store', $task), [
            'attachment' => UploadedFile::fake()->create('quotation.pdf', 60, 'application/pdf'),
        ])->assertRedirect();

        $attachment = $task->attachments()->firstOrFail();

        // Inside Sales tidak boleh menerima file-nya sendiri.
        $this->actingAs($inside)
            ->post(route('lead-tasks.attachments.accept', [$task, $attachment]))
            ->assertForbidden();

        // Sales lain (bukan pemilik lead/pembuat request) juga tidak boleh.
        $this->actingAs($otherSales)
            ->post(route('lead-tasks.attachments.accept', [$task, $attachment]))
            ->assertForbidden();

        $this->assertNull($attachment->fresh()->accepted_at);
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
