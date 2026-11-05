<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Penjaga tampilan menu Customer: header ringkas (tanpa filler),
 * CTA solid, dan banner dekoratif yang sudah dihapus.
 */
class CustomerListUiTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(RoleAndPermissionSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('super-admin');

        return $user;
    }

    public function test_customer_list_is_clean_with_solid_cta_and_description(): void
    {
        Customer::create(['name' => 'PT Nusantara Jaya', 'pt_group' => 'NTI']);

        $response = $this->actingAs($this->admin())->get(route('customers.index'));

        $response->assertOk()
            ->assertSee('Customer List')
            ->assertSee('Manage all Tridaya App customer data.')
            ->assertDontSee('Relationship Data')
            ->assertDontSee('Customer filters')
            ->assertSee('aria-label="Add Customer"', false)
            ->assertSee('bg-accent-600', false);
    }

    public function test_customer_detail_uses_open_header_without_decorative_banner(): void
    {
        $customer = Customer::create(['name' => 'PT Detail Bersih', 'pt_group' => 'NTI', 'address' => 'Jakarta']);

        $response = $this->actingAs($this->admin())->get(route('customers.show', $customer));

        $response->assertOk()
            ->assertSee('PT Detail Bersih')
            ->assertDontSee('Customer Detail')
            ->assertDontSee('from-white via-indigo-50')
            ->assertSee('sticky top-16 z-20', false)
            ->assertSee('aria-label="Edit Customer"', false);
    }

    public function test_legacy_pic_is_backfilled_as_primary_contact_when_detail_opened(): void
    {
        // Customer lama: PIC hanya ada di kolom legacy, belum ada baris kontak.
        $customer = Customer::create([
            'name' => 'PT Legacy',
            'pt_group' => 'NTI',
            'contact_person' => 'Budi Legacy',
            'phone' => '0215551234',
            'email' => 'budi@legacy.test',
        ]);

        $this->assertDatabaseMissing('customer_contacts', ['customer_id' => $customer->id]);

        $response = $this->actingAs($this->admin())->get(route('customers.show', $customer));

        $response->assertOk()->assertSee('Budi Legacy');

        $this->assertDatabaseHas('customer_contacts', [
            'customer_id' => $customer->id,
            'name' => 'Budi Legacy',
            'is_primary' => true,
            'email' => 'budi@legacy.test',
        ]);

        // Membuka ulang tidak boleh menggandakan kontak.
        $this->actingAs($this->admin())->get(route('customers.show', $customer))->assertOk();
        $this->assertSame(1, $customer->contacts()->count());
    }

    public function test_detail_tab_actions_use_flow_buttons(): void
    {
        $customer = Customer::create(['name' => 'PT Flow', 'pt_group' => 'NTI']);

        $html = $this->actingAs($this->admin())->get(route('customers.show', $customer))->getContent();

        foreach (['Edit Customer', 'Add Project', 'Add PIC', 'Upload Document', 'Add Follow Up'] as $label) {
            $this->assertMatchesRegularExpression(
                '/aria-label="'.preg_quote($label, '/').'".{0,400}rounded-\[100px\]/s',
                $html,
                "Tombol '{$label}' belum memakai flow button."
            );
        }

        // Ikon fallback (folder/users/phone) harus benar-benar merender SVG.
        foreach ([
            'folder' => 'M3 7.5a2 2 0 012-2h3.6l1.6 1.8h7.2',
            'users' => 'M3.25 20c0-3.6',
            'phone' => 'M4.5 4.5h3.6l1.6 4',
        ] as $icon => $pathFragment) {
            $this->assertStringContainsString($pathFragment, $html, "Ikon {$icon} pada flow button tidak ter-render.");
        }
    }

    public function test_back_button_uses_its_own_style_not_flow_button(): void
    {
        $customer = Customer::create(['name' => 'PT Back', 'pt_group' => 'NTI']);

        $html = $this->actingAs($this->admin())->get(route('customers.show', $customer))->getContent();

        $this->assertStringContainsString('group/back', $html);
        $this->assertMatchesRegularExpression('/aria-label="Back".{0,600}group\/back/s', $html);
        // Back tidak lagi memakai bentuk pill flow button.
        $this->assertDoesNotMatchRegularExpression('/aria-label="Back".{0,400}rounded-\[100px\]/s', $html);

        // Panel accent meluncur dari kiri saat hover.
        $this->assertMatchesRegularExpression('/aria-label="Back".{0,1200}group-hover\/back\:translate-x-0/s', $html);

        // Wajib pakai token tema, bukan bg-white: `.dark .bg-white{...!important}`
        // mematikan hover di dark mode.
        $backMarkup = substr($html, (int) strpos($html, 'aria-label="Back"'), 1200);
        $this->assertStringNotContainsString('bg-white', $backMarkup, 'Tombol Back tidak boleh memakai bg-white (hover mati di dark mode).');
        $this->assertStringContainsString('bg-[var(--card-bg)]', $backMarkup);
    }
}
