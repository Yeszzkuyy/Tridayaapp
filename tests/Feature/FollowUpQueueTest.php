<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\FollowUp;
use App\Models\Lead;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FollowUpQueueTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    private function sales(string $role = 'sales'): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function lead(User $sales, string $name = null): Lead
    {
        $customer = Customer::create([
            'name' => $name ?? 'PT Queue '.uniqid(),
            'company' => $name ?? 'PT Queue',
            'pt_group' => 'MGK',
            'contact_person' => 'Sari',
            'whatsapp' => '628123450009',
            'email' => 'sari@example.test',
        ]);

        return Lead::create([
            'customer_id' => $customer->id,
            'pt_group' => 'MGK',
            'segment' => 'vendor',
            'status' => 'warm',
            'assigned_to' => $sales->id,
            'project_name' => 'Proyek Queue',
            'incoming_date' => today()->toDateString(),
        ]);
    }

    private function followUp(User $sales, Lead $lead, array $overrides = []): FollowUp
    {
        return FollowUp::create(array_merge([
            'customer_id' => $lead->customer_id,
            'lead_id' => $lead->id,
            'type' => 'whatsapp',
            'description' => 'Follow up '.uniqid(),
            'follow_up_date' => today()->toDateString(),
            'status' => 'pending',
            'created_by' => $sales->id,
        ], $overrides));
    }

    public function test_tabs_split_due_today_upcoming_overdue_completed(): void
    {
        $sales = $this->sales();
        $lead = $this->lead($sales);

        $today = $this->followUp($sales, $lead, ['description' => 'FU hari ini']);
        $upcoming = $this->followUp($sales, $lead, ['description' => 'FU nanti', 'follow_up_date' => today()->addDays(2)->toDateString()]);
        $overdue = $this->followUp($sales, $lead, ['description' => 'FU lewat', 'follow_up_date' => today()->subDays(2)->toDateString()]);
        $completed = $this->followUp($sales, $lead, [
            'description' => 'FU selesai',
            'follow_up_date' => today()->subDay()->toDateString(),
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        $html = fn (string $tab) => $this->actingAs($sales)
            ->getJson(route('sales.follow-ups.index', ['tab' => $tab]))
            ->assertOk()
            ->json('html');

        $this->assertTrue(str_contains($html('due_today'), 'FU hari ini'), 'due_today harus memuat FU hari ini');
        $this->assertTrue(str_contains($html('due_today'), 'x-on:click="openComplete('), 'tombol Complete harus terhubung ke modal');

        $admin = $this->sales('admin');
        $adminHtml = $this->actingAs($admin)
            ->getJson(route('sales.follow-ups.index', ['tab' => 'due_today']))
            ->assertOk()
            ->json('html');
        $this->assertFalse(str_contains($adminHtml, 'openComplete('), 'role read-only tidak boleh melihat tombol Complete');
        $this->assertFalse(str_contains($html('due_today'), 'FU nanti'), 'due_today tidak boleh memuat FU nanti');
        $this->assertFalse(str_contains($html('due_today'), 'FU lewat'), 'due_today tidak boleh memuat FU lewat');

        $this->assertTrue(str_contains($html('upcoming'), 'FU nanti'), 'upcoming harus memuat FU nanti');
        $this->assertTrue(str_contains($html('overdue'), 'FU lewat'), 'overdue harus memuat FU lewat');
        $this->assertTrue(str_contains($html('completed'), 'FU selesai'), 'completed harus memuat FU selesai');
        $this->assertFalse(str_contains($html('completed'), 'FU hari ini'), 'completed tidak boleh memuat FU hari ini');

        // Tab All Open tetap kompatibel untuk tautan lama.
        $all = $html('all');
        $this->assertTrue(str_contains($all, 'FU hari ini'), 'all harus memuat FU hari ini');
        $this->assertTrue(str_contains($all, 'FU nanti'), 'all harus memuat FU nanti');
        $this->assertTrue(str_contains($all, 'FU lewat'), 'all harus memuat FU lewat');
    }

    public function test_complete_saves_outcome_and_creates_next_queue_in_one_flow(): void
    {
        $sales = $this->sales();
        $lead = $this->lead($sales);
        $fu = $this->followUp($sales, $lead, ['description' => 'Telepon pertama']);

        $this->actingAs($sales)
            ->postJson(route('sales.follow-ups.complete', $fu), [
                'outcome' => 'Customer minta penawaran resmi.',
                'next_follow_up_date' => today()->addDays(2)->toDateString(),
                'next_follow_up_time' => '10:00',
            ])
            ->assertOk();

        $fu->refresh();
        $this->assertSame('completed', $fu->status);
        $this->assertNotNull($fu->completed_at);
        $this->assertSame('Customer minta penawaran resmi.', $fu->outcome);

        $active = FollowUp::where('lead_id', $lead->id)->active()->get();
        $this->assertCount(1, $active);
        $this->assertSame(today()->addDays(2)->toDateString(), $active->first()->follow_up_date->toDateString());
        $this->assertSame('10:00', substr((string) $active->first()->activity_time, 0, 5));

        // Histori lama tetap ada.
        $this->assertDatabaseHas('follow_ups', ['id' => $fu->id, 'description' => 'Telepon pertama']);
    }

    public function test_completing_twice_is_rejected_and_does_not_duplicate_next(): void
    {
        $sales = $this->sales();
        $lead = $this->lead($sales);
        $fu = $this->followUp($sales, $lead);

        $payload = [
            'outcome' => 'Sudah dihubungi.',
            'next_follow_up_date' => today()->addDay()->toDateString(),
        ];

        $this->actingAs($sales)->postJson(route('sales.follow-ups.complete', $fu), $payload)->assertOk();
        $this->actingAs($sales)->postJson(route('sales.follow-ups.complete', $fu), $payload)->assertStatus(422);

        $this->assertSame(1, FollowUp::where('lead_id', $lead->id)->active()->count());
    }

    public function test_next_follow_up_updates_instead_of_creating_second_active(): void
    {
        $sales = $this->sales();
        $lead = $this->lead($sales);
        $first = $this->followUp($sales, $lead);

        $this->actingAs($sales)->postJson(route('sales.follow-ups.complete', $first), [
            'outcome' => 'Hasil pertama',
            'next_follow_up_date' => today()->addDay()->toDateString(),
        ])->assertOk();

        $second = FollowUp::where('lead_id', $lead->id)->active()->firstOrFail();

        $this->actingAs($sales)->postJson(route('sales.follow-ups.complete', $second), [
            'outcome' => 'Hasil kedua',
            'next_follow_up_date' => today()->addDays(5)->toDateString(),
        ])->assertOk();

        $this->assertSame(1, FollowUp::where('lead_id', $lead->id)->active()->count());
        $this->assertSame(2, FollowUp::where('lead_id', $lead->id)->completed()->count());
        $this->assertSame(today()->addDays(5)->toDateString(), FollowUp::where('lead_id', $lead->id)->active()->first()->follow_up_date->toDateString());
    }

    public function test_contact_click_marks_process_but_not_completed(): void
    {
        $sales = $this->sales();
        $lead = $this->lead($sales);
        $fu = $this->followUp($sales, $lead);

        $this->actingAs($sales)
            ->postJson(route('sales.my-leads.contact', $lead), [
                'channel' => 'whatsapp',
                'follow_up_id' => $fu->id,
            ])
            ->assertOk();

        $fu->refresh();
        $this->assertSame('process', $fu->status);
        $this->assertNull($fu->completed_at);
    }

    public function test_sales_per_pt_only_sees_own_queue(): void
    {
        $mine = $this->sales('sales-mgk');
        $other = $this->sales('sales-mgk');

        $myLead = $this->lead($mine, 'PT Milik Saya');
        $otherLead = $this->lead($other, 'PT Milik Orang');

        $this->followUp($mine, $myLead, ['description' => 'Antrean saya']);
        $this->followUp($other, $otherLead, ['description' => 'Antrean orang lain']);

        $html = $this->actingAs($mine)
            ->getJson(route('sales.follow-ups.index', ['tab' => 'all']))
            ->assertOk()
            ->json('html');

        $this->assertStringContainsString('Antrean saya', $html);
        $this->assertStringNotContainsString('Antrean orang lain', $html);
    }

    public function test_completed_tab_keeps_history_after_new_activity(): void
    {
        $sales = $this->sales();
        $lead = $this->lead($sales);

        $first = $this->followUp($sales, $lead, ['description' => 'Aktivitas lama']);
        $this->actingAs($sales)->postJson(route('sales.follow-ups.complete', $first), [
            'outcome' => 'Selesai lama',
        ])->assertOk();

        $second = $this->followUp($sales, $lead, ['description' => 'Aktivitas baru']);
        $this->actingAs($sales)->postJson(route('sales.follow-ups.complete', $second), [
            'outcome' => 'Selesai baru',
        ])->assertOk();

        $html = $this->actingAs($sales)
            ->getJson(route('sales.follow-ups.index', ['tab' => 'completed']))
            ->assertOk()
            ->json('html');

        $this->assertStringContainsString('Selesai lama', $html);
        $this->assertStringContainsString('Selesai baru', $html);
    }
}
