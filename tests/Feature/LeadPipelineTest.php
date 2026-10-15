<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeadPipelineTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(string $role): User
    {
        $this->seed(RoleAndPermissionSeeder::class);
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function makeLead(array $attributes = []): Lead
    {
        $customer = Customer::create(['name' => 'PT Pipeline']);

        $lead = Lead::create(array_merge([
            'customer_id' => $customer->id,
            'pt_group' => 'NTI',
            'segment' => 'vendor',
            'status' => 'cool',
        ], $attributes));
        // acknowledged_at bukan fillable: set eksplisit. Default = sudah
        // ditangani agar test lama tetap di kolom status (bukan New).
        $lead->forceFill([
            'acknowledged_at' => array_key_exists('acknowledged_at', $attributes)
                ? $attributes['acknowledged_at']
                : now(),
        ])->save();

        return $lead->fresh();
    }

    public function test_pipeline_page_shows_own_leads_grouped_by_status(): void
    {
        $user = $this->userWithRole('sales');
        $this->makeLead(['status' => 'hot', 'assigned_to' => $user->id]);
        $this->makeLead(['status' => 'won', 'assigned_to' => $user->id]);

        $response = $this->actingAs($user)->get(route('leads.pipeline'))
            ->assertOk()
            ->assertSee('PT Pipeline');

        $leads = $response->viewData('leads');
        $this->assertSame(1, $leads->where('status', 'hot')->count());
        $this->assertSame(1, $leads->where('status', 'won')->count());
    }

    public function test_pipeline_never_mixes_other_sales_customers(): void
    {
        $mine = $this->userWithRole('sales');
        $other = User::factory()->create();
        $other->assignRole('sales');
        $this->makeLead(['assigned_to' => $mine->id]);

        $otherCustomer = Customer::create(['name' => 'PT Orang Lain']);
        Lead::create([
            'customer_id' => $otherCustomer->id,
            'pt_group' => 'NTI',
            'segment' => 'vendor',
            'status' => 'cool',
            'assigned_to' => $other->id,
        ]);
        $this->makeLead(); // tanpa assignee

        $this->actingAs($mine)->get(route('leads.pipeline'))
            ->assertOk()
            ->assertSee('PT Pipeline')
            ->assertDontSee('PT Orang Lain');
    }

    public function test_non_sales_cannot_open_pipeline(): void
    {
        $this->actingAs($this->userWithRole('marketing'))
            ->get(route('leads.pipeline'))->assertForbidden();
    }

    public function test_newly_assigned_lead_appears_in_new_column(): void
    {
        $user = $this->userWithRole('sales');
        $lead = $this->makeLead(['assigned_to' => $user->id, 'acknowledged_at' => null]);

        $response = $this->actingAs($user)->get(route('leads.pipeline'))->assertOk();

        $this->assertTrue($response->viewData('newLeads')->contains($lead));
        $this->assertFalse($response->viewData('leads')->contains($lead));
    }

    public function test_moving_new_lead_out_acknowledges_it(): void
    {
        $user = $this->userWithRole('sales');
        $lead = $this->makeLead(['assigned_to' => $user->id, 'acknowledged_at' => null]);

        $this->actingAs($user)->patchJson(route('leads.batch-status'), [
            'changes' => [['lead_id' => $lead->id, 'status' => 'warm']],
        ])->assertOk();

        $this->assertNotNull($lead->fresh()->acknowledged_at);

        $response = $this->actingAs($user)->get(route('leads.pipeline'))->assertOk();
        $this->assertFalse($response->viewData('newLeads')->contains($lead));
        $this->assertSame(1, $response->viewData('leads')->where('status', 'warm')->count());
    }

    public function test_moving_new_lead_to_same_status_still_acknowledges_it(): void
    {
        $user = $this->userWithRole('sales');
        // Lead baru default cool + belum ditangani (kolom New virtual).
        $lead = $this->makeLead(['assigned_to' => $user->id, 'acknowledged_at' => null]);
        $this->assertSame('cool', $lead->status);

        // Geser New -> Cool: status DB sama, tapi lead tetap harus diakui.
        $this->actingAs($user)->patchJson(route('leads.batch-status'), [
            'changes' => [['lead_id' => $lead->id, 'status' => 'cool']],
        ])->assertOk();

        $this->assertNotNull($lead->fresh()->acknowledged_at);

        $response = $this->actingAs($user)->get(route('leads.pipeline'))->assertOk();
        $this->assertFalse($response->viewData('newLeads')->contains($lead));
        $this->assertSame(1, $response->viewData('leads')->where('status', 'cool')->count());
    }

    public function test_single_status_update_with_same_status_still_acknowledges_it(): void
    {
        $user = $this->userWithRole('sales');
        $lead = $this->makeLead(['assigned_to' => $user->id, 'acknowledged_at' => null]);

        $this->actingAs($user)
            ->patch(route('leads.update-status', $lead), ['status' => 'cool'])
            ->assertNoContent();

        $this->assertNotNull($lead->fresh()->acknowledged_at);
    }

    public function test_reassign_returns_lead_to_new_column(): void
    {
        $management = $this->userWithRole('management');
        $first = User::factory()->create();
        $first->assignRole('sales');
        $second = User::factory()->create();
        $second->assignRole('sales');
        $lead = $this->makeLead(['assigned_to' => $first->id]);

        $this->actingAs($management)
            ->post(route('manage-sales.assign', $lead), ['assigned_to' => $second->id])
            ->assertRedirect();

        $this->assertNull($lead->fresh()->acknowledged_at);

        $response = $this->actingAs($second)->get(route('leads.pipeline'))->assertOk();
        $this->assertTrue($response->viewData('newLeads')->contains($lead));
    }

    public function test_management_sees_no_mixed_customers(): void
    {
        // Management pegang semua view-* (matriks role) sehingga boleh buka,
        // tapi scope own-lead membuat hasilnya kosong (tidak campur).
        $sales = $this->userWithRole('sales');
        $this->makeLead(['assigned_to' => $sales->id]);

        $response = $this->actingAs($this->userWithRole('management'))
            ->get(route('leads.pipeline'))->assertOk();
        $this->assertSame(0, $response->viewData('leads')->count());
    }

    public function test_last_week_won_hidden_unless_closed_all(): void
    {
        $user = $this->userWithRole('sales');
        $this->makeLead(['status' => 'won', 'assigned_to' => $user->id, 'closed_at' => now()->subWeek()]);
        $thisWeek = $this->makeLead(['status' => 'won', 'assigned_to' => $user->id, 'closed_at' => now()]);

        $response = $this->actingAs($user)->get(route('leads.pipeline'))->assertOk();
        $leads = $response->viewData('leads');
        $this->assertSame(1, $leads->where('status', 'won')->count());
        $this->assertTrue($leads->contains($thisWeek));

        $response = $this->actingAs($user)->get(route('leads.pipeline', ['closed' => 'all']))->assertOk();
        $this->assertSame(2, $response->viewData('leads')->where('status', 'won')->count());
    }

    public function test_legacy_won_without_closed_at_uses_updated_at_fallback(): void
    {
        $user = $this->userWithRole('sales');
        // Data lama: won tanpa closed_at tapi diupdate minggu ini → tampil.
        $legacy = $this->makeLead(['status' => 'won', 'assigned_to' => $user->id]);
        $legacy->forceFill(['closed_at' => null, 'updated_at' => now()])->save();
        // Data lama yang truly basi → sembunyi.
        $stale = $this->makeLead(['status' => 'won', 'assigned_to' => $user->id]);
        $stale->forceFill(['closed_at' => null, 'updated_at' => now()->subMonth()])->save();

        $response = $this->actingAs($user)->get(route('leads.pipeline'))->assertOk();
        $leads = $response->viewData('leads');
        $this->assertTrue($leads->contains($legacy->fresh()));
        $this->assertFalse($leads->contains($stale->fresh()));
    }

    public function test_projects_relation_links_by_customer_without_loading_customer(): void
    {
        $user = $this->userWithRole('sales');
        $lead = $this->makeLead(['assigned_to' => $user->id]);

        // Membangun relasi tidak boleh menyentuh relasi customer (anti NPE).
        $relation = $lead->projects();
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class, $relation);
        $this->assertFalse($lead->relationLoaded('customer'));
        $this->assertTrue($relation->get()->isEmpty());

        // Observer Project butuh user login (Auth::id untuk activity).
        $this->actingAs($user);
        $project = \App\Models\Project::create([
            'customer_id' => $lead->customer_id,
            'work_type_id' => \App\Models\WorkType::create(['name' => 'Instalasi'])->id,
            'pic_engineer_id' => $user->id,
            'project_name' => 'Project Pipeline',
        ]);

        $this->assertTrue($lead->projects()->get()->contains($project));
        $this->assertTrue($lead->fresh()->projects->contains($project));
    }

    public function test_drag_to_won_stamps_closed_at(): void
    {
        $user = $this->userWithRole('sales');
        $lead = $this->makeLead(['assigned_to' => $user->id]);
        $this->assertNull($lead->closed_at);

        $this->actingAs($user)->patchJson(route('leads.batch-status'), [
            'changes' => [['lead_id' => $lead->id, 'status' => 'won']],
        ])->assertOk();

        $fresh = $lead->fresh();
        $this->assertSame('won', $fresh->status);
        $this->assertNotNull($fresh->closed_at);

        // Geser balik keluar won -> closed_at dibersihkan.
        $this->actingAs($user)->patchJson(route('leads.batch-status'), [
            'changes' => [['lead_id' => $lead->id, 'status' => 'hot']],
        ])->assertOk();
        $this->assertNull($lead->fresh()->closed_at);
    }

    public function test_marketing_can_update_lead_status_via_pipeline(): void
    {
        $user = $this->userWithRole('marketing');
        $lead = $this->makeLead();

        $this->actingAs($user)
            ->patch(route('leads.update-status', $lead), ['status' => 'warm'])
            ->assertNoContent();

        $this->assertSame('warm', $lead->fresh()->status);
        $this->assertSame(1, $lead->activities()->where('action', 'status_changed')->count());
    }

    public function test_status_update_is_rejected_with_invalid_status(): void
    {
        $user = $this->userWithRole('marketing');
        $lead = $this->makeLead();

        $this->actingAs($user)
            ->patch(route('leads.update-status', $lead), ['status' => 'ngawur'])
            ->assertSessionHasErrors('status');

        $this->assertSame('cool', $lead->fresh()->status);
    }

    public function test_sales_cannot_update_unassigned_lead_status(): void
    {
        $user = $this->userWithRole('sales');
        $lead = $this->makeLead();

        $this->actingAs($user)
            ->patch(route('leads.update-status', $lead), ['status' => 'won'])
            ->assertForbidden();

        $this->assertSame('cool', $lead->fresh()->status);
    }

    public function test_sales_can_update_own_assigned_lead_status(): void
    {
        $user = $this->userWithRole('sales');
        $lead = $this->makeLead(['assigned_to' => $user->id]);

        $this->actingAs($user)
            ->patch(route('leads.update-status', $lead), ['status' => 'warm'])
            ->assertNoContent();

        $this->assertSame('warm', $lead->fresh()->status);
    }

    public function test_dashboard_shows_marketing_stats(): void
    {
        $user = $this->userWithRole('marketing');
        $this->makeLead(['status' => 'cool', 'source' => 'whatsapp', 'incoming_date' => now()]);
        $this->makeLead(['status' => 'won', 'source' => 'referral', 'incoming_date' => now()->subMonths(2)]);
        $this->makeLead(['status' => 'lost', 'incoming_date' => now()]);

        $this->actingAs($user)->get(route('marketing.dashboard'))
            ->assertOk()
            ->assertViewHas('stats', fn ($stats) => $stats['total'] === 3
                && $stats['won'] === 1 && $stats['conversion'] === 50)
            ->assertViewHas('funnel', fn ($funnel) => $funnel->sum('value') === 3
                && $funnel->pluck('key')->all() === ['cool', 'warm', 'hot', 'won', 'lost']);

        $this->assertSame(3, Lead::count());
    }
}
