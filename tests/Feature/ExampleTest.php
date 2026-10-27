<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * Halaman utama menampilkan form login untuk guest.
     */
    public function test_the_application_shows_login_for_guests(): void
    {
        $response = $this->get('/');

        $response->assertOk();
    }
}
