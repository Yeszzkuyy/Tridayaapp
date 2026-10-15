<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\LeadTask;
use App\Models\ProjectStatus;
use App\Models\User;
use App\Models\WorkType;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LeadLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(string $role): User
    {
        $this->seed(RoleAndPermissionSeeder::class);
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    public function test_multirole_management_can_assign_lead_to_sales(): void
    {
        // Regresi: akun multi-role (management+sales) assign via Manage Sales
        // harus redirect + sukses, sama seperti single-role management.
        $this->seed(RoleAndPermissionSeeder::class);
        $mgmt = User::factory()->create();
        $mgmt->assignRole(['management', 'sales']);
        $sales = User::factory()->create();
        $sales->assignRole('sales');
        $lead = Lead::create([
            'customer_id' => Customer::create(['name' => 'PT Multi Role', 'contact_person' => 'QA'])->id,
        ]);

        $this->actingAs($mgmt)->post(route('manage-sales.assign', $lead), ['assigned_to' => $sales->id])
            ->assertRedirect(route('manage-sales.index'))
            ->assertSessionHas('success');

        $this->assertSame($sales->id, $lead->fresh()->assigned_to);
        $this->assertTrue($sales->notifications()->where('type', 'App\Notifications\LeadAssignedNotification')->exists());
    }

    public function test_full_lifecycle_marketing_to_won(): void
    {
        $marketing = $this->userWithRole('marketing');
        $mgmt = User::factory()->create();
        $mgmt->assignRole('management');
        $sales = User::factory()->create();
        $sales->assignRole('sales');
        $inside = User::factory()->create();
        $inside->assignRole('inside-sales');

        // 1. Marketing create lead
        $this->actingAs($marketing)->post(route('leads.store'), [
            'customer_mode' => 'new',
            'customer_name' => 'PT Lifecycle',
            'pt_group' => 'NTI',
            'segment' => 'vendor',
            'kebutuhan' => 'CCTV 8 channel + instalasi.',
            'incoming_date' => '2026-09-29',
        ])->assertRedirect(route('leads.index'));
        $lead = Lead::whereHas('customer', fn ($q) => $q->where('name', 'PT Lifecycle'))->first();
        $this->assertNotNull($lead);
        $this->assertSame('cool', $lead->status);
        // management diberi tahu (belum di-assign)
        $this->assertTrue($mgmt->notifications()->where('type', 'App\Notifications\NewLeadNotification')->exists());

        // 2. Management assign sales
        $this->actingAs($mgmt)->post(route('manage-sales.assign', $lead), ['assigned_to' => $sales->id])
            ->assertRedirect();
        $this->assertTrue($sales->notifications()->where('type', 'App\Notifications\LeadAssignedNotification')->exists());

        // 3. Sales follow-up + next date
        $this->actingAs($sales)->postJson(route('sales.follow-ups.store'), [
            'customer_id' => $lead->customer_id,
            'lead_id' => $lead->id,
            'description' => 'Customer sudah menerima proposal.',
            'type' => 'whatsapp',
            'follow_up_date' => '2026-09-29',
            'next_follow_up_date' => '2026-10-02',
        ])->assertOk();

        // 4. Sales request inside sales
        $this->actingAs($sales)->post(route('lead-tasks.store'), [
            'lead_id' => $lead->id,
            'title' => 'Buatkan proposal teknis',
            'assigned_to' => $inside->id,
        ])->assertRedirect();
        $task = LeadTask::where('lead_id', $lead->id)->first();
        $this->assertNotNull($task);
        $this->assertTrue($inside->notifications()->where('type', 'App\Notifications\LeadTaskNotification')->exists());

        // 5. Inside sales complete + komentar
        $this->actingAs($inside)->put(route('lead-tasks.update', $task), [
            'title' => $task->title,
            'status' => 'done',
        ])->assertRedirect();
        $this->actingAs($inside)->post(route('lead-tasks.comments.store', $task), ['body' => 'Draft selesai.'])
            ->assertRedirect();
        // sales (creator) diberi tahu soal status
        $this->assertTrue($sales->notifications()->where('type', 'App\Notifications\LeadTaskNotification')->exists());

        // 6. Proposal stage + dokumen kategori proposal
        $this->actingAs($sales)->patch(route('leads.update-status', $lead), ['status' => 'hot'])
            ->assertNoContent();
        \Illuminate\Support\Facades\Storage::fake('private');
        \Illuminate\Support\Facades\Storage::disk('private')->put('leads/1/q.pdf', 'isi');
        $doc = $lead->documents()->create(['file_name' => 'q.pdf', 'file_path' => 'leads/1/q.pdf', 'mime_type' => 'application/pdf']);
        $this->actingAs($marketing)->patch(route('leads.attachments.category', [$lead, $doc]), ['category' => 'proposal'])
            ->assertRedirect();

        // 7. Won + closing
        $status = ProjectStatus::create(['name' => 'Open', 'sort_order' => 1]);
        $wt = WorkType::create(['name' => 'Instalasi']);
        $this->actingAs($marketing)->patch(route('leads.convert', $lead), [
            'project_name' => 'Project Lifecycle',
            'project_status_id' => $status->id,
            'work_type_id' => $wt->id,
            'closing_note' => 'Deal.',
        ])->assertRedirect();
        $this->assertSame('won', $lead->fresh()->status);

        // Timeline mencatat semua event penting
        $actions = LeadActivity::where('lead_id', $lead->id)->pluck('action')->all();
        foreach (['created', 'assigned', 'followup_created', 'task_created', 'task_status_changed', 'status_changed', 'converted'] as $expected) {
            $this->assertContains($expected, $actions, "timeline kehilangan {$expected}");
        }

        // Lead tetap di pipeline (tidak terhapus)
        $this->assertNotNull(Lead::find($lead->id));
    }

    public function test_lifecycle_lost_branch_with_reason(): void
    {
        $marketing = $this->userWithRole('marketing');
        $customer = Customer::create(['name' => 'PT Gagal']);
        $lead = Lead::create(['customer_id' => $customer->id, 'pt_group' => 'NTI', 'segment' => 'vendor', 'status' => 'hot']);

        $this->actingAs($marketing)->patch(route('leads.update-status', $lead), ['status' => 'lost'])
            ->assertNoContent();
        $this->actingAs($marketing)->patch(route('leads.outcome', $lead), ['lost_reason' => 'budget'])
            ->assertRedirect();

        $lead->refresh();
        $this->assertSame('lost', $lead->status);
        $this->assertSame('budget', $lead->lost_reason);
        $this->assertNotNull(Lead::find($lead->id)); // tetap tersimpan untuk reporting
    }

    public function test_authorization_boundaries_per_role(): void
    {
        $marketing = $this->userWithRole('marketing');
        $mgmt = User::factory()->create();
        $mgmt->assignRole('management');
        $sales = User::factory()->create();
        $sales->assignRole('sales');
        $otherSales = User::factory()->create();
        $otherSales->assignRole('sales');
        $inside = User::factory()->create();
        $inside->assignRole('inside-sales');
        $customer = Customer::create(['name' => 'PT Batas']);
        $lead = Lead::create(['customer_id' => $customer->id, 'pt_group' => 'NTI', 'segment' => 'vendor', 'assigned_to' => $sales->id]);

        // marketing tidak boleh buka area management
        $this->actingAs($marketing)->get(route('manage-sales.index'))->assertForbidden();
        $this->actingAs($marketing)->post(route('manage-sales.assign', $lead), ['assigned_to' => $sales->id])
            ->assertForbidden();

        // sales lain tidak bisa lihat lead ini (sebelum reassign)
        $this->actingAs($otherSales)->get(route('leads.show', $lead))->assertForbidden();

        // management boleh assign
        $this->actingAs($mgmt)->post(route('manage-sales.assign', $lead), ['assigned_to' => $otherSales->id])
            ->assertRedirect();

        // setelah reassign, pemilik baru bisa lihat
        $this->actingAs($otherSales)->get(route('leads.show', $lead->fresh()))->assertOk();

        // inside sales tanpa task tidak bisa lihat lead
        $this->actingAs($inside)->get(route('leads.show', $lead))->assertForbidden();

        // inside sales tidak bisa buka manage-sales
        $this->actingAs($inside)->get(route('manage-sales.index'))->assertForbidden();
    }

    public function test_task_index_has_no_n_plus_one(): void
    {
        $mgmt = $this->userWithRole('management');
        $customer = Customer::create(['name' => 'PT N1']);
        $lead = Lead::create(['customer_id' => $customer->id, 'pt_group' => 'NTI', 'segment' => 'vendor']);
        LeadTask::create(['lead_id' => $lead->id, 'title' => 't1', 'created_by' => $mgmt->id]);
        LeadTask::create(['lead_id' => $lead->id, 'title' => 't2', 'created_by' => $mgmt->id]);
        LeadTask::create(['lead_id' => $lead->id, 'title' => 't3', 'created_by' => $mgmt->id]);
        LeadTask::create(['lead_id' => $lead->id, 'title' => 't4', 'created_by' => $mgmt->id]);
        LeadTask::create(['lead_id' => $lead->id, 'title' => 't5', 'created_by' => $mgmt->id]);
        LeadTask::create(['lead_id' => $lead->id, 'title' => 't6', 'created_by' => $mgmt->id]);

        DB::enableQueryLog();
        $this->actingAs($mgmt)->get(route('lead-tasks.index'))->assertOk();
        $count = count(DB::getQueryLog());

        // 6 task, query tetap datar (eager load) — N+1 akan > 20.
        $this->assertLessThanOrEqual(14, $count, "N+1 terdeteksi: {$count} query");
    }
}
