<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Lead;
use App\Models\PriceRequest;
use App\Models\Proposal;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PriceRequestQuotationLinkTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_price_request_can_be_linked_to_a_quotation(): void
    {
        $sales = User::factory()->create()->assignRole('sales');
        $customer = Customer::create(['name' => 'PT Price Link', 'pt_group' => 'NTI']);
        $lead = Lead::create([
            'customer_id' => $customer->id,
            'assigned_to' => $sales->id,
            'pt_group' => 'NTI',
            'segment' => 'vendor',
            'status' => 'hot',
        ]);

        $priceRequest = PriceRequest::create([
            'customer_id' => $customer->id,
            'requested_by' => $sales->id,
            'status' => 'completed',
            'quotation_path' => 'price-requests/link/quotation.pdf',
            'reply_path' => 'price-requests/link/reply.pdf',
        ]);

        $this->actingAs($sales)
            ->get(route('sales.proposals.create', ['price_request_id' => $priceRequest->id]))
            ->assertOk()
            ->assertSee('Lead #'.$lead->id);

        $this->actingAs($sales)->post(route('sales.proposals.store'), [
            'lead_id' => $lead->id,
            'price_request_id' => $priceRequest->id,
            'proposal_date' => now()->toDateString(),
            'items' => [[
                'description' => 'Switch 24 Port',
                'quantity' => 1,
                'unit' => 'unit',
                'unit_price' => 5000,
                'discount' => 0,
            ]],
        ])->assertRedirect();

        $proposal = Proposal::firstOrFail();
        $this->assertSame($proposal->id, $priceRequest->fresh()->proposal_id);

        $this->actingAs($sales)
            ->get(route('sales.price-requests.show', $priceRequest))
            ->assertOk()
            ->assertSee($proposal->proposal_number);

        // Request yang sudah tertaut tidak boleh membuat quotation kedua.
        $this->actingAs($sales)->post(route('sales.proposals.store'), [
            'lead_id' => $lead->id,
            'price_request_id' => $priceRequest->id,
            'proposal_date' => now()->toDateString(),
            'items' => [[
                'description' => 'Switch 24 Port',
                'quantity' => 1,
                'unit' => 'unit',
                'unit_price' => 5000,
                'discount' => 0,
            ]],
        ])->assertStatus(422);

        $this->assertSame(1, Proposal::count());
    }
}
