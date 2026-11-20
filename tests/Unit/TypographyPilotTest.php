<?php

namespace Tests\Unit;

use Tests\TestCase;

class TypographyPilotTest extends TestCase
{
    /** @return list<string> */
    private function pilotFiles(): array
    {
        return [
            'resources/views/dashboard/index.blade.php',
            'resources/views/sales/my-leads.blade.php',
            'resources/views/sales/my-leads/_filters.blade.php',
            'resources/views/sales/my-leads/_table.blade.php',
            'resources/views/sales/my-leads/_cards.blade.php',
            'resources/views/sales/my-leads/_next-cell.blade.php',
            'resources/views/sales/my-leads/_note-cell.blade.php',
            'resources/views/sales/my-leads/_color-picker.blade.php',
            'resources/views/customers/show.blade.php',
            'resources/views/customers/project-card.blade.php',
            'resources/views/customers/doc-card.blade.php',
            'resources/views/customers/partials/all-documents.blade.php',
            'resources/views/customers/partials/project-documents.blade.php',
            'resources/views/customers/partials/general-documents.blade.php',
            'resources/views/customers/partials/transaction-documents.blade.php',
            'resources/views/customers/partials/timeline.blade.php',
            'resources/views/documents/partials/detail-panel.blade.php',
            'resources/views/documents/partials/table.blade.php',
        ];
    }

    public function test_pilot_pages_do_not_use_text_smaller_than_12px(): void
    {
        $pattern = '/text-\[(?:9|10|11)(?:\.[0-9]+)?px\]|font-size:\s*(?:9|10|11)(?:\.[0-9]+)?px/';

        foreach ($this->pilotFiles() as $file) {
            $contents = file_get_contents(base_path($file));
            $this->assertDoesNotMatchRegularExpression($pattern, $contents, $file);
        }
    }

    public function test_pilot_pages_do_not_use_raw_neutral_text_colors(): void
    {
        $pattern = '/text-(?:slate|gray|zinc|neutral|stone)-[0-9]{2,3}/';

        foreach ($this->pilotFiles() as $file) {
            $contents = file_get_contents(base_path($file));
            $this->assertDoesNotMatchRegularExpression($pattern, $contents, $file);
        }
    }
}
