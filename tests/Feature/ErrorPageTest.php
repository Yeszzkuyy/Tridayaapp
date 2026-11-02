<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ErrorPageTest extends TestCase
{
    public function test_abort_422_renders_the_friendly_rejection_page(): void
    {
        Route::middleware('web')->get('/__test/422', function () {
            abort(422, 'The assignment target must be a Sales or Marketing user.');
        });

        $this->get('/__test/422')
            ->assertStatus(422)
            ->assertSee(__('Request Rejected'))
            ->assertSee('The assignment target must be a Sales or Marketing user.')
            ->assertDontSee('Oops! An Error Occurred');
    }

    public function test_abort_422_without_message_falls_back_to_generic_copy(): void
    {
        Route::middleware('web')->get('/__test/422-empty', function () {
            abort(422);
        });

        $this->get('/__test/422-empty')
            ->assertStatus(422)
            ->assertSee(__('Request Rejected'))
            ->assertSee(__('This request could not be processed.'))
            ->assertDontSee('Unprocessable Content');
    }

    public function test_rejected_business_rule_is_logged_as_warning(): void
    {
        Log::spy();

        Route::middleware('web')->get('/__test/422-log', function () {
            abort(422, 'Status dokumen belum siap.');
        });

        $this->get('/__test/422-log')->assertStatus(422);

        Log::shouldHaveReceived('warning')->once();
    }
}
