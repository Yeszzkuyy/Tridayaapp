<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerContact;
use App\Models\Lead;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Sinkronisasi data Lead (marketing) -> Customer -> halaman Sales.
 * Fokus: PIC, data perusahaan, dan perilaku non-destruktif.
 */
class CustomerLeadSyncTest extends TestCase
{
    use RefreshDatabase;

    private function marketingUser(): User
    {
        $this->seed(RoleAndPermissionSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('marketing');

        return $user;
    }

    private function salesUser(): User
    {
        $this->seed(RoleAndPermissionSeeder::class);
        $user = User::factory()->create();
        $user->givePermissionTo('manage-sales');

        return $user;
    }

    public function test_new_lead_creates_customer_primary_pic_and_mirror(): void
    {
        $this->actingAs($this->marketingUser())
            ->post(route('leads.store'), [
                'customer_mode' => 'new',
                'customer_name' => 'PT Sinkron Jaya',
                'customer_address' => 'Jl. Sinkron 1',
                'customer_contact_person' => 'Budi PIC',
                'customer_email' => 'info@sinkron.test',
                'customer_phone' => '021-555',
                'customer_whatsapp' => '081200000001',
                'pt_group' => 'NTI',
                'segment' => 'vendor',
                'kebutuhan' => 'Instalasi jaringan',
                'incoming_date' => now()->toDateString(),
            ])
            ->assertRedirect(route('leads.index'));

        $customer = Customer::where('name', 'PT Sinkron Jaya')->firstOrFail();
        $contact = $customer->contacts()->first();

        $this->assertNotNull($contact);
        $this->assertSame('Budi PIC', $contact->name);
        $this->assertSame('081200000001', $contact->whatsapp);
        $this->assertTrue((bool) $contact->is_primary);
        $this->assertSame('Budi PIC', $customer->contact_person);
        $this->assertSame('Budi PIC', $customer->picName());
        $this->assertSame('081200000001', $customer->picWhatsapp());
        $this->assertSame('Jl. Sinkron 1', $customer->address);
        $this->assertSame('info@sinkron.test', $customer->email);
    }

    public function test_existing_customer_pic_name_updated_without_clobbering_pic_numbers(): void
    {
        $customer = Customer::create(['name' => 'PT Lama', 'pt_group' => 'NTI']);
        $contact = $customer->contacts()->create([
            'name' => 'Pak Basri',
            'whatsapp' => '0811111111',
            'is_primary' => true,
        ]);
        $customer->forceFill(['contact_person' => 'Pak Basri'])->saveQuietly();

        $lead = Lead::create([
            'customer_id' => $customer->id,
            'pt_group' => 'NTI',
            'segment' => 'vendor',
            'incoming_date' => now()->toDateString(),
            'status' => 'cool',
        ]);

        $this->actingAs($this->marketingUser())
            ->put(route('leads.update', $lead), [
                'customer_mode' => 'existing',
                'customer_id' => $customer->id,
                'customer_name' => 'PT Lama',
                'customer_contact_person' => 'Budi Baru',
                'customer_whatsapp' => '0822222222',
                'pt_group' => 'NTI',
                'segment' => 'vendor',
                'kebutuhan' => 'Follow up',
                'incoming_date' => now()->toDateString(),
            ])
            ->assertRedirect(route('leads.index'));

        $contact->refresh();
        $this->assertSame('Budi Baru', $contact->name);
        // Nomor WA PIC lama tidak tertimpa nomor customer.
        $this->assertSame('0811111111', $contact->whatsapp);
        $this->assertSame('Budi Baru', Customer::find($customer->id)->contact_person);
    }

    public function test_lead_update_does_not_erase_customer_data_with_blank_fields(): void
    {
        $customer = Customer::create([
            'name' => 'PT Terisi',
            'company' => 'PT Terisi',
            'pt_group' => 'NTI',
            'address' => 'Jl. Asli 10',
            'email' => 'asli@terisi.test',
            'phone' => '021-777',
        ]);

        $lead = Lead::create([
            'customer_id' => $customer->id,
            'pt_group' => 'NTI',
            'segment' => 'vendor',
            'incoming_date' => now()->toDateString(),
            'status' => 'cool',
        ]);

        $this->actingAs($this->marketingUser())
            ->put(route('leads.update', $lead), [
                'customer_mode' => 'existing',
                'customer_id' => $customer->id,
                'customer_name' => '',
                'customer_address' => '',
                'customer_email' => '',
                'customer_phone' => '',
                'customer_whatsapp' => '',
                'customer_contact_person' => '',
                'pt_group' => 'NTI',
                'segment' => 'vendor',
                'kebutuhan' => 'Follow up',
                'incoming_date' => now()->toDateString(),
            ])
            ->assertRedirect(route('leads.index'));

        $customer->refresh();
        $this->assertSame('Jl. Asli 10', $customer->address);
        $this->assertSame('asli@terisi.test', $customer->email);
        $this->assertSame('021-777', $customer->phone);
        $this->assertSame(0, $customer->contacts()->count());
    }

    public function test_customer_form_creates_primary_pic(): void
    {
        $this->actingAs($this->salesUser())
            ->post(route('customers.store'), [
                'name' => 'PT Form Customer',
                'pt_group' => 'NTI',
                'pic_name' => 'Rina',
                'pic_position' => 'Purchasing',
                'pic_phone' => '021-1234',
                'pic_whatsapp' => '081200000009',
                'pic_email' => 'rina@form.test',
            ])
            ->assertRedirect(route('customers.index'));

        $customer = Customer::where('name', 'PT Form Customer')->firstOrFail();
        $contact = $customer->primaryPic();

        $this->assertNotNull($contact);
        $this->assertSame('Rina', $contact->name);
        $this->assertSame('Purchasing', $contact->position);
        $this->assertSame('081200000009', $contact->whatsapp);
        $this->assertSame('Rina', $customer->contact_person);
    }

    public function test_setting_primary_contact_updates_customer_mirror(): void
    {
        $customer = Customer::create(['name' => 'PT Mirror', 'pt_group' => 'NTI']);
        $first = $customer->contacts()->create(['name' => 'Satu', 'is_primary' => true]);
        $second = $customer->contacts()->create(['name' => 'Dua', 'is_primary' => false]);

        $this->actingAs($this->salesUser())
            ->patch(route('customer-contacts.primary', $second))
            ->assertRedirect(route('customers.show', $customer));

        $this->assertSame('Dua', $customer->fresh()->contact_person);
        $this->assertFalse((bool) $first->fresh()->is_primary);
    }

    public function test_deleting_primary_contact_promotes_another_pic(): void
    {
        $customer = Customer::create(['name' => 'PT Promosi', 'pt_group' => 'NTI']);
        $primary = $customer->contacts()->create(['name' => 'Utama', 'is_primary' => true]);
        $other = $customer->contacts()->create(['name' => 'Cadangan', 'is_primary' => false]);

        $this->actingAs($this->salesUser())
            ->delete(route('customer-contacts.destroy', $primary))
            ->assertRedirect(route('customers.show', $customer));

        $this->assertTrue((bool) $other->fresh()->is_primary);
        $this->assertSame('Cadangan', $customer->fresh()->contact_person);
    }

    public function test_lead_pages_show_primary_pic_even_when_legacy_column_is_empty(): void
    {
        $customer = Customer::create(['name' => 'PT Tampil', 'pt_group' => 'NTI', 'whatsapp' => '089999']);
        $customer->contacts()->create([
            'name' => 'Dimas',
            'whatsapp' => '08122334455',
            'is_primary' => true,
        ]);
        Lead::create([
            'customer_id' => $customer->id,
            'pt_group' => 'NTI',
            'segment' => 'vendor',
            'incoming_date' => now()->toDateString(),
            'status' => 'cool',
        ]);

        $user = $this->marketingUser();

        $this->actingAs($user)
            ->get(route('leads.index'))
            ->assertOk()
            ->assertSee('PIC: Dimas');
    }

    public function test_customer_and_lead_edit_forms_render_primary_pic(): void
    {
        $customer = Customer::create(['name' => 'PT Form Tampil', 'pt_group' => 'NTI']);
        $customer->contacts()->create([
            'name' => 'Vina',
            'position' => 'Manager',
            'whatsapp' => '081200001111',
            'is_primary' => true,
        ]);

        $lead = Lead::create([
            'customer_id' => $customer->id,
            'pt_group' => 'NTI',
            'segment' => 'vendor',
            'incoming_date' => now()->toDateString(),
            'status' => 'cool',
        ]);

        $this->actingAs($this->salesUser())
            ->get(route('customers.edit', $customer))
            ->assertOk()
            ->assertSee('value="Vina"', false)
            ->assertSee('value="081200001111"', false);

        $this->actingAs($this->marketingUser())
            ->get(route('leads.edit', $lead))
            ->assertOk()
            ->assertSee('value="Vina"', false);
    }

    public function test_backfill_migration_fills_contacts_and_mirror(): void    {
        // Simulasi data lama: PIC hanya ada di kolom, belum punya baris contact.
        $customer = Customer::create([
            'name' => 'PT Warisan',
            'pt_group' => 'NTI',
            'whatsapp' => '0812777888',
        ]);
        DB::table('customers')->where('id', $customer->id)->update(['contact_person' => 'Pak Warisan']);

        $migration = require database_path('migrations/2026_10_28_000004_backfill_customer_primary_pic.php');
        $migration->up();

        $contact = $customer->fresh()->primaryPic();
        $this->assertNotNull($contact);
        $this->assertSame('Pak Warisan', $contact->name);
        $this->assertSame('0812777888', $contact->whatsapp);
        $this->assertSame('Pak Warisan', $customer->fresh()->contact_person);

        // Idempoten: dijalankan dua kali tidak menggandakan contact.
        $migration->up();
        $this->assertSame(1, CustomerContact::where('customer_id', $customer->id)->count());
    }
}
