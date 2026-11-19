<?php

namespace Tests\Unit;

use App\Rules\SecureFile;
use PHPUnit\Framework\TestCase;

class SecureFilePreviewTest extends TestCase
{
    public function test_previewable_extensions_cover_browser_and_office_formats(): void
    {
        foreach (['photo.pdf', 'photo.PNG', 'letter.docx', 'sheet.xlsx', 'deck.pptx', 'notes.txt', 'data.csv', 'bundle.zip'] as $name) {
            $this->assertTrue(SecureFile::isPreviewable($name), $name);
        }

        // Format biner lama & RAR tetap harus diunduh.
        $this->assertFalse(SecureFile::isPreviewable('legacy.doc'));
        $this->assertFalse(SecureFile::isPreviewable('legacy.xls'));
        $this->assertFalse(SecureFile::isPreviewable('legacy.ppt'));
        $this->assertFalse(SecureFile::isPreviewable('archive.rar'));
    }

    public function test_preview_kind_maps_extension_to_viewer_type(): void
    {
        $this->assertSame('image', SecureFile::previewKind('photo.JPEG'));
        $this->assertSame('pdf', SecureFile::previewKind('invoice.pdf'));
        $this->assertSame('video', SecureFile::previewKind('clip.mp4'));
        $this->assertSame('audio', SecureFile::previewKind('voice.mp3'));
        $this->assertSame('txt', SecureFile::previewKind('notes.md'));
        $this->assertSame('docx', SecureFile::previewKind('report.docx'));
        $this->assertSame('csv', SecureFile::previewKind('data.csv'));
        $this->assertSame('other', SecureFile::previewKind('legacy.doc'));
    }
}
