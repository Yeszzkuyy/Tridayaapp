<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_settings_page_is_displayed(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('settings.edit'))
            ->assertOk()
            ->assertSee('Setting');
    }

    public function test_user_can_update_preferences(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch(route('settings.update'), [
                'theme' => 'dark',
                'locale' => 'en',
                'notify_email' => '1',
                'notify_system' => '0',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('settings.edit'));

        $user->refresh();

        $this->assertSame('dark', $user->preference('theme'));
        $this->assertSame('en', $user->preference('locale'));
        $this->assertTrue($user->preference('notify_email'));
        $this->assertFalse($user->preference('notify_system'));
    }

    public function test_autosave_returns_json_and_persists_toggles(): void
    {
        $user = User::factory()->create();

        // Checkbox off = key absent (seperti FormData browser) → tersimpan false.
        $this->actingAs($user)
            ->patchJson(route('settings.update'), [
                'theme' => 'dark',
                'locale' => 'id',
                'notify_system' => '1',
            ])
            ->assertOk()
            ->assertJsonPath('ok', true);

        $user->refresh();

        $this->assertFalse($user->preference('notify_email'));
        $this->assertFalse($user->preference('notify_push'));
        $this->assertTrue($user->preference('notify_system'));
    }

    public function test_preferences_are_validated(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch(route('settings.update'), [
                'theme' => 'neon',
                'locale' => 'fr',
            ])
            ->assertSessionHasErrors(['theme', 'locale']);
    }

    public function test_user_can_update_accent_preference(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch(route('settings.update'), [
                'theme' => 'dark',
                'accent' => 'purple',
                'locale' => 'en',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('settings.edit'));

        $user->refresh();

        $this->assertSame('purple', $user->preference('accent'));
    }

    public function test_accent_preference_is_validated(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch(route('settings.update'), [
                'theme' => 'dark',
                'accent' => 'neon',
                'locale' => 'en',
            ])
            ->assertSessionHasErrors(['accent']);
    }

    public function test_appearance_endpoint_syncs_theme_and_accent(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson(route('settings.appearance'), [
                'theme' => 'dark',
                'accent' => 'emerald',
            ])
            ->assertOk();

        $user->refresh();

        $this->assertSame('dark', $user->preference('theme'));
        $this->assertSame('emerald', $user->preference('accent'));
    }

    public function test_appearance_endpoint_validates_input(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson(route('settings.appearance'), [
                'theme' => 'neon',
                'accent' => 'neon',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['theme', 'accent']);
    }

    public function test_advanced_settings_shows_inline_lock_without_redirect(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('settings.advanced'))
            ->assertOk()
            ->assertSee(__('Konfirmasi Password'), false)
            ->assertDontSee(__('Informasi Akun'), false);
    }

    public function test_advanced_settings_rejects_wrong_password_inline(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('settings.advanced.confirm'), ['password' => 'salah'])
            ->assertSessionHasErrors('password');

        $this->actingAs($user)
            ->get(route('settings.advanced'))
            ->assertOk()
            ->assertDontSee(__('Informasi Akun'), false);
    }

    public function test_advanced_settings_unlocks_with_correct_password_for_single_visit(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('settings.advanced.confirm'), ['password' => 'password'])
            ->assertRedirect(route('settings.advanced'))
            ->assertSessionHasNoErrors();

        // Tepat setelah konfirmasi: isi terbuka.
        $this->actingAs($user)
            ->get(route('settings.advanced'))
            ->assertOk()
            ->assertSee(__('Pengaturan Lanjutan'))
            ->assertSee(__('Informasi Akun'));

        // Kunjungan berikutnya: terkunci lagi.
        $this->actingAs($user)
            ->get(route('settings.advanced'))
            ->assertOk()
            ->assertDontSee(__('Informasi Akun'), false);
    }

    public function test_account_info_can_be_updated_from_advanced(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => now()->getTimestamp()])
            ->patch(route('profile.update'), [
                'name' => 'Nama Baru',
                'email' => 'baru@example.com',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('settings.advanced'));

        $user->refresh();

        $this->assertSame('Nama Baru', $user->name);
        $this->assertSame('baru@example.com', $user->email);
    }

    public function test_layout_renders_dark_class_from_server_when_theme_is_dark(): void
    {
        // Regresi: morph wire:navigate menghapus class 'dark' client bila HTML
        // server tidak memuatnya — semua menu SPA balik light. Server wajib
        // me-render class dark agar morph mempertahankannya.
        $user = User::factory()->create();
        $user->preferences = array_merge($user->preferences ?? [], ['theme' => 'dark']);
        $user->save();

        $html = $this->actingAs($user)
            ->get(route('settings.edit'))
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression('/<html[^>]*data-mode="dark"/', $html);
        $this->assertMatchesRegularExpression('/<html[^>]*class="dark"/', $html);
    }

    public function test_layout_omits_dark_class_when_theme_is_light(): void
    {
        $user = User::factory()->create();
        $user->preferences = array_merge($user->preferences ?? [], ['theme' => 'light']);
        $user->save();

        $html = $this->actingAs($user)
            ->get(route('settings.edit'))
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression('/<html[^>]*data-mode="light"/', $html);
        $this->assertDoesNotMatchRegularExpression('/<html[^>]*class="dark"/', $html);
    }

    public function test_locale_preference_switches_app_language(): void
    {
        $user = User::factory()->create();
        $user->preferences = ['locale' => 'en'];
        $user->save();

        $this->actingAs($user)
            ->get(route('settings.edit'));

        $this->assertSame('en', App::getLocale());

        $user->preferences = ['locale' => 'id'];
        $user->save();

        $this->actingAs($user)
            ->get(route('settings.edit'));

        $this->assertSame('id', App::getLocale());
    }
}