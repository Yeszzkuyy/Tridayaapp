<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Penjaga standar feedback UI (AGENTS.md §7.3):
 * - tidak ada confirm() bawaan browser di view;
 * - tidak ada kartu centang lama (<x-status-card> / success_card);
 * - kartu konfirmasi standar terpasang di layout.
 */
class UiFeedbackStandardTest extends TestCase
{
    public function test_views_do_not_use_native_browser_confirm(): void
    {
        $offenders = [];

        foreach ($this->bladeFiles() as $file) {
            $contents = file_get_contents($file);

            $patterns = [
                '/window\.confirm\s*\(/',
                '/return\s+confirm\s*\(/',
                '/if\s*\(\s*confirm\s*\(/',
                '/onclick\s*=\s*"[^"]*confirm\s*\(/',
                '/onsubmit\s*=\s*"[^"]*confirm\s*\(/',
            ];

            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $contents)) {
                    $offenders[] = str_replace(base_path().'/', '', $file);
                    break;
                }
            }
        }

        $this->assertSame([], $offenders, 'Gunakan <x-confirm-dialog> (data-confirm), bukan confirm() bawaan browser.');
    }

    public function test_legacy_success_card_is_removed(): void
    {
        $offenders = [];

        foreach ($this->bladeFiles() as $file) {
            if (str_contains(file_get_contents($file), 'x-status-card')) {
                $offenders[] = str_replace(base_path().'/', '', $file);
            }
        }

        $this->assertSame([], $offenders, 'Kartu centang <x-status-card> sudah dipensiunkan; pakai toast standar.');
        $this->assertFileDoesNotExist(resource_path('views/components/status-card.blade.php'));
    }

    public function test_confirm_dialog_is_mounted_once_in_layout(): void
    {
        $layout = file_get_contents(resource_path('views/layouts/app.blade.php'));

        $this->assertStringContainsString('<x-confirm-dialog />', $layout);
        $this->assertStringContainsString('<x-toast type="error">', $layout, 'Error validasi tetap tampil inline.');
    }

    /**
     * @return list<string>
     */
    private function bladeFiles(): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(resource_path('views')),
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), '.blade.php')) {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }
}
