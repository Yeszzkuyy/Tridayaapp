<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\ProjectStatus;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LeadOutcomeTest extends TestCase
{
    use RefreshDatabase;

    private function marketingUser(): User
    {
        $this->seed(RoleAndPermissionSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('marketing');

        return $user;
    }

    private function makeLead(array $overrides = []): Lead
    {
        $customer = Customer::create(['name' => 'PT Outcome']);

        return Lead::create(array_merge([
            'customer_id' => $customer->id,
            'pt_group' => 'NTI',
            'segment' => 'vendor',
            'status' => 'hot',
            'incoming_date' => '2026-09-01',
        ], $overrides));
    }

    public function test_convert_stores_closing_note_and_time(): void
    {
        $user = $this->marketingUser();
        $lead = $this->makeLead();
        $status = ProjectStatus::create(['name' => 'Open', 'sort_order' => 1]);
        $workType = \App\Models\WorkType::create(['name' => 'Instalasi']);

        $this->actingAs($user)->patch(route('leads.convert', $lead), [
            'project_name' => 'Project Deal',
            'project_status_id' => $status->id,
            'work_type_id' => $workType->id,
            'closing_note' => 'Deal 10 titik fiber.',
        ])->assertRedirect();

        $lead->refresh();
        $this->assertSame('won', $lead->status);
        $this->assertSame('Deal 10 titik fiber.', $lead->closing_note);
        $this->assertNotNull($lead->closed_at);
        $this->assertDatabaseHas('lead_activities', ['lead_id' => $lead->id, 'action' => 'converted']);

        $res = $this->actingAs($user)->get(route('leads.show', $lead));
        $res->assertOk();
        $res->assertSee('Deal 10 titik fiber.');
    }

    public function test_lost_reason_saved_via_detail_and_cleared_on_reopen(): void
    {
        $user = $this->marketingUser();
        $lead = $this->makeLead(['status' => 'lost']);

        $this->actingAs($user)->patch(route('leads.outcome', $lead), [
            'lost_reason' => 'price',
            'lost_note' => 'Kemahalan 20%.',
        ])->assertRedirect();

        $lead->refresh();
        $this->assertSame('price', $lead->lost_reason);
        $this->assertSame('Kemahalan 20%.', $lead->lost_note);

        $res = $this->actingAs($user)->get(route('leads.show', $lead));
        $res->assertOk();
        $res->assertSee('Kemahalan 20%.');

        // pindah keluar dari lost membersihkan alasan
        $this->actingAs($user)->patch(route('leads.update-status', $lead), ['status' => 'hot'])
            ->assertNoContent();
        $lead->refresh();
        $this->assertNull($lead->lost_reason);
        $this->assertNull($lead->lost_note);
    }

    public function test_invalid_lost_reason_rejected(): void
    {
        $user = $this->marketingUser();
        $lead = $this->makeLead(['status' => 'lost']);

        $this->actingAs($user)->patch(route('leads.outcome', $lead), ['lost_reason' => 'ngawur'])
            ->assertSessionHasErrors('lost_reason');
    }

    public function test_sales_non_owner_cannot_save_outcome(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);
        $owner = User::factory()->create();
        $owner->assignRole('sales');
        $other = User::factory()->create();
        $other->assignRole('sales');
        $lead = $this->makeLead(['status' => 'lost', 'assigned_to' => $owner->id]);

        $this->actingAs($other)->patch(route('leads.outcome', $lead), ['lost_reason' => 'price'])
            ->assertForbidden();
    }

    public function test_document_category_saved_and_shown(): void
    {
        Storage::fake('private');
        $user = $this->marketingUser();
        $lead = $this->makeLead();
        Storage::disk('private')->put('leads/1/proposal.pdf', 'isi');
        $doc = $lead->documents()->create([
            'file_name' => 'proposal.pdf',
            'file_path' => 'leads/1/proposal.pdf',
            'mime_type' => 'application/pdf',
        ]);

        $this->actingAs($user)->patch(route('leads.attachments.category', [$lead, $doc]), [
            'category' => 'proposal',
        ])->assertRedirect();

        $this->assertSame('proposal', $doc->fresh()->category);

        $res = $this->actingAs($user)->get(route('leads.show', $lead));
        $res->assertOk();
        $res->assertSee('Proposal');
    }

    public function test_pipeline_still_works(): void
    {
        $user = $this->marketingUser();
        $sales = User::factory()->create();
        $sales->assignRole('sales');
        $this->makeLead(['assigned_to' => $sales->id]);

        $this->actingAs($sales)->get(route('leads.pipeline'))->assertOk();
    }
}
