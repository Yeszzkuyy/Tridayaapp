<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Lead;
use App\Models\Project;
use App\Models\Proposal;
use App\Models\ProposalItem;
use App\Models\User;
use App\Models\WorkType;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Paket 1 — Tahap 1: konversi quotation ke project harus idempotent,
 * di level aplikasi (guard) maupun level database (unique constraint).
 */
class ProposalConvertUniqueTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    private function makeAcceptedProposal(User $sales): Proposal
    {
        $customer = Customer::create(['name' => 'PT Convert '.uniqid()]);
        $lead = Lead::create([
            'customer_id' => $customer->id,
            'pt_group' => 'NTI',
            'segment' => 'vendor',
            'status' => 'won',
        ]);
        $lead->update(['assigned_to' => $sales->id]);

        $proposal = Proposal::create([
            'lead_id' => $lead->id,
            'proposal_number' => 'Q/2026/10/'.uniqid(),
            'created_by' => $sales->id,
            'proposal_date' => now()->toDateString(),
            'customer' => $customer->name,
            'status' => 'accepted',
        ]);
        ProposalItem::create([
            'proposal_id' => $proposal->id,
            'description' => 'Instalasi jaringan',
            'quantity' => 1,
            'unit' => 'pcs',
            'unit_price' => 5000000,
            'discount' => 0,
            'subtotal' => 5000000,
        ]);
        $proposal->recalculate();

        return $proposal;
    }

    public function test_database_rejects_second_project_for_same_proposal(): void
    {
        $sales = User::factory()->create();
        $sales->assignRole('sales');
        $proposal = $this->makeAcceptedProposal($sales);
        $workType = WorkType::create(['name' => 'Instalasi']);
        $this->actingAs($sales);

        Project::forceCreate([
            'customer_id' => $proposal->lead->customer_id,
            'sales_user_id' => $sales->id,
            'work_type_id' => $workType->id,
            'project_name' => 'Project pertama',
            'proposal_id' => $proposal->id,
            'progress' => 0,
        ]);

        // Unique constraint di DB harus menolak project kedua untuk proposal sama.
        $thrown = null;
        try {
            Project::forceCreate([
                'customer_id' => $proposal->lead->customer_id,
                'sales_user_id' => $sales->id,
                'work_type_id' => $workType->id,
                'project_name' => 'Project duplikat',
                'proposal_id' => $proposal->id,
                'progress' => 0,
            ]);
        } catch (QueryException $e) {
            $thrown = $e;
        }

        $this->assertNotNull($thrown, 'Database harus menolak proposal_id duplikat pada projects');
        $this->assertSame(1, Project::where('proposal_id', $proposal->id)->count());
    }

    public function test_project_without_proposal_still_allowed_multiple(): void
    {
        // Kolom tetap nullable: project lama tanpa proposal tidak terpengaruh.
        $sales = User::factory()->create();
        $sales->assignRole('sales');
        $proposal = $this->makeAcceptedProposal($sales);
        $workType = WorkType::create(['name' => 'Instalasi']);
        $this->actingAs($sales);

        foreach (['Project A', 'Project B'] as $name) {
            Project::forceCreate([
                'customer_id' => $proposal->lead->customer_id,
                'sales_user_id' => $sales->id,
                'work_type_id' => $workType->id,
                'project_name' => $name,
                'proposal_id' => null,
                'progress' => 0,
            ]);
        }

        $this->assertSame(2, Project::whereNull('proposal_id')->count());
    }

    public function test_repeated_convert_request_is_rejected_by_app_guard(): void
    {
        $sales = User::factory()->create();
        $sales->assignRole('sales');
        $proposal = $this->makeAcceptedProposal($sales);
        $workType = WorkType::create(['name' => 'Instalasi']);

        $this->actingAs($sales)->post(route('sales.proposals.convert', $proposal), [
            'work_type_id' => $workType->id,
        ])->assertRedirect();

        // Permintaan berulang ditolak (422), project tidak diduplikasi.
        $this->actingAs($sales)->post(route('sales.proposals.convert', $proposal), [
            'work_type_id' => $workType->id,
        ])->assertStatus(422);

        $this->assertSame(1, Project::where('proposal_id', $proposal->id)->count());
    }
}
