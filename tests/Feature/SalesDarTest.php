<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\FollowUp;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesDarTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    private function user(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function lead(User $sales, string $name): Lead
    {
        $customer = Customer::create([
            'name' => $name,
            'company' => $name,
            'pt_group' => 'NTI',
            'contact_person' => 'Dewi',
            'whatsapp' => '628123450077',
            'email' => 'dewi@example.test',
        ]);

        return Lead::create([
            'customer_id' => $customer->id,
            'pt_group' => 'NTI',
            'segment' => 'vendor',
            'status' => 'warm',
            'assigned_to' => $sales->id,
            'project_name' => 'Proyek DAR',
            'incoming_date' => today()->toDateString(),
        ]);
    }

    private function activity(User $sales, Lead $lead, string $description, array $overrides = []): FollowUp
    {
        return FollowUp::create(array_merge([
            'customer_id' => $lead->customer_id,
            'lead_id' => $lead->id,
            'type' => 'visit',
            'description' => $description,
            'follow_up_date' => today()->toDateString(),
            'activity_time' => '10:00',
            'status' => 'completed',
            'completed_at' => now(),
            'source' => 'manual',
            'created_by' => $sales->id,
        ], $overrides));
    }

    public function test_sales_only_sees_own_activity(): void
    {
        $mine = $this->user('sales');
        $other = $this->user('sales');

        $this->activity($mine, $this->lead($mine, 'PT Milik Saya'), 'Kunjungan saya');
        $this->activity($other, $this->lead($other, 'PT Milik Orang'), 'Kunjungan orang lain');

        $html = $this->actingAs($mine)->get(route('sales.dar.index'))->assertOk()->getContent();

        $this->assertStringContainsString('Kunjungan saya', $html);
        $this->assertStringNotContainsString('Kunjungan orang lain', $html);
    }

    public function test_management_sees_all_and_can_filter_by_sales(): void
    {
        $management = $this->user('management');
        $salesA = $this->user('sales');
        $salesB = $this->user('sales');

        $this->activity($salesA, $this->lead($salesA, 'PT Sales A'), 'Aktivitas A');
        $this->activity($salesB, $this->lead($salesB, 'PT Sales B'), 'Aktivitas B');

        $all = $this->actingAs($management)->get(route('sales.dar.index'))->assertOk()->getContent();
        $this->assertStringContainsString('Aktivitas A', $all);
        $this->assertStringContainsString('Aktivitas B', $all);

        $filtered = $this->actingAs($management)
            ->get(route('sales.dar.index', ['user_id' => $salesA->id]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Aktivitas A', $filtered);
        $this->assertStringNotContainsString('Aktivitas B', $filtered);
    }

    public function test_admin_read_only_also_sees_all_activity(): void
    {
        $admin = $this->user('admin');
        $sales = $this->user('sales');

        $this->activity($sales, $this->lead($sales, 'PT Admin Read'), 'Aktivitas untuk admin');

        $html = $this->actingAs($admin)->get(route('sales.dar.index'))->assertOk()->getContent();

        $this->assertStringContainsString('Aktivitas untuk admin', $html);
    }

    public function test_manual_activity_is_saved_and_can_schedule_next_follow_up(): void
    {
        $sales = $this->user('sales');
        $lead = $this->lead($sales, 'PT Manual DAR');

        $this->actingAs($sales)->post(route('sales.dar.store'), [
            'lead_id' => $lead->id,
            'type' => 'demo',
            'activity_date' => today()->toDateString(),
            'activity_time' => '14:00',
            'description' => 'Demo produk di kantor customer.',
            'outcome' => 'Customer tertarik, minta harga.',
            'next_follow_up_date' => today()->addDays(2)->toDateString(),
            'next_follow_up_time' => '09:00',
        ])->assertRedirect(route('sales.dar.index'));

        $activity = FollowUp::where('lead_id', $lead->id)->where('description', 'Demo produk di kantor customer.')->firstOrFail();
        $this->assertSame('completed', $activity->status);
        $this->assertSame('manual', $activity->source);
        $this->assertSame('demo', $activity->type);
        $this->assertSame('Customer tertarik, minta harga.', $activity->outcome);

        $this->assertSame(1, FollowUp::where('lead_id', $lead->id)->active()->count());
        $this->assertSame(today()->addDays(2)->toDateString(), FollowUp::where('lead_id', $lead->id)->active()->first()->follow_up_date->toDateString());

        $html = $this->actingAs($sales)->get(route('sales.dar.index'))->assertOk()->getContent();
        $this->assertStringContainsString('Demo produk di kantor customer.', $html);
        $this->assertStringContainsString('Customer tertarik, minta harga.', $html);
    }

    public function test_repeated_submit_does_not_duplicate_activity(): void
    {
        $sales = $this->user('sales');
        $lead = $this->lead($sales, 'PT Double Submit');

        $payload = [
            'lead_id' => $lead->id,
            'type' => 'visit',
            'activity_date' => today()->toDateString(),
            'description' => 'Kunjungan yang sama.',
        ];

        $this->actingAs($sales)->post(route('sales.dar.store'), $payload)->assertRedirect();
        $this->actingAs($sales)->post(route('sales.dar.store'), $payload)->assertRedirect();

        $this->assertSame(1, FollowUp::where('lead_id', $lead->id)->count());
    }

    public function test_same_lead_can_have_multiple_activities_on_same_day(): void
    {
        $sales = $this->user('sales');
        $lead = $this->lead($sales, 'PT Multi Aktivitas');

        $this->activity($sales, $lead, 'Telepon pagi.');
        $this->activity($sales, $lead, 'Meeting siang.');

        $html = $this->actingAs($sales)->get(route('sales.dar.index'))->assertOk()->getContent();

        $this->assertStringContainsString('Telepon pagi.', $html);
        $this->assertStringContainsString('Meeting siang.', $html);
    }

    public function test_pipeline_change_appears_as_system_activity(): void
    {
        $sales = $this->user('sales');
        $lead = $this->lead($sales, 'PT Pipeline DAR');

        LeadActivity::create([
            'lead_id' => $lead->id,
            'user_id' => $sales->id,
            'action' => 'status_changed',
            'changes' => ['status' => ['old' => 'warm', 'new' => 'hot']],
        ]);

        $html = $this->actingAs($sales)->get(route('sales.dar.index', ['type' => 'pipeline']))->assertOk()->getContent();

        $this->assertStringContainsString('Pipeline Stage', $html);
        $this->assertStringContainsString('Warm to Hot', $html);
        $this->assertStringContainsString('System', $html);
    }

    public function test_sales_cannot_add_activity_for_another_sales_lead(): void
    {
        $sales = $this->user('sales');
        $other = $this->user('sales');
        $lead = $this->lead($other, 'PT Bukan Miliknya');

        $this->actingAs($sales)->post(route('sales.dar.store'), [
            'lead_id' => $lead->id,
            'type' => 'visit',
            'activity_date' => today()->toDateString(),
            'description' => 'Coba menulis aktivitas orang lain.',
        ])->assertForbidden();
    }

    public function test_management_cannot_add_manual_activity(): void
    {
        $management = $this->user('management');
        $sales = $this->user('sales');
        $lead = $this->lead($sales, 'PT Read Only');

        $this->actingAs($management)->post(route('sales.dar.store'), [
            'lead_id' => $lead->id,
            'type' => 'visit',
            'activity_date' => today()->toDateString(),
            'description' => 'Management tidak boleh menulis.',
        ])->assertForbidden();
    }
}
