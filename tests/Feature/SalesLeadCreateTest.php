<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Lead;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sales lapangan boleh membuat lead baru untuk dirinya sendiri, lalu
 * mengubah data prospek miliknya. Lead tetap tidak terlihat oleh Sales lain.
 */
class SalesLeadCreateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'customer_mode' => 'new',
            'customer_name' => 'PT Prospek Sales',
            'customer_address' => 'Jl. Prospek No. 1',
            'customer_contact_person' => 'Budi Prospek',
            'customer_whatsapp' => '081234567890',
            'customer_email' => 'budi@prospek.test',
            'pt_group' => 'NTI',
            'segment' => 'end_user',
            'kebutuhan' => 'Instalasi CCTV 8 channel',
            'project_name' => 'Proyek CCTV Gudang',
            'need_type' => 'cctv',
            'procurement_status' => 'new',
            'incoming_date' => now()->toDateString(),
        ], $overrides);
    }

    public function test_sales_can_open_and_submit_new_lead_for_themselves(): void
    {
        $sales = $this->userWithRole('sales');

        $this->actingAs($sales)
            ->get(route('leads.create'))
            ->assertOk()
            ->assertSee(__('Add New Lead / Opportunity'));

        $this->actingAs($sales)
            ->post(route('leads.store'), $this->payload())
            ->assertRedirect();

        $lead = Lead::where('project_name', 'Proyek CCTV Gudang')->firstOrFail();
        $this->assertSame($sales->id, (int) $lead->assigned_to);
        $this->assertSame($sales->id, (int) $lead->customer->created_by);
        $this->assertSame('cool', $lead->status);

        $this->actingAs($sales)
            ->get(route('leads.show', $lead))
            ->assertOk()
            ->assertSee('Proyek CCTV Gudang');
    }

    public function test_sales_can_create_lead_without_project_name_for_regular_installation(): void
    {
        $sales = $this->userWithRole('sales');

        $this->actingAs($sales)
            ->post(route('leads.store'), $this->payload(['project_name' => null]))
            ->assertRedirect();

        $lead = Lead::whereHas('customer', fn ($q) => $q->where('name', 'PT Prospek Sales'))->firstOrFail();
        $this->assertNull($lead->project_name);
        $this->assertSame('cctv', $lead->need_type);

        $this->actingAs($sales)
            ->get(route('leads.show', $lead))
            ->assertOk()
            ->assertSee(__('Installation'));
    }


    public function test_add_lead_button_is_on_my_leads_not_pipeline(): void
    {
        $sales = $this->userWithRole('sales');

        $this->actingAs($sales)
            ->get(route('sales.my-leads'))
            ->assertOk()
            ->assertSee(route('leads.create'), false);

        $this->actingAs($sales)
            ->get(route('leads.pipeline'))
            ->assertOk()
            ->assertDontSee(route('leads.create'), false);
    }

    public function test_sales_can_edit_own_lead_but_not_another_sales_lead(): void
    {
        $sales = $this->userWithRole('sales');
        $other = $this->userWithRole('sales');

        $own = Lead::create([
            'customer_id' => Customer::create([
                'name' => 'PT Milik Sendiri',
                'created_by' => $sales->id,
            ])->id,
            'pt_group' => 'NTI',
            'segment' => 'end_user',
            'status' => 'cool',
            'assigned_to' => $sales->id,
            'project_name' => 'Proyek Sendiri',
            'need_type' => 'cctv',
        ]);

        $foreign = Lead::create([
            'customer_id' => Customer::create([
                'name' => 'PT Milik Lain',
                'created_by' => $other->id,
            ])->id,
            'pt_group' => 'NTI',
            'segment' => 'end_user',
            'status' => 'cool',
            'assigned_to' => $other->id,
            'project_name' => 'Proyek Lain',
            'need_type' => 'network',
        ]);

        $this->actingAs($sales)
            ->get(route('leads.edit', $own))
            ->assertOk();

        $this->actingAs($sales)
            ->get(route('leads.edit', $foreign))
            ->assertForbidden();

        $this->actingAs($sales)
            ->put(route('leads.update', $own), $this->payload([
                'customer_mode' => 'existing',
                'customer_id' => $own->customer_id,
                'customer_name' => null,
                'customer_address' => null,
                'customer_contact_person' => null,
                'customer_whatsapp' => null,
                'customer_email' => null,
                'project_name' => 'Proyek Sendiri Revisi',
            ]))
            ->assertRedirect(route('leads.show', $own));

        $this->assertSame('Proyek Sendiri Revisi', $own->fresh()->project_name);
    }

    public function test_sales_cannot_delete_lead_or_attach_foreign_customer(): void
    {
        $sales = $this->userWithRole('sales');
        $other = $this->userWithRole('sales');

        $foreignCustomer = Customer::create([
            'name' => 'PT Customer Sales Lain',
            'created_by' => $other->id,
            'status' => 'lead',
        ]);

        $this->actingAs($sales)
            ->post(route('leads.store'), $this->payload([
                'customer_mode' => 'existing',
                'customer_id' => $foreignCustomer->id,
                'customer_name' => null,
                'customer_address' => null,
                'customer_contact_person' => null,
                'customer_whatsapp' => null,
                'customer_email' => null,
            ]))
            ->assertNotFound();

        $lead = Lead::create([
            'customer_id' => Customer::create([
                'name' => 'PT Hapus Sendiri',
                'created_by' => $sales->id,
            ])->id,
            'pt_group' => 'NTI',
            'segment' => 'end_user',
            'status' => 'cool',
            'assigned_to' => $sales->id,
            'project_name' => 'Proyek Hapus',
            'need_type' => 'cctv',
        ]);

        $this->actingAs($sales)
            ->delete(route('leads.destroy', $lead))
            ->assertForbidden();

        $this->assertNotNull($lead->fresh());
    }
}
