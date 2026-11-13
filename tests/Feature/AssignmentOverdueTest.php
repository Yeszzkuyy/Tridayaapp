<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\FollowUp;
use App\Models\Lead;
use App\Models\User;
use App\Notifications\LeadAssignmentOverdueNotification;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AssignmentOverdueTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    private function user(string $role = 'sales'): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function lead(User $sales, array $overrides = []): Lead
    {
        $customer = Customer::create([
            'name' => 'PT Overdue '.uniqid(),
            'company' => 'PT Overdue',
            'pt_group' => 'NTI',
            'contact_person' => 'Rina',
            'whatsapp' => '628123450011',
            'email' => 'rina@example.test',
        ]);

        $assignedAt = $overrides['assigned_at'] ?? now()->subHours(25);
        $notifiedAt = $overrides['assignment_overdue_notified_at'] ?? null;
        unset($overrides['assigned_at'], $overrides['assignment_overdue_notified_at']);

        $lead = Lead::create(array_merge([
            'customer_id' => $customer->id,
            'pt_group' => 'NTI',
            'segment' => 'vendor',
            'status' => 'cool',
            'assigned_to' => $sales->id,
            'project_name' => 'Proyek Overdue',
            'incoming_date' => today()->toDateString(),
        ], $overrides));

        $lead->forceFill([
            'assigned_at' => $assignedAt,
            'assignment_overdue_notified_at' => $notifiedAt,
        ])->saveQuietly();

        return $lead->fresh();
    }

    public function test_lead_assigned_more_than_24_hours_without_follow_up_is_overdue(): void
    {
        $sales = $this->user();
        $lead = $this->lead($sales);

        $this->assertTrue($lead->isAssignmentOverdue());
        $this->assertSame(1, Lead::assignmentOverdue()->count());

        $other = $this->lead($sales, ['assigned_at' => now()->subHours(2)]);

        $html = $this->actingAs($sales)->get(route('sales.my-leads', ['overdue' => 1]))->assertOk()->getContent();
        $this->assertStringContainsString($lead->customer->name, $html);
        $this->assertStringNotContainsString($other->customer->name, $html);
        $this->assertStringContainsString('Overdue', $html);
    }

    public function test_lead_under_24_hours_is_not_overdue(): void
    {
        $sales = $this->user();
        $lead = $this->lead($sales, ['assigned_at' => now()->subHours(23)]);

        $this->assertFalse($lead->fresh()->isAssignmentOverdue());
        $this->assertSame(0, Lead::assignmentOverdue()->count());
    }

    public function test_follow_up_after_assignment_clears_overdue(): void
    {
        $sales = $this->user();
        $lead = $this->lead($sales);

        FollowUp::create([
            'customer_id' => $lead->customer_id,
            'lead_id' => $lead->id,
            'type' => 'whatsapp',
            'description' => 'Sudah dihubungi.',
            'follow_up_date' => today()->toDateString(),
            'created_by' => $sales->id,
        ]);

        $this->assertFalse($lead->fresh()->isAssignmentOverdue());
        $this->assertSame(0, Lead::assignmentOverdue()->count());
    }

    public function test_moving_stage_clears_overdue(): void
    {
        $sales = $this->user();
        $lead = $this->lead($sales);

        $lead->forceFill(['acknowledged_at' => now()])->save();

        $this->assertFalse($lead->fresh()->isAssignmentOverdue());
        $this->assertSame(0, Lead::assignmentOverdue()->count());
    }

    public function test_notification_is_sent_once_per_assignment(): void
    {
        Notification::fake();

        $sales = $this->user();
        $lead = $this->lead($sales);

        $this->artisan('leads:overdue')->assertSuccessful();

        Notification::assertSentTo($sales, LeadAssignmentOverdueNotification::class);
        $this->assertNotNull($lead->fresh()->assignment_overdue_notified_at);

        $this->artisan('leads:overdue')->assertSuccessful();
        Notification::assertSentToTimes($sales, LeadAssignmentOverdueNotification::class, 1);
    }

    public function test_reassign_resets_overdue_timer_and_notification(): void
    {
        $management = $this->user('management');
        $oldSales = $this->user();
        $newSales = $this->user();
        $lead = $this->lead($oldSales, ['assignment_overdue_notified_at' => now()]);

        $this->actingAs($management)->post(route('manage-sales.assign', $lead), [
            'assigned_to' => $newSales->id,
        ])->assertRedirect();

        $lead->refresh();
        $this->assertSame($newSales->id, $lead->assigned_to);
        $this->assertNull($lead->assignment_overdue_notified_at);
        $this->assertFalse($lead->isAssignmentOverdue());
    }
}
