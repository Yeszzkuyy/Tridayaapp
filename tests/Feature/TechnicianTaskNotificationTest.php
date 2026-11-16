<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Lead;
use App\Models\TechnicalRequest;
use App\Models\User;
use App\Notifications\TechnicianAssignedNotification;
use App\Notifications\TechnicianTaskCancelledNotification;
use App\Notifications\TechnicianTaskReassignedNotification;
use App\Support\NotificationPresenter;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Sistem notifikasi task engineer:
 * 1. Engineer utama + support dapat kartu task lengkap (jadwal + lokasi).
 * 2. Engineer yang dilepas tidak menyisakan kartu lama dan diberi kabar.
 * 3. Request batal dikabari ke semua engineer.
 */
class TechnicianTaskNotificationTest extends TestCase
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

    private function makeLead(User $sales, string $location = 'Gudang Blok C'): Lead
    {
        $customer = Customer::create([
            'name' => 'PT Task Notif '.uniqid(),
            'address' => $location,
        ]);

        return Lead::create([
            'customer_id' => $customer->id,
            'pt_group' => 'NTI',
            'segment' => 'vendor',
            'status' => 'cool',
            'assigned_to' => $sales->id,
            'incoming_date' => now()->toDateString(),
        ]);
    }

    private function makeRequest(User $sales, Lead $lead, array $overrides = []): TechnicalRequest
    {
        return TechnicalRequest::create(array_merge([
            'lead_id' => $lead->id,
            'requested_by' => $sales->id,
            'request_type' => 'survey',
            'title' => 'Survey Task Notif',
            'status' => 'accepted',
            'location' => 'Gudang Blok C',
        ], $overrides));
    }

    public function test_assigned_engineers_get_task_card_with_schedule_and_location(): void
    {
        Notification::fake();

        $sales = $this->userWithRole('sales');
        $leadTech = $this->userWithRole('lead-technician');
        $main = $this->userWithRole('technician');
        $support = $this->userWithRole('technician');
        $request = $this->makeRequest($sales, $this->makeLead($sales));

        $this->actingAs($leadTech)->post(route('sales.technical-requests.assign', $request), [
            'technician_id' => $main->id,
            'support_ids' => [$support->id],
            'support_present' => 1,
            'target_date' => '2026-12-01',
            'target_time' => '09:30',
        ])->assertRedirect()->assertSessionHasNoErrors();

        Notification::assertSentTo([$main, $support], TechnicianAssignedNotification::class);

        $data = Notification::sent($main, TechnicianAssignedNotification::class)->first()->toDatabase($main);

        $this->assertSame('technician_assigned', $data['type']);
        $this->assertSame('01 Dec 2026', $data['schedule']);
        $this->assertSame('09:30', $data['time']);
        $this->assertSame('Gudang Blok C', $data['location']);
        $this->assertSame('technician_assigned', NotificationPresenter::kind(TechnicianAssignedNotification::class, $data));

        $card = NotificationPresenter::present(new DatabaseNotification([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'type' => TechnicianAssignedNotification::class,
            'notifiable_type' => User::class,
            'notifiable_id' => $main->id,
            'data' => $data,
        ]), $main);

        $this->assertSame('Engineer', $card['app']);
        $this->assertStringContainsString('Gudang Blok C', $card['body']);
        $this->assertSame('01 Dec 2026 09:30', $card['chips'][0]['label']);
    }

    public function test_removed_engineer_is_told_and_old_card_is_cleared(): void
    {
        $sales = $this->userWithRole('sales');
        $leadTech = $this->userWithRole('lead-technician');
        $mainA = $this->userWithRole('technician');
        $mainB = $this->userWithRole('technician');
        $support = $this->userWithRole('technician');
        $request = $this->makeRequest($sales, $this->makeLead($sales));

        $this->actingAs($leadTech)->post(route('sales.technical-requests.assign', $request), [
            'technician_id' => $mainA->id,
            'support_ids' => [$support->id],
            'support_present' => 1,
            'target_date' => '2026-12-01',
            'target_time' => '09:30',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(1, $mainA->fresh()->notifications()
            ->where('type', TechnicianAssignedNotification::class)->count());

        // Pindah ke engineer lain tanpa support.
        $this->actingAs($leadTech)->post(route('sales.technical-requests.assign', $request->fresh()), [
            'technician_id' => $mainB->id,
            'support_present' => 1,
            'target_date' => '2026-12-01',
            'target_time' => '09:30',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $mainA->refresh();
        $support->refresh();
        $mainB->refresh();

        $this->assertSame(0, $mainA->notifications()
            ->where('type', TechnicianAssignedNotification::class)->count());
        $this->assertSame(0, $support->notifications()
            ->where('type', TechnicianAssignedNotification::class)->count());
        $this->assertSame(1, $mainA->notifications()
            ->where('type', TechnicianTaskReassignedNotification::class)->count());
        $this->assertSame(1, $support->notifications()
            ->where('type', TechnicianTaskReassignedNotification::class)->count());
        $this->assertSame(1, $mainB->notifications()
            ->where('type', TechnicianAssignedNotification::class)->count());
    }

    public function test_cancelled_request_notifies_all_engineers(): void
    {
        Notification::fake();

        $sales = $this->userWithRole('sales');
        $main = $this->userWithRole('technician');
        $support = $this->userWithRole('technician');
        $request = $this->makeRequest($sales, $this->makeLead($sales), [
            'status' => 'assigned',
            'assigned_technician_id' => $main->id,
        ]);
        $request->supportUsers()->attach($support->id);

        $this->actingAs($sales)
            ->post(route('sales.technical-requests.cancel', $request))
            ->assertRedirect();

        $this->assertSame('cancelled', $request->fresh()->status);
        Notification::assertSentTo([$main, $support], TechnicianTaskCancelledNotification::class);
    }
}
