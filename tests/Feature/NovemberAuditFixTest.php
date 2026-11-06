<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Lead;
use App\Models\LeadDocument;
use App\Models\LeadTask;
use App\Models\Proposal;
use App\Models\TechnicalRequest;
use App\Models\User;
use App\Notifications\TechnicianAssignedNotification;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Perbaikan audit Nov 2026:
 * - quotation: tandai terkirim via WhatsApp + tombol email mailto;
 * - assign teknisi selalu mengirim notifikasi task (walau jadwal dibuat);
 * - Document Center tidak error saat hanya cabang dokumen lead yang aktif.
 */
class NovemberAuditFixTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
        Storage::fake('private');
    }

    private function userWithRole(string ...$roles): User
    {
        $user = User::factory()->create();
        $user->assignRole($roles);

        return $user;
    }

    private function makeLead(?User $assignee = null): Lead
    {
        $customer = Customer::create([
            'name' => 'PT Audit '.uniqid(),
            'pt_group' => 'NTI',
            'email' => 'customer@example.test',
        ]);

        return Lead::create([
            'customer_id' => $customer->id,
            'pt_group' => 'NTI',
            'segment' => 'vendor',
            'status' => 'hot',
            'assigned_to' => $assignee?->id,
            'incoming_date' => '2026-11-01',
        ]);
    }

    private function makeProposal(Lead $lead, User $owner, string $status = 'ready'): Proposal
    {
        return Proposal::create([
            'lead_id' => $lead->id,
            'proposal_number' => 'Q/2026/11/'.uniqid(),
            'created_by' => $owner->id,
            'proposal_date' => now()->toDateString(),
            'customer' => $lead->customer?->name ?? '-',
            'status' => $status,
            'grand_total' => 1000000,
        ]);
    }

    public function test_whatsapp_marks_quotation_sent_with_channel_and_shows_badge(): void
    {
        $sales = $this->userWithRole('sales');
        $lead = $this->makeLead($sales);
        $proposal = $this->makeProposal($lead, $sales);

        $this->actingAs($sales)
            ->postJson(route('sales.proposals.sent-via', [$proposal, 'whatsapp']))
            ->assertOk()
            ->assertJson(['ok' => true, 'channel' => 'whatsapp']);

        $proposal->refresh();
        $this->assertSame('sent', $proposal->status);
        $this->assertSame('whatsapp', $proposal->sent_channel);
        $this->assertNotNull($proposal->sent_at);

        $html = $this->actingAs($sales)->get(route('sales.proposals.show', $proposal))->assertOk()->getContent();
        $this->assertStringContainsString(__('Sent via :channel', ['channel' => 'WhatsApp']), $html);
        // Sudah terkirim: tombol kanal kirim tidak muncul lagi.
        $this->assertStringNotContainsString('wa.me/', $html);
    }

    public function test_quotation_ready_shows_mark_as_sent_whatsapp_and_email(): void
    {
        $sales = $this->userWithRole('sales');
        $lead = $this->makeLead($sales);
        $lead->customer->update(['whatsapp' => '081234567890']);
        $proposal = $this->makeProposal($lead, $sales);

        $html = $this->actingAs($sales)->get(route('sales.proposals.show', $proposal))->assertOk()->getContent();

        $this->assertStringContainsString(__('Mark as Sent'), $html);
        $this->assertStringContainsString('wa.me/', $html);
        $this->assertStringContainsString('mailto:', $html);
        $this->assertStringContainsString(__('Send via Email'), $html);
    }

    public function test_assigning_technician_with_schedule_still_sends_task_notification(): void
    {
        Notification::fake();

        $coordinator = $this->userWithRole('lead-technician');
        $technician = $this->userWithRole('technician');
        $sales = $this->userWithRole('sales');
        $lead = $this->makeLead($sales);

        $technicalRequest = TechnicalRequest::create([
            'lead_id' => $lead->id,
            'requested_by' => $sales->id,
            'request_type' => 'survey',
            'title' => 'Survey lokasi audit',
            'target_date' => now()->addDay()->toDateString(),
            'target_time' => '10:00',
            'status' => 'accepted',
        ]);

        $this->actingAs($coordinator)
            ->post(route('sales.technical-requests.assign', $technicalRequest), [
                'technician_id' => $technician->id,
            ])->assertRedirect();

        Notification::assertSentTo($technician, TechnicianAssignedNotification::class);
    }

    public function test_document_center_lead_only_branch_does_not_error(): void
    {
        $marketing = $this->userWithRole('marketing');
        $lead = $this->makeLead();

        LeadDocument::create([
            'lead_id' => $lead->id,
            'file_name' => 'requirement-audit.pdf',
            'file_path' => 'leads/requirement-audit.pdf',
            'category' => 'requirement',
        ]);

        $this->actingAs($marketing)
            ->get(route('documents.index'))
            ->assertOk()
            ->assertSee('requirement-audit.pdf');
    }

    public function test_technician_documents_page_survives_document_without_project(): void
    {
        $technician = $this->userWithRole('technician');
        $customer = Customer::create(['name' => 'PT Tanpa Project', 'pt_group' => 'NTI']);

        \App\Models\ProjectDocument::create([
            'customer_id' => $customer->id,
            'project_id' => null,
            'file_name' => 'dokumen-tanpa-project.pdf',
            'file_path' => 'documents/dokumen-tanpa-project.pdf',
        ]);

        $this->actingAs($technician)->get(route('teknisi.documents.index'))->assertOk();
    }
}
