<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\FollowUp;
use App\Models\Lead;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Badge "New" di My Leads = lead belum diakui. Sesuai keputusan user, lead
 * dianggap sudah diakui begitu sales mencatat follow up, catatan, atau jadwal
 * berikutnya - bukan hanya saat tahap lead diubah.
 */
class MyLeadsNewBadgeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    private function sales(): User
    {
        $user = User::factory()->create();
        $user->assignRole('sales');

        return $user;
    }

    private function lead(User $sales, array $overrides = []): Lead
    {
        $customer = Customer::create([
            'name' => 'PT Baru '.uniqid(),
            'company' => 'PT Baru',
            'pt_group' => 'NTI',
            'contact_person' => 'Budi',
            'address' => 'Jl. Merdeka 1',
            'email' => 'budi'.uniqid().'@example.test',
            'phone' => '0215550001',
            'whatsapp' => '628123450001',
        ]);

        return Lead::create(array_merge([
            'customer_id' => $customer->id,
            'pt_group' => 'NTI',
            'segment' => 'vendor',
            'status' => 'cool',
            'assigned_to' => $sales->id,
            'project_name' => 'Proyek CCTV',
            'incoming_date' => today()->toDateString(),
        ], $overrides));
    }

    public function test_saving_the_first_note_clears_the_new_badge(): void
    {
        $sales = $this->sales();
        $lead = $this->lead($sales);

        $this->assertNull($lead->acknowledged_at);
        $this->assertStringContainsString('>New</span>', $this->myLeadsHtml($sales));

        $this->actingAs($sales)
            ->patchJson(route('sales.my-leads.note', $lead), ['note' => 'Sudah dihubungi via WA.'])
            ->assertOk();

        $this->assertNotNull($lead->fresh()->acknowledged_at);
        $this->assertStringNotContainsString('>New</span>', $this->myLeadsHtml($sales));
    }

    public function test_recording_the_first_follow_up_clears_the_new_badge(): void
    {
        $sales = $this->sales();
        $lead = $this->lead($sales);

        $this->actingAs($sales)->post(route('sales.follow-ups.store'), [
            'customer_id' => $lead->customer_id,
            'lead_id' => $lead->id,
            'type' => 'whatsapp',
            'description' => 'Kirim penawaran awal.',
        ])->assertRedirect();

        $this->assertNotNull($lead->fresh()->acknowledged_at);
        $this->assertSame(1, FollowUp::where('lead_id', $lead->id)->count());
    }

    public function test_setting_the_next_follow_up_clears_the_new_badge(): void
    {
        $sales = $this->sales();
        $lead = $this->lead($sales);

        $this->actingAs($sales)
            ->patchJson(route('sales.my-leads.next-follow-up', $lead), [
                'next_follow_up_date' => today()->addDay()->toDateString(),
            ])
            ->assertOk();

        $this->assertNotNull($lead->fresh()->acknowledged_at);
    }

    public function test_existing_acknowledgement_time_is_not_overwritten(): void
    {
        $sales = $this->sales();
        $lead = $this->lead($sales);
        $lead->forceFill(['acknowledged_at' => '2026-01-02 08:30:00'])->save();

        $this->actingAs($sales)
            ->patchJson(route('sales.my-leads.note', $lead), ['note' => 'Catatan lanjutan.'])
            ->assertOk();

        $this->assertSame(
            '2026-01-02 08:30:00',
            $lead->fresh()->acknowledged_at->toDateTimeString(),
        );
    }

    public function test_opening_a_contact_channel_is_not_counted_as_acknowledgement(): void
    {
        $sales = $this->sales();
        $lead = $this->lead($sales);

        $this->actingAs($sales)
            ->postJson(route('sales.my-leads.contact', $lead), ['channel' => 'whatsapp'])
            ->assertOk();

        $this->assertNull($lead->fresh()->acknowledged_at);
    }

    private function myLeadsHtml(User $sales): string
    {
        return $this->actingAs($sales)->get(route('sales.my-leads'))->assertOk()->getContent();
    }
}
