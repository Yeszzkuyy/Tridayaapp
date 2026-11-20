<?php

namespace Tests\Unit;

use Tests\TestCase;

class TypographyCustomerTest extends TestCase
{
    /** @return list<string> */
    private function files(): array
    {
        return [
            'resources/views/customers/index.blade.php',
            'resources/views/customers/_list.blade.php',
            'resources/views/customers/create.blade.php',
            'resources/views/customers/edit.blade.php',
            'resources/views/customers/show.blade.php',
            'resources/views/customers/project-card.blade.php',
            'resources/views/customers/doc-card.blade.php',
            'resources/views/customers/partials/all-documents.blade.php',
            'resources/views/customers/partials/project-documents.blade.php',
            'resources/views/customers/partials/general-documents.blade.php',
            'resources/views/customers/partials/transaction-documents.blade.php',
            'resources/views/customers/partials/timeline.blade.php',
        ];
    }

    public function test_customer_pages_do_not_use_text_smaller_than_12px(): void
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

    public function test_customer_pages_do_not_use_raw_neutral_text_colors(): void
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
