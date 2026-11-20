<?php

namespace Tests\Unit;

use Tests\TestCase;

class TypographySystemTest extends TestCase
{
    /** @return list<string> */
    private function files(): array
    {
        return [
            'resources/views/documents/index.blade.php',
            'resources/views/documents/partials/category-tabs.blade.php',
            'resources/views/documents/partials/filters.blade.php',
            'resources/views/documents/partials/detail-panel.blade.php',
            'resources/views/documents/partials/table.blade.php',
            'resources/views/trash/index.blade.php',
            'resources/views/auth/pending-approval.blade.php',
        ];
    }

    public function test_system_pages_do_not_use_text_smaller_than_12px(): void
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

    public function test_system_pages_do_not_use_raw_neutral_text_colors(): void
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
