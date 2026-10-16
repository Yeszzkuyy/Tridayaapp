<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Lead;
use App\Models\LeadTask;
use App\Models\TechnicalRequest;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesRequestHubTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(string $role): User
    {
        $this->seed(RoleAndPermissionSeeder::class);
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function makeLead(?User $assignee = null): Lead
    {
        $customer = Customer::create(['name' => 'PT Hub '.uniqid()]);

        return Lead::create([
            'customer_id' => $customer->id,
            'pt_group' => 'NTI',
            'segment' => 'vendor',
            'status' => 'warm',
            'assigned_to' => $assignee?->id,
        ]);
    }

    private function makeTask(Lead $lead, ?User $creator = null): LeadTask
    {
        return LeadTask::create([
            'lead_id' => $lead->id,
            'title' => 'Buatkan penawaran',
            'status' => 'todo',
            'created_by' => $creator?->id,
        ]);
    }

    private function makeTechRequest(User $requester, string $type = 'survey', array $overrides = []): TechnicalRequest
    {
        $lead = Lead::create([
            'customer_id' => Customer::create(['name' => 'PT Hub '.uniqid()])->id,
            'status' => 'warm',
            'assigned_to' => $requester->id,
        ]);

        return TechnicalRequest::create(array_merge([
            'lead_id' => $lead->id,
            'requested_by' => $requester->id,
            'request_type' => $type,
            'title' => 'Request '.$type,
            'status' => 'waiting_lead',
        ], $overrides));
    }

    public function test_sales_sees_hub_with_all_three_tabs(): void
    {
        $sales = $this->userWithRole('sales');

        $html = $this->actingAs($sales)->get(route('sales.requests.index'))
            ->assertOk()->getContent();

        $this->assertStringContainsString('Request Inside Sales', $html);
        $this->assertStringContainsString('Request Price', $html);
        $this->assertStringContainsString('Engineer', $html);
    }

    public function test_hub_defaults_to_inside_sales_tab(): void
    {
        $sales = $this->userWithRole('sales');
        $lead = $this->makeLead($sales);
        $this->makeTask($lead, $sales);

        $html = $this->actingAs($sales)->get(route('sales.requests.index'))
            ->assertOk()->getContent();

        $this->assertStringContainsString('Buatkan penawaran', $html);
    }

    public function test_price_tab_lists_only_estimates(): void
    {
        $sales = $this->userWithRole('sales');
        $estimate = $this->makeTechRequest($sales, 'estimate');
        $survey = $this->makeTechRequest($sales, 'survey');

        $html = $this->actingAs($sales)->get(route('sales.requests.index', ['tab' => 'price']))
            ->assertOk()->getContent();

        $this->assertStringContainsString($estimate->title, $html);
        $this->assertStringNotContainsString($survey->title, $html);
    }

    public function test_engineer_tab_excludes_estimates(): void
    {
        $sales = $this->userWithRole('sales');
        $estimate = $this->makeTechRequest($sales, 'estimate');
        $survey = $this->makeTechRequest($sales, 'survey');

        $html = $this->actingAs($sales)->get(route('sales.requests.index', ['tab' => 'engineer']))
            ->assertOk()->getContent();

        $this->assertStringContainsString($survey->title, $html);
        $this->assertStringNotContainsString($estimate->title, $html);
    }

    public function test_sales_only_sees_own_requests(): void
    {
        $mine = $this->userWithRole('sales');
        $other = User::factory()->create();
        $other->assignRole('sales');
        $this->makeTask($this->makeLead($mine), $mine);
        $foreignTask = $this->makeTask($this->makeLead($other), $other);
        $foreignReq = $this->makeTechRequest($other, 'survey');

        $html = $this->actingAs($mine)->get(route('sales.requests.index'))
            ->assertOk()->getContent();
        $this->assertStringContainsString('Buatkan penawaran', $html);
        $this->assertStringNotContainsString($foreignTask->lead->customer->name, $html);

        $html = $this->actingAs($mine)->get(route('sales.requests.index', ['tab' => 'engineer']))
            ->assertOk()->getContent();
        $this->assertStringNotContainsString($foreignReq->title, $html);
    }

    public function test_management_sees_all_requests(): void
    {
        $mgmt = $this->userWithRole('management');
        $sales = User::factory()->create();
        $sales->assignRole('sales');
        $req = $this->makeTechRequest($sales, 'survey');

        $html = $this->actingAs($mgmt)->get(route('sales.requests.index', ['tab' => 'engineer']))
            ->assertOk()->getContent();

        $this->assertStringContainsString($req->title, $html);
    }

    public function test_inside_sales_role_keeps_inside_sales_menu(): void
    {
        $inside = $this->userWithRole('inside-sales');

        $html = $this->actingAs($inside)->get(route('lead-tasks.index'))
            ->assertOk()->getContent();

        $this->assertStringContainsString('Inside Sales', $html);
        $this->assertStringNotContainsString('/sales/requests', $html);
    }

    public function test_sales_menu_shows_request_instead_of_inside_sales(): void
    {
        $sales = $this->userWithRole('sales');

        $html = $this->actingAs($sales)->get(route('sales.my-leads'))
            ->assertOk()->getContent();

        $this->assertStringContainsString('/sales/requests', $html);
    }

    public function test_create_without_lead_shows_only_own_leads(): void
    {
        $sales = $this->userWithRole('sales');
        $mine = $this->makeLead($sales);
        $other = User::factory()->create();
        $other->assignRole('sales');
        $foreign = $this->makeLead($other);

        $html = $this->actingAs($sales)->get(route('sales.technical-requests.create'))
            ->assertOk()->getContent();

        $this->assertStringContainsString('Select a lead...', $html);
        $this->assertStringContainsString('value="'.$mine->id.'"', $html);
        $this->assertStringNotContainsString('value="'.$foreign->id.'"', $html);
    }

    public function test_create_from_price_tab_locks_estimate_type(): void
    {
        $sales = $this->userWithRole('sales');

        $html = $this->actingAs($sales)
            ->get(route('sales.technical-requests.create', ['request_type' => 'estimate', 'locked' => 1]))
            ->assertOk()->getContent();

        $this->assertStringContainsString('name="request_type" value="estimate"', $html);
        $this->assertStringNotContainsString('<select name="request_type"', $html);
    }

    public function test_create_from_engineer_tab_hides_estimate_option(): void
    {
        $sales = $this->userWithRole('sales');

        $html = $this->actingAs($sales)
            ->get(route('sales.technical-requests.create', ['hide_estimate' => 1]))
            ->assertOk()->getContent();

        $this->assertStringContainsString('<select name="request_type"', $html);
        $this->assertStringNotContainsString('value="estimate"', $html);
    }

    public function test_create_from_lead_detail_keeps_all_types(): void
    {
        $sales = $this->userWithRole('sales');
        $lead = $this->makeLead($sales);

        $html = $this->actingAs($sales)
            ->get(route('sales.technical-requests.create', ['lead_id' => $lead->id]))
            ->assertOk()->getContent();

        $this->assertStringContainsString('value="estimate"', $html);
        $this->assertStringContainsString('value="survey"', $html);
    }
}
