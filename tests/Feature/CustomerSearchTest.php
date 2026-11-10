<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerContact;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class CustomerSearchTest extends TestCase
{
    use RefreshDatabase;

    private function user()
    {
        Artisan::call('db:seed', ['--class' => 'RoleAndPermissionSeeder']);
        $u = User::factory()->create();
        $u->givePermissionTo('view-sales');
        return $u;
    }

    public function test_search_filters_customers()
    {
        $u = $this->user();
        Customer::create(['name' => 'Alpha Corp', 'status' => 'deal']);
        Customer::create(['name' => 'Beta Ltd', 'status' => 'deal']);

        // dengan search: hanya yang cocok
        $this->actingAs($u)->call('GET', '/customers', ['search' => 'Alpha'])
            ->assertOk()
            ->assertSee('Alpha Corp')
            ->assertDontSee('Beta Ltd');

        // tanpa search: semua tampil + tombol Cari ada
        $this->actingAs($u)->get('/customers')
            ->assertOk()
            ->assertSee('Alpha Corp')
            ->assertSee('Beta Ltd')
            ->assertSee(__('Cari'));
    }

    public function test_live_search_ajax_returns_table_partial_only()
    {
        $u = $this->user();
        Customer::create(['name' => 'Alpha Corp', 'status' => 'deal']);
        Customer::create(['name' => 'Beta Ltd', 'status' => 'deal']);

        $res = $this->actingAs($u)
            ->call('GET', '/customers', ['search' => 'Alpha'], [], [], ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest'])
            ->assertOk();

        $body = $res->getContent();
        $this->assertStringContainsString('Alpha Corp', $body);
        $this->assertStringNotContainsString('Beta Ltd', $body);
        $this->assertStringNotContainsString('<html', strtolower($body)); // partial, bukan halaman penuh

        // tanpa kata kunci: semua kembali
        $body = $this->actingAs($u)
            ->call('GET', '/customers', [], [], [], ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest'])
            ->getContent();
        $this->assertStringContainsString('Beta Ltd', $body);
    }

    public function test_search_is_case_insensitive_partial_and_trimmed()
    {
        $u = $this->user();
        Customer::create(['name' => 'PT Jepun', 'email' => 'jepun@example.com', 'status' => 'deal']);
        Customer::create(['name' => 'Beta Ltd', 'status' => 'deal']);

        // Case-insensitive: semua varian casing memberi hasil yang sama
        foreach (['PT JEPUN', 'pt jepun', 'Pt Jepun'] as $query) {
            $this->actingAs($u)->call('GET', '/customers', ['search' => $query])
                ->assertOk()
                ->assertSee('PT Jepun')
                ->assertDontSee('Beta Ltd');
        }

        // Partial match: potongan kata tengah tetap cocok
        $this->actingAs($u)->call('GET', '/customers', ['search' => 'pun'])
            ->assertOk()
            ->assertSee('PT Jepun')
            ->assertDontSee('Beta Ltd');

        // Auto-trim: spasi berlebih di awal/akhir diabaikan
        $this->actingAs($u)->call('GET', '/customers', ['search' => '  PT Jepun  '])
            ->assertOk()
            ->assertSee('PT Jepun')
            ->assertDontSee('Beta Ltd');
    }

    public function test_search_matches_pic_name_and_whatsapp()
    {
        $u = $this->user();
        $alpha = Customer::create(['name' => 'PT Alpha', 'status' => 'deal']);
        CustomerContact::create([
            'customer_id' => $alpha->id,
            'name' => 'Budi Santoso',
            'whatsapp' => '081234567890',
            'is_primary' => true,
        ]);
        Customer::create(['name' => 'Beta Ltd', 'status' => 'deal']);

        $this->actingAs($u)->call('GET', '/customers', ['search' => 'Budi'])
            ->assertOk()
            ->assertSee('PT Alpha')
            ->assertDontSee('Beta Ltd');

        $this->actingAs($u)->call('GET', '/customers', ['search' => '081234567890'])
            ->assertOk()
            ->assertSee('PT Alpha')
            ->assertDontSee('Beta Ltd');
    }

    public function test_pic_filter_can_show_only_customers_with_or_without_pic()
    {
        $u = $this->user();
        $withPic = Customer::create(['name' => 'PT Ada PIC', 'status' => 'deal']);
        CustomerContact::create([
            'customer_id' => $withPic->id,
            'name' => 'Budi',
            'is_primary' => true,
        ]);
        Customer::create(['name' => 'PT Tanpa PIC', 'status' => 'deal']);

        $this->actingAs($u)->call('GET', '/customers', ['pic' => 'yes'])
            ->assertOk()
            ->assertSee('PT Ada PIC')
            ->assertDontSee('PT Tanpa PIC');

        $this->actingAs($u)->call('GET', '/customers', ['pic' => 'no'])
            ->assertOk()
            ->assertSee('PT Tanpa PIC')
            ->assertDontSee('PT Ada PIC');
    }

    public function test_lead_status_customer_is_hidden_until_promoted()
    {
        $u = $this->user();
        Customer::create(['name' => 'PT Prospek', 'status' => 'lead', 'pt_group' => 'NTI']);
        Customer::create(['name' => 'PT Customer', 'status' => 'deal', 'pt_group' => 'NTI']);

        $this->actingAs($u)->get('/customers')
            ->assertOk()
            ->assertSee('PT Customer')
            ->assertDontSee('PT Prospek');

        $this->actingAs($u)->call('GET', '/customers', ['status' => 'lead'])
            ->assertOk()
            ->assertSee('PT Prospek')
            ->assertDontSee('PT Customer');
    }
}
