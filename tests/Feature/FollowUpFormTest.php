<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\FollowUp;
use App\Models\Lead;
use App\Models\Meeting;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FollowUpFormTest extends TestCase
{
    use RefreshDatabase;

    private function salesMgk(): User
    {
        $this->seed(RoleAndPermissionSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('sales-mgk');

        return $user;
    }

    private function seedCustomers(): array
    {
        return [
            'mgk' => Customer::create(['name' => 'PT MGK Client', 'pt_group' => 'MGK']),
            'nti' => Customer::create(['name' => 'PT NTI Client', 'pt_group' => 'NTI']),
            'general' => Customer::create(['name' => 'PT General Client', 'pt_group' => null]),
        ];
    }

    public function test_create_page_scopes_customers_by_pt(): void
    {
        $sales = $this->salesMgk();
        $this->seedCustomers();

        $html = $this->actingAs($sales)->get(route('sales.follow-ups.create'))
            ->assertOk()->getContent();

        $this->assertStringContainsString('PT MGK Client (MGK)', $html);
        $this->assertStringContainsString('PT General Client', $html);
        $this->assertStringNotContainsString('PT NTI Client', $html);
    }

    public function test_plain_sales_and_admin_see_all_customers(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);
        $plain = User::factory()->create();
        $plain->assignRole('sales');
        $this->seedCustomers();

        $html = $this->actingAs($plain)->get(route('sales.follow-ups.create'))
            ->assertOk()->getContent();

        foreach (['PT MGK Client', 'PT NTI Client', 'PT General Client'] as $name) {
            $this->assertStringContainsString($name, $html);
        }
    }

    public function test_store_rejects_customer_outside_user_pt(): void
    {
        $sales = $this->salesMgk();
        $customers = $this->seedCustomers();

        $this->actingAs($sales)->post(route('sales.follow-ups.store'), [
            'customer_id' => $customers['nti']->id,
            'description' => 'Coba NTI.',
        ])->assertForbidden();
    }

    public function test_store_rejects_lead_and_meeting_from_other_customer(): void
    {
        $sales = $this->salesMgk();
        $customers = $this->seedCustomers();
        $otherLead = Lead::create([
            'customer_id' => $customers['nti']->id,
            'pt_group' => 'NTI',
            'segment' => 'vendor',
            'status' => 'cool',
            'assigned_to' => $sales->id,
        ]);
        $otherMeeting = Meeting::create([
            'customer_id' => $customers['nti']->id,
            'meeting_date' => now()->toDateString(),
            'notes' => 'Meeting NTI.',
            'created_by' => $sales->id,
        ]);

        $this->actingAs($sales)->post(route('sales.follow-ups.store'), [
            'customer_id' => $customers['mgk']->id,
            'lead_id' => $otherLead->id,
            'description' => 'Lead silang.',
        ])->assertStatus(422);

        $this->actingAs($sales)->post(route('sales.follow-ups.store'), [
            'customer_id' => $customers['mgk']->id,
            'meeting_id' => $otherMeeting->id,
            'description' => 'Meeting silang.',
        ])->assertStatus(422);
    }

    public function test_type_options_offer_follow_up_activities(): void
    {
        $sales = $this->salesMgk();

        $html = $this->actingAs($sales)->get(route('sales.follow-ups.create'))
            ->assertOk()->getContent();

        foreach (['value="whatsapp"', 'value="call"', 'value="email"', 'value="meeting"', 'value="proposal"'] as $option) {
            $this->assertStringContainsString($option, $html);
        }
        $this->assertStringNotContainsString('value="follow_up"', $html);
    }

    public function test_lead_options_show_unique_labels(): void
    {
        $sales = $this->salesMgk();
        $customers = $this->seedCustomers();
        Lead::create([
            'customer_id' => $customers['mgk']->id,
            'pt_group' => 'MGK',
            'segment' => 'vendor',
            'status' => 'cool',
            'incoming_date' => now()->toDateString(),
            'assigned_to' => $sales->id,
        ]);

        $html = $this->actingAs($sales)->get(route('sales.follow-ups.create'))
            ->assertOk()->getContent();

        $this->assertStringContainsString('Lead #', $html);
    }

    public function test_store_accepts_matching_relations(): void
    {
        $sales = $this->salesMgk();
        $customers = $this->seedCustomers();
        $lead = Lead::create([
            'customer_id' => $customers['mgk']->id,
            'pt_group' => 'MGK',
            'segment' => 'vendor',
            'status' => 'cool',
            'assigned_to' => $sales->id,
        ]);
        $meeting = Meeting::create([
            'customer_id' => $customers['mgk']->id,
            'meeting_date' => now()->toDateString(),
            'notes' => 'Kickoff.',
            'created_by' => $sales->id,
        ]);

        $this->actingAs($sales)->post(route('sales.follow-ups.store'), [
            'customer_id' => $customers['mgk']->id,
            'lead_id' => $lead->id,
            'meeting_id' => $meeting->id,
            'type' => 'whatsapp',
            'description' => 'Kirim penawaran.',
        ])->assertRedirect(route('sales.follow-ups.index'));

        $fu = FollowUp::first();
        $this->assertSame($lead->id, $fu->lead_id);
        $this->assertSame($meeting->id, $fu->meeting_id);
    }

    public function test_edit_page_renders_with_current_values(): void
    {
        $sales = $this->salesMgk();
        $customers = $this->seedCustomers();
        $fu = FollowUp::create([
            'customer_id' => $customers['mgk']->id,
            'description' => 'Follow up lama.',
            'type' => 'call',
            'created_by' => $sales->id,
        ]);

        $html = $this->actingAs($sales)->get(route('sales.follow-ups.edit', $fu))
            ->assertOk()->getContent();

        $this->assertStringContainsString('PT MGK Client (MGK)', $html);
        $this->assertStringContainsString('Follow up lama.', $html);
    }

    public function test_index_renders_follow_ups_only(): void
    {
        $this->seed(\Database\Seeders\RoleAndPermissionSeeder::class);
        $sales = User::factory()->create();
        $sales->assignRole('sales');
        $customer = Customer::create(['name' => 'PT Index Penuh', 'pt_group' => 'MGK']);
        FollowUp::create([
            'customer_id' => $customer->id,
            'description' => 'Follow up index penuh.',
            'type' => 'whatsapp',
            'follow_up_date' => now()->toDateString(),
            'created_by' => $sales->id,
        ]);

        $html = $this->actingAs($sales)->get(route('sales.follow-ups.index'))
            ->assertOk()->getContent();

        $this->assertStringContainsString('Follow up index penuh.', $html);
        $this->assertStringContainsString('PT Index Penuh', $html);
        // Halaman Follow Up murni: tanpa seksi Meeting / Daily Update.
        $this->assertStringNotContainsString('Daily Update', $html);
        $this->assertStringNotContainsString('id="meetings-table"', $html);
    }

    public function test_index_shows_real_lead_status_not_new_label(): void
    {
        $sales = $this->salesMgk();
        $customers = $this->seedCustomers();
        $lead = Lead::create([
            'customer_id' => $customers['mgk']->id,
            'pt_group' => 'MGK',
            'segment' => 'vendor',
            'status' => 'cool',
            'assigned_to' => $sales->id,
        ]);
        FollowUp::create([
            'customer_id' => $customers['mgk']->id,
            'lead_id' => $lead->id,
            'description' => 'Cek label status.',
            'follow_up_date' => now()->toDateString(),
            'created_by' => $sales->id,
        ]);

        $html = $this->actingAs($sales)->get(route('sales.follow-ups.index'))
            ->assertOk()->getContent();

        // Status asli (Cool) tampil, bukan label New khas pipeline virtual.
        $this->assertStringContainsString('>Cool<', $html);
        $this->assertStringNotContainsString('>New<', $html);
    }

    public function test_index_uses_glider_for_status_filter(): void
    {
        $sales = $this->salesMgk();

        $html = $this->actingAs($sales)->get(route('sales.follow-ups.index'))
            ->assertOk()->getContent();

        $this->assertStringContainsString('data-glide-mount', $html);
        $this->assertStringContainsString('name="overdue"', $html);
    }

    public function test_filter_partials_have_no_wrapper_id(): void
    {
        // Partial di-inject via innerHTML: kalau membawa id wrapper,
        // tabel bersarang tiap submit filter.
        $this->seed(\Database\Seeders\RoleAndPermissionSeeder::class);
        $sales = User::factory()->create();
        $sales->assignRole('sales');

        $res = $this->actingAs($sales)
            ->getJson(route('sales.follow-ups.index', ['search' => 'x']))
            ->assertOk();
        $this->assertStringNotContainsString('id="followups-table"', $res->json('html'));
    }

    public function test_full_page_has_single_table_wrapper(): void
    {
        $sales = $this->salesMgk();

        $html = $this->actingAs($sales)->get(route('sales.follow-ups.index'))
            ->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, 'id="followups-table"'));
    }

    public function test_filter_form_skips_glider_submit_guard(): void
    {
        // Guard glider mengunci tombol + overlay selamanya pada submit AJAX.
        $sales = $this->salesMgk();

        $html = $this->actingAs($sales)->get(route('sales.follow-ups.index'))
            ->assertOk()->getContent();

        $this->assertStringContainsString('data-submit-guarded', $html);
    }
}
