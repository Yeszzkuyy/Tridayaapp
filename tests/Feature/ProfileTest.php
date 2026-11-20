<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed(): void
    {
        $user = $this->makeUserWithRole();

        $response = $this
            ->actingAs($user)
            ->get('/profile');

        $response->assertOk();
    }

    /**
     * Menu foto kanan atas harus memuat halaman penuh, bukan wire:navigate.
     * Navigasi SPA tidak membangun ulang iframe tab workspace sehingga area
     * isi halaman Profile tampil kosong.
     */
    public function test_header_profile_shortcut_uses_full_page_navigation(): void
    {
        $user = $this->makeUserWithRole();

        $response = $this
            ->actingAs($user)
            ->get('/profile');

        $response->assertOk();

        // Hanya link Profile di header (menu foto kanan atas) yang diuji;
        // link sidebar boleh tetap wire:navigate karena ditangani tab workspace.
        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML($response->getContent());
        libxml_clear_errors();

        $nodes = (new \DOMXPath($dom))->query('//header//a[@href="'.route('profile.edit').'"]');

        $this->assertCount(1, $nodes);

        $attributes = [];
        foreach ($nodes->item(0)->attributes as $attribute) {
            $attributes[] = $attribute->nodeName;
        }

        $this->assertEmpty(
            array_filter($attributes, fn ($name) => str_contains($name, 'wire:navigate')),
            'Link Profile di header tidak boleh memakai navigasi SPA (area isi workspace bisa kosong).',
        );
    }

    public function test_profile_information_can_be_updated(): void
    {
        $user = $this->makeUserWithRole();

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('settings.advanced'));

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertSame('test@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
    }

    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged(): void
    {
        $user = $this->makeUserWithRole();

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => $user->email,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('settings.advanced'));

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_user_can_delete_their_account(): void
    {
        $user = $this->makeUserWithRole();

        $response = $this
            ->actingAs($user)
            ->delete('/profile', [
                'password' => 'password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/');

        $this->assertGuest();
        $this->assertSoftDeleted($user->fresh());
    }

    public function test_correct_password_must_be_provided_to_delete_account(): void
    {
        $user = $this->makeUserWithRole();

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->delete('/profile', [
                'password' => 'wrong-password',
            ]);

        $response
            ->assertSessionHasErrorsIn('userDeletion', 'password')
            ->assertRedirect('/profile');

        $this->assertNotNull($user->fresh());
    }
}
