<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\LeadTask;
use App\Models\Proposal;
use App\Models\SalesSchedule;
use App\Models\TechnicalRequest;
use App\Models\User;
use App\Notifications\TechnicalRequestCreatedNotification;
use App\Notifications\TechnicalRequestReviewedNotification;
use App\Notifications\TechnicalWorkCompletedNotification;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SalesFlowTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function makeLead(?int $assignedTo = null): Lead
    {
        $customer = Customer::create(['name' => 'PT Flow ' . uniqid()]);
        $lead = Lead::create([
            'customer_id' => $customer->id,
            'pt_group' => 'NTI',
            'segment' => 'vendor',
            'status' => 'hot',
        ]);

        if ($assignedTo) {
            $lead->update(['assigned_to' => $assignedTo]);
        }

        return $lead;
    }

    private function giveInsideTask(User $inside, Lead $lead): void
    {
        LeadTask::create([
            'lead_id' => $lead->id,
            'title' => 'Tugas',
            'assigned_to' => $inside->id,
            'status' => 'todo',
            'created_by' => $inside->id,
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_sales_can_create_schedule_for_own_lead(): void
    {
        $sales = $this->userWithRole('sales');
        $lead = $this->makeLead($sales->id);

        $this->actingAs($sales)->post(route('sales.schedules.store'), [
            'lead_id' => $lead->id,
            'assigned_to' => $sales->id,
            'title' => 'Customer Meeting',
            'type' => 'meeting',
            'start_at' => now()->addDay()->format('Y-m-d H:i'),
            'end_at' => now()->addDay()->addHour()->format('Y-m-d H:i'),
        ])->assertRedirect();

        $this->assertDatabaseHas('sales_schedules', [
            'lead_id' => $lead->id,
            'title' => 'Customer Meeting',
            'status' => 'scheduled',
            'created_by' => $sales->id,
        ]);
        $this->assertTrue(
            LeadActivity::where('lead_id', $lead->id)->where('action', 'schedule_created')->exists()
        );
    }

    public function test_inside_sales_can_create_complete_and_cancel_schedule(): void
    {
        $inside = $this->userWithRole('inside-sales');
        $lead = $this->makeLead();
        $this->giveInsideTask($inside, $lead);

        $this->actingAs($inside)->post(route('sales.schedules.store'), [
            'lead_id' => $lead->id,
            'assigned_to' => $inside->id,
            'title' => 'Call Customer',
            'type' => 'call',
            'start_at' => now()->addDay()->format('Y-m-d H:i'),
        ])->assertRedirect();

        $schedule = SalesSchedule::where('title', 'Call Customer')->firstOrFail();

        $this->actingAs($inside)
            ->post(route('sales.schedules.complete', $schedule))
            ->assertRedirect();
        $this->assertSame('completed', $schedule->refresh()->status);

        $schedule->update(['status' => 'scheduled']);
        $this->actingAs($inside)
            ->post(route('sales.schedules.cancel', $schedule))
            ->assertRedirect();
        $this->assertSame('cancelled', $schedule->refresh()->status);
    }

    public function test_schedule_rejects_end_before_start(): void
    {
        $sales = $this->userWithRole('sales');
        $lead = $this->makeLead($sales->id);

        $this->actingAs($sales)->post(route('sales.schedules.store'), [
            'lead_id' => $lead->id,
            'assigned_to' => $sales->id,
            'title' => 'Invalid',
            'type' => 'call',
            'start_at' => now()->addDay()->format('Y-m-d H:i'),
            'end_at' => now()->format('Y-m-d H:i'),
        ])->assertSessionHasErrors('end_at');

        $this->assertDatabaseMissing('sales_schedules', ['title' => 'Invalid']);
    }

    public function test_other_sales_cannot_view_or_edit_foreign_schedule(): void
    {
        $sales = $this->userWithRole('sales');
        $other = $this->userWithRole('sales');
        $lead = $this->makeLead($sales->id);

        $schedule = SalesSchedule::create([
            'lead_id' => $lead->id,
            'assigned_to' => $sales->id,
            'created_by' => $sales->id,
            'title' => 'Rahasia',
            'type' => 'meeting',
            'start_at' => now()->addDay(),
            'status' => 'scheduled',
        ]);

        $this->actingAs($other)->get(route('sales.schedules.show', $schedule))->assertForbidden();
        $this->actingAs($other)->get(route('sales.schedules.edit', $schedule))->assertForbidden();
    }

    public function test_agenda_renders_weekly_strip(): void
    {
        $sales = $this->userWithRole('sales');
        $mgmt = $this->userWithRole('management');

        $this->actingAs($sales)->get(route('sales.schedules.index'))
            ->assertOk()->assertSee('sales-strip', false);

        $this->actingAs($mgmt)->get(route('sales.schedules.index'))
            ->assertOk()->assertSee('sales-strip', false);
    }

    public function test_sales_creates_technical_request_and_lead_tech_is_notified(): void
    {
        Notification::fake();

        $sales = $this->userWithRole('sales');
        $leadTech = $this->userWithRole('lead-technician');
        $lead = $this->makeLead($sales->id);

        $this->actingAs($sales)->post(route('sales.technical-requests.store'), [
            'lead_id' => $lead->id,
            'request_type' => 'survey',
            'title' => 'Survey Lokasi',
            'priority' => 'urgent',
        ])->assertRedirect();

        $request = TechnicalRequest::where('title', 'Survey Lokasi')->firstOrFail();
        $this->assertSame('waiting_lead', $request->status);

        Notification::assertSentTo($leadTech, TechnicalRequestCreatedNotification::class);
        $this->assertTrue(
            LeadActivity::where('lead_id', $lead->id)->where('action', 'tech_request_created')->exists()
        );
    }

    public function test_sales_cannot_assign_technician(): void
    {
        $sales = $this->userWithRole('sales');
        $tech = $this->userWithRole('technician');
        $lead = $this->makeLead($sales->id);

        $request = TechnicalRequest::create([
            'lead_id' => $lead->id, 'requested_by' => $sales->id,
            'request_type' => 'survey', 'title' => 'R1', 'priority' => 'normal', 'status' => 'accepted',
        ]);

        // Route teknisi di belakang manage-technician: sales langsung 403.
        $this->actingAs($sales)
            ->post(route('sales.technical-requests.assign', $request), ['technician_id' => $tech->id])
            ->assertForbidden();
    }

    public function test_lead_technician_accepts_and_assigns_and_sales_is_notified(): void
    {
        Notification::fake();

        $sales = $this->userWithRole('sales');
        $leadTech = $this->userWithRole('lead-technician');
        $tech = $this->userWithRole('technician');
        $lead = $this->makeLead($sales->id);

        $request = TechnicalRequest::create([
            'lead_id' => $lead->id, 'requested_by' => $sales->id,
            'request_type' => 'instalasi', 'title' => 'R2', 'priority' => 'normal', 'status' => 'waiting_lead',
        ]);

        $this->actingAs($leadTech)
            ->post(route('sales.technical-requests.review', $request), ['decision' => 'accepted'])
            ->assertRedirect();
        $this->assertSame('accepted', $request->refresh()->status);
        Notification::assertSentTo($sales, TechnicalRequestReviewedNotification::class);

        $this->actingAs($leadTech)
            ->post(route('sales.technical-requests.assign', $request), ['technician_id' => $tech->id])
            ->assertRedirect();
        $this->assertSame('assigned', $request->refresh()->status);
        $this->assertSame($tech->id, (int) $request->assigned_technician_id);
    }

    public function test_assign_rejects_non_technician_user(): void
    {
        $sales = $this->userWithRole('sales');
        $leadTech = $this->userWithRole('lead-technician');
        $lead = $this->makeLead($sales->id);

        $request = TechnicalRequest::create([
            'lead_id' => $lead->id, 'requested_by' => $sales->id,
            'request_type' => 'survey', 'title' => 'R3', 'priority' => 'normal', 'status' => 'accepted',
        ]);

        $this->actingAs($leadTech)
            ->post(route('sales.technical-requests.assign', $request), ['technician_id' => $sales->id])
            ->assertStatus(422);
    }

    public function test_technician_works_and_completes_and_sales_sees_result(): void
    {
        Notification::fake();

        $sales = $this->userWithRole('sales');
        $tech = $this->userWithRole('technician');
        $lead = $this->makeLead($sales->id);

        $request = TechnicalRequest::create([
            'lead_id' => $lead->id, 'requested_by' => $sales->id,
            'request_type' => 'troubleshooting', 'title' => 'R4', 'priority' => 'normal',
            'status' => 'assigned', 'assigned_technician_id' => $tech->id,
        ]);

        // Sales dilarang mengisi hasil teknis.
        $this->actingAs($sales)
            ->post(route('sales.technical-requests.result', $request), ['technical_result' => 'X'])
            ->assertForbidden();

        $this->actingAs($tech)
            ->post(route('sales.technical-requests.progress', $request), ['notes' => 'OTW'])
            ->assertRedirect();
        $this->assertSame('in_progress', $request->refresh()->status);

        // Complete tanpa hasil ditolak.
        $this->actingAs($tech)
            ->post(route('sales.technical-requests.complete', $request))
            ->assertStatus(422);

        $this->actingAs($tech)
            ->post(route('sales.technical-requests.result', $request), ['technical_result' => 'Kabel putus, sudah disambung.'])
            ->assertRedirect();

        $this->actingAs($tech)
            ->post(route('sales.technical-requests.complete', $request))
            ->assertRedirect();
        $this->assertSame('completed', $request->refresh()->status);

        Notification::assertSentTo($sales, TechnicalWorkCompletedNotification::class);

        $this->actingAs($sales)->get(route('sales.technical-requests.show', $request))
            ->assertOk()->assertSee('Kabel putus, sudah disambung.');
    }

    public function test_proposal_draft_with_items_computes_totals_server_side(): void
    {
        $sales = $this->userWithRole('sales');
        $lead = $this->makeLead($sales->id);

        $this->actingAs($sales)->post(route('sales.proposals.store'), [
            'lead_id' => $lead->id,
            'proposal_date' => now()->format('Y-m-d'),
            'discount' => 10000,
            'tax' => 11000,
            'items' => [
                ['description' => 'Router', 'quantity' => 2, 'unit' => 'pcs', 'unit_price' => 50000, 'discount' => 5000],
                ['description' => 'Jasa', 'quantity' => 1, 'unit' => 'lot', 'unit_price' => 100000],
            ],
        ])->assertRedirect();

        $proposal = Proposal::where('lead_id', $lead->id)->firstOrFail();
        $this->assertMatchesRegularExpression('#^Q/\d{4}/\d{2}/\d{4}$#', $proposal->proposal_number);
        // (2*50000-5000) + 100000 = 195000; -10000 +11000 => 196000
        $this->assertSame(195000.0, (float) $proposal->subtotal);
        $this->assertSame(196000.0, (float) $proposal->grand_total);
        $this->assertSame('draft', $proposal->status);
        $this->assertTrue(
            LeadActivity::where('lead_id', $lead->id)->where('action', 'proposal_created')->exists()
        );
    }

    public function test_proposal_lifecycle_and_edit_lock_after_send(): void
    {
        $sales = $this->userWithRole('sales');
        $lead = $this->makeLead($sales->id);

        $proposal = Proposal::create([
            'lead_id' => $lead->id, 'proposal_number' => 'Q/2026/10/0001', 'created_by' => $sales->id,
            'proposal_date' => now(), 'customer' => 'PT X', 'status' => 'draft',
        ]);

        $this->actingAs($sales)->post(route('sales.proposals.ready', $proposal))->assertRedirect();
        $this->actingAs($sales)->post(route('sales.proposals.send', $proposal))->assertRedirect();
        $this->assertSame('sent', $proposal->refresh()->status);
        $this->assertNotNull($proposal->sent_at);

        // Draft-only edit: terkunci setelah sent.
        $this->actingAs($sales)->get(route('sales.proposals.edit', $proposal))->assertStatus(422);

        $this->actingAs($sales)->post(route('sales.proposals.revise', $proposal))->assertRedirect();
        $this->assertSame('revision', $proposal->refresh()->status);
        $this->actingAs($sales)->get(route('sales.proposals.edit', $proposal))->assertOk();

        $this->actingAs($sales)
            ->post(route('sales.proposals.respond', $proposal), ['decision' => 'accepted'])
            ->assertRedirect();
        $this->assertSame('accepted', $proposal->refresh()->status);
    }

    public function test_expire_command_marks_overdue_sent_proposals(): void
    {
        $sales = $this->userWithRole('sales');
        $lead = $this->makeLead($sales->id);

        $proposal = Proposal::create([
            'lead_id' => $lead->id, 'proposal_number' => 'Q/2026/10/0002', 'created_by' => $sales->id,
            'proposal_date' => now()->subDays(10), 'valid_until' => now()->subDay(),
            'customer' => 'PT Y', 'status' => 'sent',
        ]);

        $this->artisan('proposals:expire')->assertSuccessful();
        $this->assertSame('expired', $proposal->refresh()->status);
        $this->assertTrue(
            LeadActivity::where('lead_id', $lead->id)->where('action', 'proposal_expired')->exists()
        );
    }

    public function test_lead_detail_shows_new_sections_and_dashboard_summary(): void
    {
        $sales = $this->userWithRole('sales');
        $lead = $this->makeLead($sales->id);

        SalesSchedule::create([
            'lead_id' => $lead->id, 'assigned_to' => $sales->id, 'created_by' => $sales->id,
            'title' => 'Visit Pabrik', 'type' => 'visit', 'start_at' => now()->addDay(), 'status' => 'scheduled',
        ]);

        $this->actingAs($sales)->get(route('leads.show', $lead))
            ->assertOk()
            ->assertSee('Technical Request')
            ->assertSee('Jadwal Sales')
            ->assertSee('Penawaran')
            ->assertSee('Visit Pabrik');

        $this->actingAs($sales)->get(route('sales.dashboard'))
            ->assertOk()
            ->assertSee('Jadwal Saya');
    }

    public function test_other_sales_cannot_touch_foreign_proposal(): void
    {
        $sales = $this->userWithRole('sales');
        $other = $this->userWithRole('sales');
        $lead = $this->makeLead($sales->id);

        $proposal = Proposal::create([
            'lead_id' => $lead->id, 'proposal_number' => 'Q/2026/10/0003', 'created_by' => $sales->id,
            'proposal_date' => now(), 'customer' => 'PT Z', 'status' => 'draft',
        ]);

        $this->actingAs($other)->get(route('sales.proposals.show', $proposal))->assertForbidden();
    }
}
