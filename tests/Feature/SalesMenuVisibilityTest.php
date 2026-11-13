<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesMenuVisibilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    private function sales(): User
    {
        $user = User::factory()->create();
        $user->assignRole('sales');

        return $user;
    }

    public function test_follow_up_and_dar_menus_show_by_default(): void
    {
        $sales = $this->sales();

        $html = $this->actingAs($sales)->get(route('sales.my-leads'))->assertOk()->getContent();

        $this->assertStringContainsString('href="'.route('sales.follow-ups.index').'"', $html);
        $this->assertStringContainsString('href="'.route('sales.dar.index').'"', $html);
    }

    public function test_menus_can_be_hidden_and_pages_remain_available(): void
    {
        $sales = $this->sales();

        $this->actingAs($sales)->patch(route('settings.update'), [
            'theme' => 'system',
            'locale' => 'en',
        ])->assertRedirect(route('settings.edit'));

        $sales->refresh();
        $this->assertFalse($sales->preference('menu_follow_up'));
        $this->assertFalse($sales->preference('menu_dar'));

        $html = $this->actingAs($sales)->get(route('sales.my-leads'))->assertOk()->getContent();
        $this->assertStringNotContainsString('href="'.route('sales.follow-ups.index').'"', $html);
        $this->assertStringNotContainsString('href="'.route('sales.dar.index').'"', $html);

        // Halaman tetap bisa dibuka walau menu disembunyikan.
        $this->actingAs($sales)->get(route('sales.follow-ups.index'))->assertOk();
        $this->actingAs($sales)->get(route('sales.dar.index'))->assertOk();
    }

    public function test_menus_can_be_shown_again(): void
    {
        $sales = $this->sales();

        $this->actingAs($sales)->patch(route('settings.update'), [
            'theme' => 'system',
            'locale' => 'en',
            'menu_follow_up' => 1,
            'menu_dar' => 1,
        ])->assertRedirect(route('settings.edit'));

        $sales->refresh();
        $this->assertTrue($sales->preference('menu_follow_up'));
        $this->assertTrue($sales->preference('menu_dar'));

        $html = $this->actingAs($sales)->get(route('sales.my-leads'))->assertOk()->getContent();
        $this->assertStringContainsString('href="'.route('sales.follow-ups.index').'"', $html);
        $this->assertStringContainsString('href="'.route('sales.dar.index').'"', $html);
    }
}
