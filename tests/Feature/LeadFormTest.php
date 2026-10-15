<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeadFormTest extends TestCase
{
    use RefreshDatabase;

    private function marketingUser(): User
    {
        $this->seed(RoleAndPermissionSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('marketing');

        return $user;
    }

    public function test_create_page_shows_segment_and_new_fields(): void
    {
        $this->actingAs($this->marketingUser())
            ->get(route('leads.create'))
            ->assertOk()
            ->assertSee('Segment')
            ->assertSee('System Integrator')
            ->assertSee(__('Canvasing'))
            ->assertSee(__('Kebutuhan'))
            ->assertSee(__('Tanggal Masuk'))
            ->assertDontSee('Nilai Opportunity');
    }

    public function test_create_prefills_company_and_pic_separately_from_whatsapp(): void
    {
        $account = \App\Models\WhatsappAccount::create([
            'name' => 'WA wa_nti',
            'phone_number' => '6281111111101',
            'account_code' => 'wa_nti',
            'gateway_type' => \App\Models\WhatsappAccount::GATEWAY_GREEN,
            'assigned_to' => null,
            'is_active' => true,
        ]);

        $this->actingAs($this->marketingUser())
            ->get(route('leads.create', [
                'whatsapp_account_id' => $account->id,
                'sender' => '6281234567890',
                'company' => 'PT Maju Jaya',
                'pic' => 'Rina Putri',
            ]))
            ->assertOk()
            ->assertSee('value="PT Maju Jaya"', false)
            ->assertSee('value="Rina Putri"', false)
            ->assertSee('value="6281234567890"', false);
    }

    public function test_create_prefill_leaves_company_empty_for_unknown_sender(): void
    {
        $account = \App\Models\WhatsappAccount::create([
            'name' => 'WA wa_nti',
            'phone_number' => '6281111111101',
            'account_code' => 'wa_nti',
            'gateway_type' => \App\Models\WhatsappAccount::GATEWAY_GREEN,
            'assigned_to' => null,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->marketingUser())
            ->get(route('leads.create', [
                'whatsapp_account_id' => $account->id,
                'sender' => '6281234567890',
                'company' => '',
                'pic' => 'Rina Putri',
                'kebutuhan' => 'Tanya harga',
            ]))
            ->assertOk()
            ->assertSee('value="Rina Putri"', false);

        // Kolom Perusahaan harus kosong — nama orang tidak boleh jadi nama company.
        $this->assertStringContainsString('name="customer_name" id="customer_name" value=""', $response->getContent());
    }

    public function test_store_creates_lead_with_customer_details(): void
    {
        // marketingUser() sudah seed; seed ganda akan me-wipe assignment role.
        $marketing = $this->marketingUser();
        $sales = User::factory()->create();
        $sales->assignRole('sales');
        $this->actingAs($marketing)
            ->post(route('leads.store'), [
                'customer_mode' => 'new',
                'customer_name' => 'PT Uji Coba',
                'customer_email' => 'info@ujicoba.id',
                'customer_phone' => 'wa.me/6281234567890',
                'customer_address' => 'Jl. Testing No. 1, Jakarta',
                'customer_contact_person' => 'Budi PIC',
                'pt_group' => 'NTI',
                'segment' => 'system_integrator',
                'source' => 'canvasing',
                'kebutuhan' => 'Instalasi jaringan 3 lantai',
                'incoming_date' => '2026-08-24',
                'assigned_to' => $sales->id,
            ])
            ->assertRedirect(route('leads.index'));

        $lead = Lead::whereHas('customer', fn ($q) => $q->where('name', 'PT Uji Coba'))->first();

        $this->assertNotNull($lead);
        $this->assertSame('system_integrator', $lead->segment);
        $this->assertSame('canvasing', $lead->source);
        $this->assertSame('cool', $lead->status);
        $this->assertSame('2026-08-24', $lead->incoming_date->toDateString());
        $this->assertSame('NTI', $lead->pt_group);
        $this->assertSame('info@ujicoba.id', $lead->customer->email);
        $this->assertSame('Budi PIC', $lead->customer->contact_person);
        $this->assertSame('Jl. Testing No. 1, Jakarta', $lead->customer->address);
    }

    public function test_update_persists_customer_whatsapp_even_when_customer_soft_deleted(): void
    {
        $user = $this->marketingUser();
        $customer = Customer::create(['name' => 'PT Soft Deleted', 'whatsapp' => '0812-1111-2222']);
        $lead = Lead::create([
            'customer_id' => $customer->id,
            'pt_group' => 'NTI',
            'segment' => 'vendor',
            'incoming_date' => now()->toDateString(),
            'status' => 'cool',
        ]);
        $customer->delete();

        $response = $this->actingAs($user)->put(route('leads.update', $lead), [
            'customer_mode' => 'existing',
            'customer_id' => $customer->id,
            'customer_name' => 'PT Soft Deleted',
            'customer_whatsapp' => '0813-3333-4444',
            'pt_group' => 'NTI',
            'segment' => 'vendor',
            'kebutuhan' => 'x',
            'incoming_date' => now()->toDateString(),
            'assigned_to' => $user->id,
        ]);
        $response->assertRedirect(route('leads.index'));

        $this->assertSame('0813-3333-4444', Customer::withTrashed()->find($customer->id)->whatsapp);
    }

    public function test_activities_are_logged_with_user(): void
    {
        $user = $this->marketingUser();
        $customer = Customer::create(['name' => 'PT Log Uji']);

        $this->actingAs($user)->post(route('leads.store'), [
            'customer_mode' => 'existing',
            'customer_id' => $customer->id,
            'pt_group' => 'NTI',
            'segment' => 'vendor',
            'kebutuhan' => 'Instalasi CCTV',
            'incoming_date' => now()->toDateString(),
            'assigned_to' => $user->id,
        ])->assertRedirect(route('leads.index'));

        $lead = Lead::latest('id')->first();

        $this->actingAs($user)->put(route('leads.update', $lead), [
            'customer_mode' => 'existing',
            'customer_id' => $customer->id,
            'pt_group' => 'TPS',
            'segment' => 'end_user',
            'kebutuhan' => 'Instalasi CCTV',
            'incoming_date' => now()->toDateString(),
            'assigned_to' => $user->id,
        ])->assertRedirect(route('leads.index'));

        $created = LeadActivity::where('lead_id', $lead->id)->where('action', 'created')->first();
        $updated = LeadActivity::where('lead_id', $lead->id)->where('action', 'updated')->first();

        $this->assertNotNull($created);
        $this->assertSame($user->id, $created->user_id);

        $this->assertNotNull($updated);
        $this->assertSame($user->id, $updated->user_id);
        $this->assertSame('vendor', $updated->changes['segment']['old']);
        $this->assertSame('end_user', $updated->changes['segment']['new']);
    }

    public function test_activities_page_renders(): void
    {
        $this->actingAs($this->marketingUser())
            ->get(route('leads.activities'))
            ->assertOk()
            ->assertSee(__('Log Aktivitas Lead'));
    }

    public function test_show_renders_without_raw_blade_directives(): void
    {
        $customer = Customer::create(['name' => 'PT Render Uji']);
        $lead = Lead::create([
            'customer_id' => $customer->id,
            'pt_group' => 'NTI',
            'segment' => 'vendor',
            'incoming_date' => now()->toDateString(),
            'status' => 'cool',
        ]);

        $this->actingAs($this->marketingUser())
            ->get(route('leads.show', $lead))
            ->assertOk()
            ->assertSee('PT Render Uji')
            ->assertDontSee('@if($canConvert', false)
            ->assertDontSee('@can(', false)
            ->assertDontSee('@csrf', false)
            ->assertDontSee('<?php(', false);
    }

    public function test_assign_to_cross_division_user_rejected(): void
    {
        $marketing = $this->marketingUser();
        $technician = User::factory()->create();
        $technician->assignRole('technician');

        $this->actingAs($marketing)->post(route('leads.store'), [
            'customer_mode' => 'new',
            'customer_name' => 'PT Salah Assign',
            'pt_group' => 'NTI',
            'segment' => 'vendor',
            'kebutuhan' => 'Butuh review assign.',
            'incoming_date' => now()->toDateString(),
            'assigned_to' => $technician->id,
        ])->assertStatus(422);

        $this->assertNull(Lead::whereHas('customer', fn ($q) => $q->where('name', 'PT Salah Assign'))->first());
    }

    public function test_move_lead_to_other_customer_requires_customer_permission(): void
    {
        $user = $this->marketingUser();
        $mine = Customer::create(['name' => 'PT Milikku', 'contact_person' => 'Saya']);
        $victim = Customer::create(['name' => 'PT Korban', 'contact_person' => 'Korban']);
        $lead = Lead::create([
            'customer_id' => $mine->id,
            'pt_group' => 'NTI',
            'segment' => 'vendor',
            'incoming_date' => now()->toDateString(),
            'status' => 'cool',
        ]);

        $this->actingAs($user)->put(route('leads.update', $lead), [
            'customer_mode' => 'existing',
            'customer_id' => $victim->id,
            'customer_name' => 'PT Korban Diambil',
            'pt_group' => 'NTI',
            'segment' => 'vendor',
            'kebutuhan' => 'Butuh review akses.',
            'incoming_date' => now()->toDateString(),
        ])->assertForbidden();

        $this->assertSame($mine->id, $lead->fresh()->customer_id);
        $this->assertSame('Korban', Customer::find($victim->id)->contact_person);
    }

    public function test_import_batches_rows_and_reports_per_row_errors(): void
    {
        $marketing = $this->marketingUser();
        $sales = \App\Models\User::factory()->create();
        $sales->assignRole('sales');

        $path = tempnam(sys_get_temp_dir(), 'imp').'.csv';
        $handle = fopen($path, 'w');
        fputcsv($handle, ['nama_perusahaan', 'pt', 'segment', 'sales']);
        fputcsv($handle, ['PT Impor Satu', 'NTI', 'vendor', $sales->name]);
        fputcsv($handle, ['PT Impor Dua', 'XXX', 'vendor', '']);
        fputcsv($handle, ['', 'NTI', 'vendor', '']);
        fputcsv($handle, ['PT Impor Satu', 'NTI', 'vendor', 'Tidak Ada']);
        fclose($handle);

        $file = new \Illuminate\Http\UploadedFile($path, 'leads.csv', 'text/csv', null, true);

        $this->actingAs($marketing)->post(route('leads.import.execute'), ['file' => $file])
            ->assertRedirect();

        // 2 sukses (duplikat company dipakai ulang), 2 gagal (pt invalid + nama kosong).
        $this->assertSame(1, \App\Models\Customer::where('name', 'PT Impor Satu')->count());
        $this->assertSame(2, \App\Models\Lead::whereHas('customer', fn ($q) => $q->where('name', 'PT Impor Satu'))->count());
        $this->assertNull(\App\Models\Customer::where('name', 'PT Impor Dua')->first());

        $assigned = \App\Models\Lead::whereHas('customer', fn ($q) => $q->where('name', 'PT Impor Satu'))->orderBy('id')->first();
        $this->assertSame($sales->id, (int) $assigned->assigned_to);

        $unassigned = \App\Models\Lead::whereHas('customer', fn ($q) => $q->where('name', 'PT Impor Satu'))->orderByDesc('id')->first();
        $this->assertNull($unassigned->assigned_to);
    }

    public function test_segment_is_required_and_validated(): void
    {
        $customer = Customer::create(['name' => 'PT Lama']);

        $this->actingAs($this->marketingUser())
            ->post(route('leads.store'), [
                'customer_mode' => 'existing',
                'customer_id' => $customer->id,
                'pt_group' => 'MGK',
                'segment' => 'bukan_segment',
            ])
            ->assertSessionHasErrors(['segment']);
    }

    public function test_segment_and_kebutuhan_have_custom_required_messages(): void
    {
        $customer = Customer::create(['name' => 'PT Wajib Isi']);

        $this->actingAs($this->marketingUser())
            ->post(route('leads.store'), [
                'customer_mode' => 'existing',
                'customer_id' => $customer->id,
                'pt_group' => 'MGK',
                'incoming_date' => now()->toDateString(),
            ])
            ->assertSessionHasErrors([
                'segment' => 'Kategori (Segment) wajib dipilih.',
                'kebutuhan' => 'Kebutuhan klien wajib diisi.',
            ]);
    }
}
