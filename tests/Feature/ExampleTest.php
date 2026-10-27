<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * Halaman utama menampilkan halaman login untuk guest (tanpa redirect).
     */
    public function test_the_application_shows_login_page_to_guests(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee(__('Sign in with Google'));
    }
}
