<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tampilan baca (CEO / pengawas) tidak boleh menampilkan tombol aksi tulis
 * yang pasti ditolak backend. Backend tetap menjadi penentu; test ini menjaga
 * agar pengalaman cek website tidak dipenuhi tombol yang berujung 403.
 */
class ReadOnlyUiTest extends TestCase
{
    use RefreshDatabase;

    private function leadFor(User $sales): Lead
    {
        $customer = Customer::create([
            'name' => 'PT Read Only',
            'company' => 'PT Read Only',
            'pt_group' => 'NTI',
            'contact_person' => 'Budi',
            'address' => 'Jl. Merdeka 1',
            'email' => 'budi@example.test',
            'phone' => '0215550001',
            'whatsapp' => '628123450001',
        ]);

        return Lead::create([
            'customer_id' => $customer->id,
            'pt_group' => 'NTI',
            'segment' => 'vendor',
            'status' => 'warm',
            'assigned_to' => $sales->id,
            'project_name' => 'Proyek Read Only',
            'kebutuhan' => 'Butuh penawaran',
            'incoming_date' => today()->toDateString(),
        ]);
    }

    public function test_ceo_does_not_see_write_actions_on_my_leads(): void
    {
        $sales = $this->makeUserWithRole([], 'sales');
        $this->leadFor($sales);

        $ceo = $this->makeUserWithRole([], 'ceo');

        $html = $this->actingAs($ceo)->get(route('sales.my-leads'))->assertOk()->getContent();

        $this->assertStringNotContainsString(route('leads.create'), $html);
        $this->assertStringNotContainsString(route('sales.follow-ups.create'), $html);
        $this->assertStringNotContainsString(__('Edit note'), $html);
        $this->assertStringNotContainsString('pipeline_stage', $html);
        $this->assertStringNotContainsString('x-model="nextDate"', $html);
    }

    public function test_sales_still_sees_write_actions_on_my_leads(): void
    {
        $sales = $this->makeUserWithRole([], 'sales');
        $this->leadFor($sales);

        $html = $this->actingAs($sales)->get(route('sales.my-leads'))->assertOk()->getContent();

        $this->assertStringContainsString(route('leads.create'), $html);
        $this->assertStringContainsString(route('sales.follow-ups.create'), $html);
        $this->assertStringContainsString(__('Edit note'), $html);
        $this->assertStringContainsString('pipeline_stage', $html);
        $this->assertStringNotContainsString('x-model="stage"', $html);
        $this->assertStringContainsString('x-model="nextDate"', $html);
    }

    public function test_ceo_does_not_see_write_actions_on_admin_index_pages(): void
    {
        $ceo = $this->makeUserWithRole([], 'ceo');

        $this->actingAs($ceo)
            ->get(route('admin.payments.index'))
            ->assertOk()
            ->assertDontSee(route('admin.payments.create'), false);

        $this->actingAs($ceo)
            ->get(route('admin.invoices.index'))
            ->assertOk()
            ->assertDontSee(route('admin.invoices.create'), false);
    }

    public function test_admin_still_sees_payment_and_invoice_actions(): void
    {
        $admin = $this->makeUserWithRole([], 'admin');

        $this->actingAs($admin)
            ->get(route('admin.payments.index'))
            ->assertOk()
            ->assertSee(route('admin.payments.create'), false);

        $this->actingAs($admin)
            ->get(route('admin.invoices.index'))
            ->assertOk()
            ->assertSee(route('admin.invoices.create'), false);
    }

    public function test_ceo_does_not_see_technician_write_actions(): void
    {
        config(['services.bast-templates' => ['NTI' => 'https://example.test/bast-template']]);

        $ceo = $this->makeUserWithRole([], 'ceo');

        $this->actingAs($ceo)
            ->get(route('teknisi.requests.index'))
            ->assertOk()
            ->assertDontSee(route('sales.price-requests.create'), false);

        $this->actingAs($ceo)
            ->get(route('teknisi.sizing-projects.index'))
            ->assertOk()
            ->assertDontSee(route('teknisi.sizing-projects.create'), false);

        $this->actingAs($ceo)
            ->get(route('teknisi.field-work.index'))
            ->assertOk()
            ->assertDontSee(__('Create BAST Document'));
    }

