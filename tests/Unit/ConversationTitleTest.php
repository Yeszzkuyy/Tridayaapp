<?php

namespace Tests\Unit;

use App\Services\ConversationTitle;
use Tests\TestCase;

class ConversationTitleTest extends TestCase
{
    public function test_short_title_passes_through(): void
    {
        $this->assertSame('Jadwal Kerja', ConversationTitle::smart('Jadwal Kerja'));
    }

    public function test_strips_quotes_and_trailing_punctuation(): void
    {
        $this->assertSame('Jadwal Kerja', ConversationTitle::smart('"Jadwal Kerja."'));
        $this->assertSame('Jadwal Kerja', ConversationTitle::smart('«Jadwal Kerja»,'));
    }

    public function test_strips_legacy_dots_suffix(): void
    {
        $this->assertSame(
            'Cara memperbaiki jadwal kerja yang rusak',
            ConversationTitle::smart('Cara memperbaiki jadwal kerja yang rusak...')
        );
    }

    public function test_cuts_at_word_boundary_without_dots(): void
    {
        $title = 'Panduan lengkap cara mengatur jadwal teknisi lapangan setiap hari kerja';
        $result = ConversationTitle::smart($title, 40);

        $this->assertLessThanOrEqual(40, mb_strlen($result));
        $this->assertStringNotContainsString('...', $result);
        $this->assertStringNotContainsString('…', $result);
        // Potong di spasi: tidak ada kata kepotong.
        $this->assertSame('Panduan lengkap cara mengatur jadwal', $result);
    }

    public function test_empty_title_falls_back(): void
    {
        $fallback = __('Percakapan baru');
        $this->assertSame($fallback, ConversationTitle::smart(null));
        $this->assertSame($fallback, ConversationTitle::smart('   '));
        $this->assertSame($fallback, ConversationTitle::smart('...'));
        $this->assertSame($fallback, ConversationTitle::smart('"..."'));
    }

    public function test_unicode_and_emoji_survive(): void
    {
        $this->assertSame('Jadwal 😊 Kerja', ConversationTitle::smart('  Jadwal 😊 Kerja  '));
    }

    public function test_clean_message_trims_invisible_ends(): void
    {
        $this->assertSame('tes', ConversationTitle::cleanMessage("tes\n\n\n"));
        $this->assertSame('tes', ConversationTitle::cleanMessage("  tes\u{00A0}"));
        $this->assertSame('tes', ConversationTitle::cleanMessage("\u{200B}tes\u{FEFF}"));
        // Isi tengah (enter asli) dipertahankan.
        $this->assertSame("baris satu\nbaris dua", ConversationTitle::cleanMessage("baris satu\nbaris dua"));
        $this->assertSame('', ConversationTitle::cleanMessage("\u{200B}\n  "));
    }

    public function test_clean_content_normalizes_exotic_spaces(): void
    {
        // U+202F (spasi sempit AI) → spasi biasa.
        $this->assertSame(
            'mencari detail customer',
            ConversationTitle::cleanContent("mencari\u{202F}detail\u{202F}customer")
        );
    }

    public function test_clean_content_removes_zero_width_chars(): void
    {
        $this->assertSame('tes', ConversationTitle::cleanContent("t\u{200B}es"));
        $this->assertSame('tes', ConversationTitle::cleanContent("\u{FEFF}tes"));
    }

    public function test_clean_content_keeps_legit_whitespace(): void
    {
        // NBSP (anti-pisah angka) + newline poisoning dipertahankan.
        $this->assertSame("Rp\u{00A0}1.000", ConversationTitle::cleanContent("Rp\u{00A0}1.000"));
        $this->assertSame("baris satu\nbaris dua", ConversationTitle::cleanContent("baris satu\nbaris dua"));
    }
}
