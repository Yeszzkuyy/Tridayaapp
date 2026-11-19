<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerContact;
use App\Models\FollowUp;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MyLeadsWorkspaceTest extends TestCase
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

    private function customer(string $name, ?string $pt = 'NTI'): Customer
    {
        return Customer::create([
            'name' => $name,
            'company' => $name,
            'pt_group' => $pt,
            'contact_person' => 'Budi',
            'address' => 'Jl. Merdeka 1',
            'email' => 'budi@example.test',
            'phone' => '0215550001',
            'whatsapp' => '628123450001',
        ]);
    }

    private function lead(User $sales, array $overrides = []): Lead
    {
        $customer = $overrides['customer'] ?? $this->customer('PT Workspace '.uniqid());
        unset($overrides['customer']);

        return Lead::create(array_merge([
            'customer_id' => $customer->id,
            'pt_group' => $customer->pt_group ?? 'NTI',
            'segment' => 'vendor',
            'status' => 'warm',
            'assigned_to' => $sales->id,
            'project_name' => 'Proyek CCTV',
            'kebutuhan' => 'Butuh CCTV 8 titik',
            'incoming_date' => today()->toDateString(),
        ], $overrides));
    }

    public function test_my_leads_shows_workspace_columns_and_only_own_leads(): void
    {
        $sales = $this->sales();
        $other = $this->sales();

        $mine = $this->lead($sales);
        $this->lead($other, ['project_name' => 'Proyek Sales Lain']);

        $html = $this->actingAs($sales)->get(route('sales.my-leads'))->assertOk()->getContent();

        foreach (['Project Name', 'Latest Note', 'Last Activity', 'Next Follow Up', 'PIC', 'Contact'] as $label) {
            $this->assertStringContainsString($label, $html);
        }

        $this->assertStringContainsString($mine->customer->name, $html);
        $this->assertStringNotContainsString('Proyek Sales Lain', $html);
        $this->assertStringContainsString('data-contact-channel="whatsapp"', $html);
        $this->assertStringContainsString('data-tour="followup"', $html);
        $this->assertStringContainsString('myLeadsRow({', $html);
        $this->assertStringNotContainsString('@js(', $html);
    }

    public function test_inline_note_is_appended_and_old_notes_stay(): void
    {
        $sales = $this->sales();
        $lead = $this->lead($sales);

        FollowUp::create([
            'customer_id' => $lead->customer_id,
            'lead_id' => $lead->id,
            'type' => 'note',
            'description' => 'Catatan lama',
            'follow_up_date' => today()->toDateString(),
            'created_by' => $sales->id,
        ]);

        $this->actingAs($sales)
            ->patchJson(route('sales.my-leads.note', $lead), ['note' => 'Catatan baru'])
            ->assertOk()
            ->assertJsonPath('note', 'Catatan baru');

        $this->assertSame(2, FollowUp::where('lead_id', $lead->id)->count());
        $this->assertDatabaseHas('follow_ups', ['lead_id' => $lead->id, 'description' => 'Catatan lama']);

        // Kirim ulang note yang sama (double click) tidak menggandakan.
        $this->actingAs($sales)
            ->patchJson(route('sales.my-leads.note', $lead), ['note' => 'Catatan baru'])
            ->assertOk();

        $this->assertSame(2, FollowUp::where('lead_id', $lead->id)->count());
        $this->assertSame(2, FollowUp::where('lead_id', $lead->id)->where('type', 'note')->count());
    }

    public function test_other_sales_cannot_write_note_for_my_lead(): void
    {
        $sales = $this->sales();
        $other = $this->sales();
        $lead = $this->lead($sales);

        $this->actingAs($other)
            ->patchJson(route('sales.my-leads.note', $lead), ['note' => 'Hack'])
            ->assertForbidden();
    }

    public function test_contact_click_is_recorded_once_and_never_marks_sent(): void
    {
        $sales = $this->sales();
        $lead = $this->lead($sales);

        $this->actingAs($sales)
            ->postJson(route('sales.my-leads.contact', $lead), ['channel' => 'whatsapp'])
            ->assertOk()
            ->assertJsonPath('duplicate', false);

        $this->actingAs($sales)
            ->postJson(route('sales.my-leads.contact', $lead), ['channel' => 'whatsapp'])
            ->assertOk()
            ->assertJsonPath('duplicate', true);

        $this->assertSame(1, LeadActivity::where('lead_id', $lead->id)->where('action', 'contact_initiated')->count());
        $this->assertSame(0, FollowUp::where('lead_id', $lead->id)->count());
    }

    public function test_next_follow_up_creates_one_active_queue_and_can_be_rescheduled(): void
    {
        $sales = $this->sales();
        $lead = $this->lead($sales);

        $this->actingAs($sales)
            ->patchJson(route('sales.my-leads.next-follow-up', $lead), [
                'next_follow_up_date' => today()->addDay()->toDateString(),
                'next_follow_up_time' => '09:30',
            ])
            ->assertOk();

        $this->assertSame(1, FollowUp::where('lead_id', $lead->id)->active()->count());

        $this->actingAs($sales)
            ->patchJson(route('sales.my-leads.next-follow-up', $lead), [
                'next_follow_up_date' => today()->addDays(3)->toDateString(),
            ])
            ->assertOk();

        $this->assertSame(1, FollowUp::where('lead_id', $lead->id)->active()->count());
        $this->assertSame(today()->addDays(3)->toDateString(), FollowUp::where('lead_id', $lead->id)->active()->first()->follow_up_date->toDateString());
    }

    public function test_filter_next_follow_up_today_only_shows_due_lead(): void
    {
        $sales = $this->sales();

        $due = $this->lead($sales, ['project_name' => 'Proyek Due']);
        $later = $this->lead($sales, ['project_name' => 'Proyek Nanti']);

        FollowUp::create([
            'customer_id' => $due->customer_id,
            'lead_id' => $due->id,
            'description' => '',
            'follow_up_date' => today()->toDateString(),
            'created_by' => $sales->id,
        ]);
        FollowUp::create([
            'customer_id' => $later->customer_id,
            'lead_id' => $later->id,
            'description' => '',
            'follow_up_date' => today()->addDays(2)->toDateString(),
            'created_by' => $sales->id,
        ]);

        $html = $this->actingAs($sales)
            ->get(route('sales.my-leads', ['next_follow_up' => 'today']))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Proyek Due', $html);
        $this->assertStringNotContainsString('Proyek Nanti', $html);
    }

    public function test_lead_color_can_be_changed_and_cleared(): void
    {
        $sales = $this->sales();
        $lead = $this->lead($sales);

        $this->actingAs($sales)
            ->patchJson(route('sales.my-leads.color', $lead), ['color_tag' => 'red'])
            ->assertOk()
            ->assertJsonPath('color', 'red');

        $this->assertSame('red', $lead->fresh()->color_tag);

        $this->actingAs($sales)
            ->patchJson(route('sales.my-leads.color', $lead), ['color_tag' => null])
            ->assertOk()
            ->assertJsonPath('color', '');

        $this->assertNull($lead->fresh()->color_tag);
    }

    public function test_only_owner_can_change_lead_color_and_invalid_color_is_rejected(): void
    {
        $sales = $this->sales();
        $other = $this->sales();
        $lead = $this->lead($sales);

        $this->actingAs($other)
            ->patchJson(route('sales.my-leads.color', $lead), ['color_tag' => 'green'])
            ->assertForbidden();

        $this->actingAs($sales)
            ->patchJson(route('sales.my-leads.color', $lead), ['color_tag' => 'blue'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['color_tag']);

        $this->assertNull($lead->fresh()->color_tag);
    }

    public function test_my_leads_shows_note_form_below_existing_note_and_color_picker(): void
    {
        $sales = $this->sales();
        $lead = $this->lead($sales, ['color_tag' => 'yellow']);

        $html = $this->actingAs($sales)->get(route('sales.my-leads'))->assertOk()->getContent();

        $this->assertStringContainsString('x-show="editingNote"', $html);
        $this->assertStringContainsString('Lead color', $html);
        $this->assertStringContainsString('bg-amber-400', $html);
    }

    public function test_latest_note_keeps_multiline_text(): void
    {
        $sales = $this->sales();
        $lead = $this->lead($sales);

        FollowUp::create([
            'customer_id' => $lead->customer_id,
            'lead_id' => $lead->id,
            'type' => 'note',
            'description' => "Baris satu\nBaris dua\nBaris tiga",
            'follow_up_date' => today()->toDateString(),
            'created_by' => $sales->id,
        ]);

        $html = $this->actingAs($sales)->get(route('sales.my-leads'))->assertOk()->getContent();

        $this->assertStringContainsString('whitespace-pre-line', $html);
        $this->assertStringContainsString('Baris satu', $html);
        $this->assertTrue(strpos($html, 'Baris satu') < strpos($html, 'Baris dua'));
        $this->assertTrue(strpos($html, 'Baris dua') < strpos($html, 'Baris tiga'));
        $this->assertStringNotContainsString('View all', $html);
    }

    public function test_won_is_rejected_without_accepted_quotation(): void
    {
        $sales = $this->sales();
        $customer = $this->customer('PT Gate Won');
        CustomerContact::create([
            'customer_id' => $customer->id,
            'name' => 'Budi',
            'whatsapp' => '628123450001',
            'email' => 'budi@example.test',
            'is_primary' => true,
        ]);
        $lead = $this->lead($sales, ['customer' => $customer]);

        FollowUp::create([
            'customer_id' => $customer->id,
            'lead_id' => $lead->id,
            'type' => 'call',
            'description' => 'Sudah follow up.',
            'follow_up_date' => today()->toDateString(),
            'created_by' => $sales->id,
        ]);

        $this->actingAs($sales)
            ->patchJson(route('leads.update-status', $lead), ['status' => 'won'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['status']);

        $this->assertSame('warm', $lead->fresh()->status);
    }
}
