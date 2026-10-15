<?php

namespace Tests\Unit;

use App\Http\Controllers\OfficeAssistantController;
use RuntimeException;
use Tests\TestCase;

class AssistantResilienceTest extends TestCase
{
    public function test_transient_errors_are_recognized(): void
    {
        foreach ([
            'OpenRouter Error: [502] Upstream error from Nvidia: ResourceExhausted',
            'OpenRouter Error: [503] Upstream error from Nvidia: Service temporarily overloaded',
            'OpenRouter Error: [429] Rate limited',
            'cURL error 28: Operation timed out after 60000 milliseconds',
        ] as $message) {
            $this->assertTrue(
                OfficeAssistantController::isTransientAiError(new RuntimeException($message)),
                $message
            );
        }
    }

    public function test_permanent_errors_are_not_retried(): void
    {
        foreach ([
            'OpenRouter Error: [401] Invalid API key',
            'OpenRouter Error: [404] Model not found',
            'Validation failed',
        ] as $message) {
            $this->assertFalse(
                OfficeAssistantController::isTransientAiError(new RuntimeException($message)),
                $message
            );
        }
    }

    public function test_filesearch_unsupported_is_recognized(): void
    {
        $this->assertTrue(OfficeAssistantController::isFileSearchUnsupported(
            new RuntimeException('OpenRouter does not support [FileSearch] provider tools.')
        ));
        $this->assertFalse(OfficeAssistantController::isFileSearchUnsupported(
            new RuntimeException('OpenRouter Error: [503] overloaded')
        ));
    }
}
