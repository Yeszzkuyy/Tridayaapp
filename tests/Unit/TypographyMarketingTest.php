<?php

namespace Tests\Unit;

use Tests\TestCase;

class TypographyMarketingTest extends TestCase
{
    /** @return list<string> */
    private function files(): array
    {
        return [
            'resources/views/leads/activities.blade.php',
            'resources/views/leads/create.blade.php',
            'resources/views/leads/edit.blade.php',
            'resources/views/leads/import.blade.php',
            'resources/views/leads/index.blade.php',
            'resources/views/leads/monitoring.blade.php',
            'resources/views/leads/show.blade.php',
            'resources/views/leads/partials/timeline.blade.php',
            'resources/views/partners/index.blade.php',
            'resources/views/partners/_table.blade.php',
            'resources/views/partners/create.blade.php',
            'resources/views/partners/edit.blade.php',
            'resources/views/marketing/dashboard.blade.php',
        ];
    }

    public function test_marketing_pages_do_not_use_text_smaller_than_12px(): void
    {
        $pattern = '/text-\[(?:9|10|11)(?:\.[0-9]+)?px\]|font-size:\s*(?:9|10|11)(?:\.[0-9]+)?px/';

        foreach ($this->files() as $file) {
            $this->assertDoesNotMatchRegularExpression(
                $pattern,
                file_get_contents(base_path($file)),
                $file
            );
        }
    }

    public function test_marketing_pages_do_not_use_raw_neutral_text_colors(): void
    {
        $pattern = '/text-(?:slate|gray|zinc|neutral|stone)-[0-9]{2,3}/';

        foreach ($this->files() as $file) {
            $this->assertDoesNotMatchRegularExpression(
                $pattern,
                file_get_contents(base_path($file)),
                $file
            );
        }
    }
}
