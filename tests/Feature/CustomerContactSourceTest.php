<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Lead;
use App\Models\User;
use App\Services\CustomerContactService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Sumber kebenaran kontak: customer_contacts.
 * `customers.contact_person` hanya cache kompatibilitas.
 */
class CustomerContactSourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    private function service(): CustomerContactService
    {
        return app(CustomerContactService::class);
    }

    private function makeCustomer(array $attributes = []): Customer
    {
        return Customer::create(array_merge([
            'name' => 'PT Kontak',
            'company' => 'PT Kontak',
        ], $attributes));
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function makeLead(Customer $customer, User $assignee): Lead
    {
        return Lead::create([
            'customer_id' => $customer->id,
            'pt_group' => 'NTI',
            'segment' => 'vendor',
            'status' => 'cool',
            'kebutuhan' => 'Butuh CCTV',
            'assigned_to' => $assignee->id,
            'incoming_date' => '2026-01-05',
        ]);
    }

    // ============================================================
    // ATURAN PRIMARY
    // ============================================================

    public function test_first_contact_is_not_primary_unless_explicit(): void
    {
        $customer = $this->makeCustomer(['contact_person' => 'PIC Lama']);

        $contact = $this->service()->create($customer, ['name' => 'Budi']);

        $this->assertFalse($contact->is_primary);
        $this->assertNull($customer->fresh()->contact_person);
        $this->assertNull($customer->fresh()->picName());
    }

    public function test_set_primary_keeps_only_one_and_syncs_cache(): void
    {
        $customer = $this->makeCustomer();
        $first = $this->service()->create($customer, ['name' => 'Andi', 'is_primary' => true]);
        $second = $this->service()->create($customer, ['name' => 'Budi']);

        $this->service()->setPrimary($second);

        $this->assertSame(1, $customer->contacts()->where('is_primary', true)->count());
        $this->assertFalse($first->fresh()->is_primary);
        $this->assertTrue($second->fresh()->is_primary);
        $this->assertSame('Budi', $customer->fresh()->contact_person);
        $this->assertSame('Budi', $customer->fresh()->picName());
    }

    public function test_editing_secondary_does_not_touch_primary(): void
    {
        $customer = $this->makeCustomer();
        $primary = $this->service()->create($customer, ['name' => 'Andi', 'is_primary' => true]);
        $secondary = $this->service()->create($customer, ['name' => 'Budi']);

        $this->service()->update($secondary, ['name' => 'Budi Santoso', 'position' => 'Purchasing']);

        $this->assertSame('Andi', $primary->fresh()->name);
        $this->assertTrue($primary->fresh()->is_primary);
        $this->assertSame('Andi', $customer->fresh()->contact_person);
    }

    public function test_deleting_primary_does_not_auto_promote_another(): void
    {
        $customer = $this->makeCustomer();
        $primary = $this->service()->create($customer, ['name' => 'Andi', 'is_primary' => true]);
        $secondary = $this->service()->create($customer, ['name' => 'Budi']);

        $this->service()->delete($primary);

        $this->assertSame(0, $customer->contacts()->where('is_primary', true)->count());
        $this->assertFalse($secondary->fresh()->is_primary);
        $this->assertNull($customer->fresh()->contact_person);
    }

    public function test_database_rejects_second_primary(): void
    {
        $customer = $this->makeCustomer();
        $this->service()->create($customer, ['name' => 'Andi', 'is_primary' => true]);

        $this->expectException(QueryException::class);

        DB::table('customer_contacts')->insert([
            'customer_id' => $customer->id,
            'name' => 'Budi',
            'is_primary' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    // ============================================================
    // FORM CUSTOMER & LEAD
    // ============================================================

    public function test_customer_form_turns_contact_person_into_primary_contact(): void
    {
        $user = $this->userWithRole('sales');

        $this->actingAs($user)->post(route('customers.store'), [
            'name' => 'PT Baru',
            'contact_person' => 'Pak Budi',
            'pt_group' => 'NTI',
            'address' => 'Jl. Merdeka 1',
        ])->assertRedirect();

        $customer = Customer::where('name', 'PT Baru')->firstOrFail();
        $primary = $customer->primaryContact();

        $this->assertNotNull($primary);
        $this->assertSame('Pak Budi', $primary->name);
        $this->assertSame('Pak Budi', $customer->contact_person);
    }

    public function test_editing_lead_does_not_overwrite_customer_primary_contact(): void
    {
        $user = $this->userWithRole('marketing');
        $customer = $this->makeCustomer(['contact_person' => 'PIC Lama']);
        $primary = $this->service()->create($customer, ['name' => 'Bu Sari', 'is_primary' => true]);
        $lead = $this->makeLead($customer, $user);

        $this->actingAs($user)->put(route('leads.update', $lead), [
            'customer_mode' => 'existing',
            'customer_id' => $customer->id,
            'customer_name' => 'PT Kontak',
            'customer_contact_person' => 'Orang Lain',
            'pt_group' => 'NTI',
            'segment' => 'vendor',
            'kebutuhan' => 'Butuh CCTV',
            'incoming_date' => '2026-01-05',
        ])->assertRedirect();

        $this->assertSame('Bu Sari', $primary->fresh()->name);
        $this->assertTrue($primary->fresh()->is_primary);
        $this->assertSame('Bu Sari', $customer->fresh()->contact_person);
        $this->assertSame(1, $customer->contacts()->count());
    }

    public function test_lead_detail_shows_primary_contact_not_stale_legacy_value(): void
    {
        $user = $this->userWithRole('marketing');
        $customer = $this->makeCustomer(['contact_person' => 'PIC Lama']);
        $this->service()->create($customer, ['name' => 'Bu Sari', 'is_primary' => true]);

        // Simulasikan cache basi dari data lama.
        $customer->forceFill(['contact_person' => 'PIC Lama'])->saveQuietly();

        $lead = $this->makeLead($customer, $user);

        $this->actingAs($user)->get(route('leads.show', $lead))
            ->assertOk()
            ->assertSee('Bu Sari')
            ->assertDontSee('PIC Lama');
    }

    // ============================================================
    // BACKFILL
    // ============================================================

    public function test_backfill_only_touches_customers_without_contacts_and_is_idempotent(): void
    {
        $legacy = $this->makeCustomer(['contact_person' => 'Pak Lama']);
        $empty = $this->makeCustomer();
        $withPrimary = $this->makeCustomer(['contact_person' => 'Sudah Ada']);
        $this->service()->create($withPrimary, ['name' => 'Sudah Ada', 'is_primary' => true]);
        $withoutPrimary = $this->makeCustomer(['contact_person' => 'Perlu Review']);
        $this->service()->create($withoutPrimary, ['name' => 'Sekunder']);

        $this->artisan('customers:backfill-contacts')->assertSuccessful();
        $this->artisan('customers:backfill-contacts')->assertSuccessful();

        $this->assertSame(1, $legacy->contacts()->count());
        $this->assertSame('Pak Lama', $legacy->primaryContact()->name);
        $this->assertSame('Pak Lama', $legacy->fresh()->contact_person);

        $this->assertSame(0, $empty->contacts()->count());
        $this->assertSame(1, $withPrimary->contacts()->count());
        $this->assertSame('Sudah Ada', $withPrimary->primaryContact()->name);

        // Punya kontak tapi tanpa primary: tidak ditebak, tetap tanpa primary.
        $this->assertSame(1, $withoutPrimary->contacts()->count());
        $this->assertNull($withoutPrimary->primaryContact());
    }

    public function test_backfill_dry_run_writes_nothing(): void
    {
        $legacy = $this->makeCustomer(['contact_person' => 'Pak Lama']);

        $this->artisan('customers:backfill-contacts', ['--dry-run' => true])->assertSuccessful();

        $this->assertSame(0, $legacy->contacts()->count());
    }
}
