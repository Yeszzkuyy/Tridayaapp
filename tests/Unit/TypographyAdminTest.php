<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class TypographyAdminTest extends TestCase
{
    /** @return list<string> */
    private function files(): array
    {
        $files = [];

        foreach ([resource_path('views/admin'), resource_path('views/admin-panel')] as $dir) {
            foreach (File::allFiles($dir) as $file) {
                if (str_ends_with($file->getFilename(), '.blade.php')) {
                    $files[] = $file->getPathname();
                }
            }
        }

        return $files;
    }

    public function test_admin_pages_do_not_use_text_smaller_than_12px(): void
    {
        $pattern = '/text-\[(?:9|10|11)(?:\.[0-9]+)?px\]|font-size:\s*(?:9|10|11)(?:\.[0-9]+)?px/';

        foreach ($this->files() as $file) {
            $this->assertDoesNotMatchRegularExpression(
                $pattern,
                file_get_contents($file),
                $file
            );
        }
    }

    public function test_admin_pages_do_not_use_raw_neutral_text_colors(): void
    {
        $pattern = '/text-(?:slate|gray|zinc|neutral|stone)-[0-9]{2,3}/';

        foreach ($this->files() as $file) {
            $this->assertDoesNotMatchRegularExpression(
                $pattern,
                file_get_contents($file),
                $file
            );
        }
    }
}
