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
        $this->assertStringContainsString('stroke-width="2.5"', $html);
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
}
