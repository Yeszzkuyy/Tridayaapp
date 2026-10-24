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

        $this->assertStringContainsString(__('Inside Sales'), $html);
        $this->assertStringContainsString(__('Price'), $html);
        $this->assertStringContainsString('Engineer', $html);
    }

    public function test_engineer_request_card_shows_requester_and_new_request_path(): void
    {
        $sales = $this->userWithRole('sales');
        $request = $this->makeTechRequest($sales, 'survey', ['title' => 'Site survey request']);

        $html = $this->actingAs($sales)
            ->get(route('sales.requests.index', ['tab' => 'engineer']))
            ->assertOk()->getContent();

        $this->assertStringContainsString($request->title, $html);
        $this->assertStringContainsString(__('Requested by'), $html);
        $this->assertStringContainsString($sales->name, $html);
        $this->assertStringContainsString(route('sales.technical-requests.create'), $html);
    }

    public function test_sales_new_request_action_follows_the_active_tab(): void
    {
        $sales = $this->userWithRole('sales');
        $createRoutes = [
            'inside-sales' => route('lead-tasks.create'),
            'price' => route('sales.price-requests.create'),
            'engineer' => route('sales.technical-requests.create'),
        ];

        foreach ($createRoutes as $tab => $createRoute) {
            $html = $this->actingAs($sales)
                ->get(route('sales.requests.index', ['tab' => $tab]))
                ->assertOk()->getContent();

            $this->assertStringContainsString($createRoute, $html);
        }
    }

    public function test_read_only_management_does_not_see_request_create_action(): void
    {
        $management = $this->userWithRole('management');

        $html = $this->actingAs($management)
            ->get(route('sales.requests.index', ['tab' => 'price']))
            ->assertOk()->getContent();

        $this->assertStringNotContainsString(route('sales.price-requests.create'), $html);
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

    public function test_task_deep_link_opens_discussion_inside_request_hub(): void
    {
        $sales = $this->userWithRole('sales');
        $inside = User::factory()->create();
        $inside->assignRole('inside-sales');
        $lead = $this->makeLead($sales);
        $task = LeadTask::create([
            'lead_id' => $lead->id,
            'title' => 'Diskusi penawaran PT Forvis',
            'status' => 'todo',
            'assigned_to' => $inside->id,
            'created_by' => $sales->id,
        ]);
        $task->comments()->create(['user_id' => $inside->id, 'body' => 'Pesan dari forum diskusi.']);

        foreach ([$sales, $inside] as $viewer) {
            $this->actingAs($viewer)
                ->get(route('sales.requests.index', ['tab' => 'inside-sales', 'task' => $task->id]))
                ->assertOk()
                ->assertSee('Diskusi penawaran PT Forvis')
                ->assertSee('Pesan dari forum diskusi.');
        }
    }

    public function test_task_deep_link_rejects_stranger(): void
    {
        $sales = $this->userWithRole('sales');
        $inside = User::factory()->create();
        $inside->assignRole('inside-sales');
        $stranger = User::factory()->create();
        $stranger->assignRole('sales');
        $lead = $this->makeLead($sales);
        $task = LeadTask::create([
            'lead_id' => $lead->id,
            'title' => 'Task privat',
            'status' => 'todo',
            'assigned_to' => $inside->id,
            'created_by' => $sales->id,
        ]);

        $this->actingAs($stranger)
            ->get(route('sales.requests.index', ['task' => $task->id]))
            ->assertForbidden();
    }

    public function test_multi_role_sales_and_inside_sales_sees_assigned_task(): void
    {
        $owner = $this->userWithRole('sales');
        $multi = User::factory()->create();
        $multi->assignRole(['sales', 'inside-sales']);
        $lead = $this->makeLead($owner); // lead milik sales lain
        $task = LeadTask::create([
            'lead_id' => $lead->id,
            'title' => 'Task untuk multi-role',
            'status' => 'todo',
            'assigned_to' => $multi->id,
            'created_by' => $owner->id,
        ]);

        $this->actingAs($multi)
            ->get(route('sales.requests.index', ['tab' => 'inside-sales']))
            ->assertOk()
            ->assertSee($task->title);

        $this->actingAs($multi)
            ->get(route('lead-tasks.index'))
            ->assertOk()
            ->assertSee($task->title);
    }

    public function test_price_tab_lists_only_price_requests(): void
    {
        $sales = $this->userWithRole('sales');
        $customer = \App\Models\Customer::create(['name' => 'PT Price Uji'.uniqid()]);
        \App\Models\PriceRequest::create([
            'customer_id' => $customer->id,
            'requested_by' => $sales->id,
            'status' => 'waiting_lead',
        ]);
        $survey = $this->makeTechRequest($sales, 'survey');

        $html = $this->actingAs($sales)->get(route('sales.requests.index', ['tab' => 'price']))
            ->assertOk()->getContent();

        $this->assertStringContainsString($customer->name, $html);
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

    public function test_engineer_form_never_offers_estimate_and_offers_new_types(): void
    {
        $sales = $this->userWithRole('sales');
        $lead = $this->makeLead($sales);

        $html = $this->actingAs($sales)
            ->get(route('sales.technical-requests.create', ['lead_id' => $lead->id]))
            ->assertOk()->getContent();

        // Estimasi sudah pindah ke sistem Request Harga.
        $this->assertStringNotContainsString('value="estimate"', $html);
        $this->assertStringContainsString('value="survey"', $html);
        $this->assertStringContainsString('value="installation"', $html);
        $this->assertStringContainsString('value="demo"', $html);
        $this->assertStringContainsString('value="poc"', $html);
        // Priority tidak lagi ditanyakan.
        $this->assertStringNotContainsString('name="priority"', $html);
    }

    public function test_price_tab_new_button_opens_price_form(): void
    {
        $sales = $this->userWithRole('sales');

        $html = $this->actingAs($sales)
            ->get(route('sales.requests.index', ['tab' => 'price']))
            ->assertOk()->getContent();

        $this->assertStringContainsString(route('sales.price-requests.create'), $html);

        $form = $this->actingAs($sales)
            ->get(route('sales.price-requests.create'))
            ->assertOk()->getContent();

        $this->assertStringContainsString('name="quotation"', $form);
        $this->assertStringContainsString('name="customer_id"', $form);
        $this->assertStringContainsString('name="notes"', $form);
    }
}
