<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/**
 * Mengunci standar tombol (§7 AGENTS.md):
 * - Tombol berlabel: ikon h-4 w-4 + stroke 2.5 (tidak lebih besar dari teks).
 * - Tombol icon-only: ikon h-5 w-5 + stroke 2.
 * - CTA solid: variant="solid" memakai accent penuh.
 * - reveal: opsional (default label selalu tampil).
 */
class ButtonStyleStandardTest extends TestCase
{
    private function render(string $template): string
    {
        return Blade::render($template);
    }

    public function test_labeled_icon_button_uses_small_but_bold_icon(): void
    {
        $html = $this->render('<x-icon-button icon="add" label="Add PIC" title="Add PIC" />');

        $this->assertStringContainsString('class="h-4 w-4 shrink-0"', $html);
        $this->assertStringContainsString('stroke-width="3.5"', $html);
        $this->assertStringNotContainsString('class="h-5 w-5 shrink-0"', $html);

        // Default: label selalu tampil (reveal tidak aktif).
        $this->assertStringContainsString('<span class="">Add PIC</span>', $html);
        $this->assertStringNotContainsString('group-hover/btn:max-w-44', $html);
    }

    public function test_icon_only_button_keeps_larger_icon(): void
    {
        $html = $this->render('<x-icon-button icon="filter" title="Filter" />');

        $this->assertStringContainsString('class="h-5 w-5 shrink-0"', $html);
        $this->assertStringContainsString('stroke-width="2"', $html);
        $this->assertStringContainsString('h-10 w-10', $html);
    }

    public function test_solid_variant_renders_primary_cta_and_reveal_is_opt_in(): void
    {
        $static = $this->render('<x-icon-button as="a" href="#" variant="solid" icon="add" label="New Request" title="New Request" />');
        $this->assertStringContainsString('bg-accent-600', $static);
        $this->assertStringContainsString('text-white', $static);
        $this->assertStringContainsString('New Request</span>', $static);

        $reveal = $this->render('<x-icon-button as="a" href="#" variant="solid" reveal icon="add" label="New Meeting" title="New Meeting" />');
        $this->assertStringContainsString('group-hover/btn:max-w-44', $reveal);
        $this->assertStringContainsString('group-hover/btn:rotate-90', $reveal);
        $this->assertStringContainsString('motion-reduce:transition-none', $reveal);
        $this->assertStringContainsString('aria-label="New Meeting"', $reveal);
    }

    public function test_icon_button_renders_receipt_and_falls_back_to_shared_icon_set(): void
    {
        // Ikon yang ada di peta lokal.
        $receipt = $this->render('<x-icon-button icon="receipt" title="Purchase Order" />');
        $this->assertStringContainsString('<svg', $receipt);
        $this->assertStringContainsString('<path', $receipt);

        // Ikon yang hanya ada di <x-icon> tetap tampil lewat fallback.
        $fallback = $this->render('<x-icon-button icon="clock" title="Clock" />');
        $this->assertStringContainsString('<svg', $fallback);
        $this->assertStringContainsString('<circle', $fallback);
    }

    public function test_every_icon_used_by_icon_button_actually_renders_an_svg(): void
    {
        $views = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(resource_path('views'), \FilesystemIterator::SKIP_DOTS)
        );

        $icons = [];
        foreach ($views as $file) {
            if (! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }
            $src = file_get_contents($file->getPathname());
            if (preg_match_all('/<x-icon-button\b[^>]*?\bicon="([a-z0-9-]+)"/s', $src, $m)) {
                foreach ($m[1] as $name) {
                    $icons[$name] = true;
                }
            }
        }

        $this->assertNotEmpty($icons, 'Tidak ada pemakaian x-icon-button dengan icon= yang terdeteksi.');

        foreach (array_keys($icons) as $name) {
            $html = $this->render('<x-icon-button icon="'.e($name).'" title="Test" />');
            $this->assertMatchesRegularExpression('/<(path|circle|rect|polyline)\b/', $html, "Ikon '{$name}' tidak merender SVG (tombol akan kosong).");
        }
    }
}
