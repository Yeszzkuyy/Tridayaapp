<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\FollowUp;
use App\Models\Lead;
use App\Models\Proposal;
use App\Models\TechnicalRequest;
use App\Models\User;
use App\Notifications\LeadAssignedNotification;
use App\Notifications\LeadReassignedNotification;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Aturan pindah lead antar sales (Management → Manage Sales):
 * 1. Lead yang sudah berprogress tetap di kolomnya, tidak kembali ke New.
 * 2. Sebelum pindah ada konfirmasi berisi ringkasan progress.
 * 3. Sales lama diberi tahu saat lead-nya dipindahkan.
 * 4. Lead Won/Lost tidak boleh dipindahkan lagi.
 */
class LeadReassignmentTest extends TestCase
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

    private function makeLead(?User $assignee = null, array $attributes = []): Lead
    {
        $customer = Customer::create(['name' => 'PT Pindah Sales '.uniqid()]);

        return Lead::create(array_merge([
            'customer_id' => $customer->id,
            'pt_group' => 'NTI',
            'segment' => 'vendor',
            'status' => 'cool',
            'incoming_date' => now()->toDateString(),
            'assigned_to' => $assignee?->id,
        ], $attributes));
    }

    public function test_assign_form_shows_confirmation_with_progress_summary(): void
    {
        $management = $this->userWithRole('management');
        $salesA = $this->userWithRole('sales');
        $lead = $this->makeLead($salesA, ['status' => 'hot']);
        $lead->forceFill(['acknowledged_at' => now()])->save();

        FollowUp::create([
            'lead_id' => $lead->id,
            'customer_id' => $lead->customer_id,
            'description' => 'Follow-up awal',
            'follow_up_date' => now()->toDateString(),
        ]);
        Proposal::create([
            'lead_id' => $lead->id,
            'proposal_number' => 'PRP-REASSIGN-1',
            'created_by' => $salesA->id,
            'proposal_date' => now()->toDateString(),
            'customer' => $lead->customer->name,
            'status' => 'sent',
        ]);
        TechnicalRequest::create([
            'lead_id' => $lead->id,
            'requested_by' => $salesA->id,
            'request_type' => 'survey',
            'title' => 'Survey lokasi',
            'status' => 'accepted',
        ]);

        $html = $this->actingAs($management)
            ->get(route('manage-sales.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('data-confirm-label="'.__('Yes, move lead').'"', $html);
        $this->assertStringContainsString('data-confirm-tone="warning"', $html);
        $this->assertStringContainsString(__('Move lead to another sales?'), $html);
        $this->assertStringContainsString('already has progress', $html);
        $this->assertStringContainsString(__('Follow ups'), $html);
        $this->assertStringContainsString(__('Quotation'), $html);
        $this->assertStringContainsString(__('Engineer requests'), $html);
    }

    public function test_assign_form_uses_plain_confirmation_for_untouched_lead(): void
    {
        $management = $this->userWithRole('management');
        $lead = $this->makeLead();

        $html = $this->actingAs($management)
            ->get(route('manage-sales.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString(__('Move this lead to the selected sales?'), $html);
        $this->assertStringNotContainsString('already has progress', $html);
        $this->assertStringContainsString(route('manage-sales.assign', $lead), $html);
    }

    public function test_previous_sales_is_notified_when_lead_is_reassigned(): void
    {
        Notification::fake();

        $management = $this->userWithRole('management');
        $salesA = $this->userWithRole('sales');
        $salesB = $this->userWithRole('sales');
        $lead = $this->makeLead($salesA);

        $this->actingAs($management)
            ->post(route('manage-sales.assign', $lead), ['assigned_to' => $salesB->id])
            ->assertRedirect();

        Notification::assertSentTo($salesA, LeadReassignedNotification::class);
        Notification::assertSentTo($salesB, LeadAssignedNotification::class);
        Notification::assertNotSentTo($salesB, LeadReassignedNotification::class);
    }

    public function test_won_and_lost_leads_cannot_be_reassigned(): void
    {
        $management = $this->userWithRole('management');
        $salesA = $this->userWithRole('sales');
        $salesB = $this->userWithRole('sales');

        foreach (['won', 'lost'] as $status) {
            $lead = $this->makeLead($salesA, ['status' => $status]);

            $this->actingAs($management)
                ->post(route('manage-sales.assign', $lead), ['assigned_to' => $salesB->id])
                ->assertStatus(422);

            $this->assertSame($salesA->id, (int) $lead->fresh()->assigned_to);
        }

        $this->actingAs($management)
            ->get(route('manage-sales.index'))
            ->assertOk()
            ->assertSee(__('Cannot be reassigned'));
    }
}
