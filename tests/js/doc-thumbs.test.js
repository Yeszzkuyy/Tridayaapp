import assert from 'node:assert/strict';
import test from 'node:test';
import { readFileSync } from 'node:fs';
import { strToU8, unzipSync, zipSync } from 'fflate';
import {
    docxParagraphs,
    parseCsv,
    pptxTexts,
    xlsxRows,
    zipNames,
} from '../../resources/js/doc-thumbs.js';

const DOCX_XML = `<?xml version="1.0" encoding="UTF-8"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body>
<w:p><w:r><w:t>Judul Laporan</w:t></w:r></w:p>
<w:p><w:r><w:t xml:space="preserve">Biaya &amp; pajak 10%</w:t></w:r></w:p>
<w:p><w:r><w:t>Baris ketiga</w:t></w:r></w:p>
</w:body></w:document>`;

const XLSX_SHARED = `<?xml version="1.0" encoding="UTF-8"?>
<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="3" uniqueCount="3">
<si><t>Nama Barang</t></si><si><t>Jumlah</t></si><si><t>Kabel 3m</t></si>
</sst>`;

const XLSX_SHEET = `<?xml version="1.0" encoding="UTF-8"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>
<row r="1"><c r="A1" t="s"><v>0</v></c><c r="B1" t="s"><v>1</v></c></row>
<row r="2"><c r="A2" t="s"><v>2</v></c><c r="B2"><v>25</v></c></row>
</sheetData></worksheet>`;

const PPTX_SLIDE = `<?xml version="1.0" encoding="UTF-8"?>
<p:sld xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"><p:cSld><p:spTree><p:sp>
<p:txBody><a:p><a:r><a:t>Evaluasi Proyek</a:t></a:r></a:p>
<p><a:r><a:t>Maret 2026</a:t></a:r></a:p></p:txBody>
</p:sp></p:spTree></p:cSld></p:sld>`;

test('docx: paragraf diambil dari word/document.xml', () => {
    const files = { 'word/document.xml': strToU8(DOCX_XML) };

    const paragraphs = docxParagraphs(files);

    assert.deepEqual(paragraphs, ['Judul Laporan', 'Biaya & pajak 10%', 'Baris ketiga']);
});

test('docx: file tanpa document.xml tidak error', () => {
    assert.deepEqual(docxParagraphs({}), []);
});

test('xlsx: baris & sel diambil dari sharedStrings + sheet1', () => {
    const files = {
        'xl/sharedStrings.xml': strToU8(XLSX_SHARED),
        'xl/worksheets/sheet1.xml': strToU8(XLSX_SHEET),
    };

    const rows = xlsxRows(files);

    assert.deepEqual(rows, [
        ['Nama Barang', 'Jumlah'],
        ['Kabel 3m', '25'],
    ]);
});

test('xlsx: file tanpa sheet tidak error', () => {
    assert.deepEqual(xlsxRows({}), []);
});

test('pptx: teks slide pertama diambil', () => {
    const files = { 'ppt/slides/slide1.xml': strToU8(PPTX_SLIDE) };

    assert.deepEqual(pptxTexts(files), ['Evaluasi Proyek', 'Maret 2026']);
});

test('zip: daftar nama file di dalam arsip', () => {
    const zip = zipSync({
        'dokumen/berkas.txt': strToU8('halo'),
        'dokumen/gambar.png': strToU8('gambar'),
    });

    const names = zipNames(unzipSync, zip);

    assert.deepEqual(names.sort(), ['dokumen/berkas.txt', 'dokumen/gambar.png']);
});

test('csv: sel dengan tanda kutip dibaca utuh', () => {
    const rows = parseCsv('nama,ket\nKabel 3m,"warna merah, 5m"\n');

    assert.deepEqual(rows, [
        ['nama', 'ket'],
        ['Kabel 3m', 'warna merah, 5m'],
    ]);
});

test('thumbnail PDF: bersihkan lewat loading task (PDF.js v6 tak punya pdf.destroy)', () => {
    // Regresi: sempat pakai pdf.destroy() yang sudah dihapus di PDF.js v6.
    // Render sukses, tapi destroy melempar TypeError sehingga kanvas
    // dianggap gagal dan thumbnail PDF hilang semua.
    const src = readFileSync(new URL('../../resources/js/doc-thumbs.js', import.meta.url), 'utf8');

    assert.match(src, /await task\.destroy\(\)/);
    assert.doesNotMatch(src, /await pdf\.destroy\(\)/);
});