    public function test_technician_still_sees_technician_write_actions(): void
    {
        config(['services.bast-templates' => ['NTI' => 'https://example.test/bast-template']]);

        $technician = $this->makeUserWithRole([], 'technician');

        $this->actingAs($technician)
            ->get(route('teknisi.requests.index'))
            ->assertOk()
            ->assertSee(route('sales.price-requests.create'), false);

        $this->actingAs($technician)
            ->get(route('teknisi.sizing-projects.index'))
            ->assertOk()
            ->assertSee(route('teknisi.sizing-projects.create'), false);

        $this->actingAs($technician)
            ->get(route('teknisi.field-work.index'))
            ->assertOk()
            ->assertSee(__('Create BAST Document'));
    }

    public function test_ceo_does_not_see_meeting_create_actions(): void
    {
        $ceo = $this->makeUserWithRole([], 'ceo');

        $this->actingAs($ceo)
            ->get(route('sales.meetings.index'))
            ->assertOk()
            ->assertDontSee(route('sales.meetings.create'), false);
    }

    public function test_ceo_does_not_see_whatsapp_write_actions(): void
    {
        $ceo = $this->makeUserWithRole([], 'ceo');

        $this->actingAs($ceo)
            ->get(route('whatsapp-center.index'))
            ->assertOk()
            ->assertDontSee('@click="startNewChat()"', false)
            ->assertDontSee('bot-settings', false)
            ->assertSee(__('Mode baca. Anda tidak memiliki izin untuk mengirim pesan.'));
    }

    public function test_ceo_can_view_management_but_not_write(): void
    {
        $sales = $this->makeUserWithRole([], 'sales');
        $lead = $this->leadFor($sales);

        $ceo = $this->makeUserWithRole([], 'ceo');

        $index = $this->actingAs($ceo)->get(route('manage-sales.index'))->assertOk()->getContent();
        $this->assertStringContainsString(__('Manage Sales'), $index);
        $this->assertStringNotContainsString(route('manage-sales.assign', $lead), $index);
        $this->assertStringNotContainsString(__('Assign to Sales'), $index);

        $kpi = $this->actingAs($ceo)->get(route('manage-sales.kpi'))->assertOk()->getContent();
        $this->assertStringNotContainsString(route('manage-sales.targets.update'), $kpi);
        $this->assertStringNotContainsString(__('Atur Target Bulanan Sales'), $kpi);

        $this->actingAs($ceo)->get(route('manage-sales.activity-log'))->assertOk();
        $this->actingAs($ceo)->get(route('manage.marketing.index'))->assertOk();
        $this->actingAs($ceo)->get(route('manage.technical.index'))->assertOk();
        $this->actingAs($ceo)->get(route('manage.admin.index'))->assertOk();
        $this->actingAs($ceo)->get(route('admin-panel.index'))->assertForbidden();
    }

    public function test_ceo_can_view_estimator_read_only(): void
    {
        $ceo = $this->makeUserWithRole([], 'ceo');

        $queue = $this->actingAs($ceo)->get(route('estimator.requests.index'))->assertOk()->getContent();
        $this->assertStringNotContainsString(route('estimator.requests.create'), $queue);
        $this->assertStringNotContainsString(__('Process Request'), $queue);

        $products = $this->actingAs($ceo)->get(route('products.index'))->assertOk()->getContent();
        $this->assertStringNotContainsString(route('products.create'), $products);
        $this->assertStringNotContainsString(__('Add Product'), $products);

        $this->actingAs($ceo)->get(route('estimator.price-history'))->assertOk();
        $this->actingAs($ceo)->get(route('estimator.suppliers'))->assertOk();
    }
}
