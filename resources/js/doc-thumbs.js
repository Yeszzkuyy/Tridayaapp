/**
 * Thumbnail dokumen ala Drive untuk kartu dokumen customer.
 *
 * - <img> menangani file gambar langsung (URL preview privat, same-origin).
 * - <canvas data-pdf-url="..."> digambar halaman pertamanya via PDF.js.
 * - <canvas data-doc-render="txt|csv|docx|xlsx|pptx|zip"> digambar dari
 *   ISI file: teks/tabel asli (Word/Excel/PPT dibaca dari XML di dalam
 *   file ZIP-nya, tanpa alat konversi di server).
 * - Semua pustaka berat (PDF.js 431KB, fflate) dimuat LAZILY: hanya saat
 *   ada file tipe itu yang terlihat di layar.
 * - Gagal render = ikon fallback di belakang canvas tetap terlihat.
 */
const MAX_WIDTH = 480;
const MAX_DPR = 2;
const MAX_PARSE_BYTES = 10 * 1024 * 1024; // file > 10MB tidak diparse
const MAX_TEXT_BYTES = 40000; // cukup untuk 1-2 halaman

// PDF.js (431KB) & fflate diambil saat dipakai, bukan saat halaman dibuka.
let pdfjsPromise = null;
let unzipPromise = null;

function loadPdfjs() {
    if (!pdfjsPromise) {
        pdfjsPromise = (async () => {
            const mod = await import('pdfjs-dist');
            mod.GlobalWorkerOptions.workerSrc = await pdfjsWorkerUrl();
            return mod;
        })();
    }
    return pdfjsPromise;
}

// Worker PDF.js: di build, file-nya disalin sebagai aset terpisah
// (lihat vite.config.js) supaya chunk PDF.js tetap bisa dimuat malas.
function pdfjsWorkerUrl() {
    if (import.meta.env.DEV) {
        return '/node_modules/pdfjs-dist/build/pdf.worker.min.mjs';
    }
    return `${import.meta.env.BASE_URL}pdf.worker.min.js`;
}

function loadUnzip() {
    if (!unzipPromise) {
        unzipPromise = import('fflate').then((m) => m.unzipSync);
    }
    return unzipPromise;
}

async function renderPdfThumb(canvas) {
    if (canvas.dataset.rendered || canvas.dataset.rendering) return;
    canvas.dataset.rendering = '1';

    // PDF.js v6 tidak lagi punya pdf.destroy(); pembersihan dilakukan lewat
    // loading task. Kalau salah objek yang di-destroy, render sukses pun
    // dianggap gagal dan kanvas ikut dibuang (pernah terjadi: thumbnail
    // PDF hilang semua padahal halamannya sudah tergambar).
    let task = null;

    try {
        const pdfjsLib = await loadPdfjs();
        task = pdfjsLib.getDocument({
            url: canvas.dataset.pdfUrl,
            withCredentials: true,
        });
        const pdf = await task.promise;
        const page = await pdf.getPage(1);
        const base = page.getViewport({ scale: 1 });

        // Isi kartu (bukan ukuran halaman) jadi kanvas: halaman pertama
        // diskalakan selebar kartu dan dipotong bagian bawahnya — mirip
        // thumbnail Drive, tidak gepeng seperti kalau halaman dipaksa
        // masuk ke kotak 16:6.
        const boxWidth = canvas.clientWidth || canvas.parentElement?.clientWidth || MAX_WIDTH;
        const boxHeight = canvas.clientHeight || canvas.parentElement?.clientHeight || 144;
        const scale = boxWidth / base.width;
        const viewport = page.getViewport({ scale });
        const dpr = Math.min(window.devicePixelRatio || 1, MAX_DPR);

        canvas.width = Math.floor(boxWidth * dpr);
        canvas.height = Math.floor(boxHeight * dpr);

        const ctx = canvas.getContext('2d');
        ctx.scale(dpr, dpr);
        ctx.fillStyle = '#ffffff';
        ctx.fillRect(0, 0, boxWidth, boxHeight);

        await page.render({ canvasContext: ctx, viewport }).promise;
        canvas.dataset.rendered = '1';
    } catch (e) {
        // Biarkan ikon fallback di belakang canvas yang terlihat.
        console.warn('Thumbnail PDF gagal dirender:', e);
        canvas.remove();
    } finally {
        if (task) {
            try {
                await task.destroy();
            } catch (e) {
                /* sudah tidak terpakai */
            }
        }
        delete canvas.dataset.rendering;
    }
}

/* ============================================================
   Pratinjau isi file di dalam layar (popup preview)
   ============================================================ */

const CONTENT_KINDS = ['txt', 'csv', 'docx', 'xlsx', 'pptx', 'zip'];

function previewShell() {
    const wrap = document.createElement('div');
    wrap.className = 'max-h-[70vh] w-full overflow-auto rounded-lg bg-slate-50 p-4 text-sm text-slate-700 dark:bg-slate-800 dark:text-slate-200';
    return wrap;
}

function buildTable(rows) {
    const table = document.createElement('table');
    table.className = 'w-full border-collapse text-left text-xs';
    rows.forEach((row, r) => {
        const tr = document.createElement('tr');
        row.forEach((cell) => {
            const td = document.createElement(r === 0 ? 'th' : 'td');
            td.className = 'border border-slate-200 px-2 py-1 dark:border-slate-700';
            td.textContent = cell;
            tr.appendChild(td);
        });
        table.appendChild(tr);
    });
    return table;
}

function appendParagraphs(wrap, lines) {
    if (!lines.length) {
        wrap.textContent = 'Tidak ada teks yang bisa ditampilkan.';
        return;
    }
    lines.forEach((line) => {
        const el = document.createElement('p');
        el.className = 'mb-2 leading-relaxed';
        el.textContent = line;
        wrap.appendChild(el);
    });
}

async function buildContentPreview(kind, url) {
    const wrap = previewShell();

    try {
        if (kind === 'txt') {
            const text = await fetchText(url);
            const pre = document.createElement('pre');
            pre.className = 'whitespace-pre-wrap break-words font-sans';
            pre.textContent = text || 'File kosong.';
            wrap.appendChild(pre);
        } else if (kind === 'csv') {
            const text = await fetchText(url);
            wrap.appendChild(buildTable(parseCsv(text)));
        } else {
            const bytes = new Uint8Array(await fetchBuffer(url));
            const unzip = await loadUnzip();

            if (kind === 'docx') {
                const files = unzip(bytes, { filter: (f) => f.name === 'word/document.xml' });
                appendParagraphs(wrap, docxParagraphs(files));
            } else if (kind === 'xlsx') {
                const files = unzip(bytes, {
                    filter: (f) => /^xl\/(sharedStrings\.xml|worksheets\/)/i.test(f.name),
                });
                wrap.appendChild(buildTable(xlsxRows(files)));
            } else if (kind === 'pptx') {
                const files = unzip(bytes, { filter: (f) => /^ppt\/slides\/slide1\.xml$/i.test(f.name) });
                appendParagraphs(wrap, pptxTexts(files));
            } else if (kind === 'zip') {
                const list = document.createElement('ul');
                list.className = 'list-disc pl-5';
                zipNames(unzip, bytes).forEach((name) => {
                    const li = document.createElement('li');
                    li.textContent = name;
                    list.appendChild(li);
                });
                wrap.appendChild(list);
            }
        }
    } catch (e) {
        wrap.textContent = 'Pratinjau tidak tersedia. Silakan unduh file.';
    }

    return wrap;
}

function initDocThumbs() {
    const canvases = document.querySelectorAll(
        'canvas[data-pdf-url]:not([data-rendered]), canvas[data-doc-render]:not([data-rendered])'
    );
    if (!canvases.length || !('IntersectionObserver' in window)) {
        canvases.forEach((c) => renderThumb(c));
        return;
    }

    const io = new IntersectionObserver(
        (entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    io.unobserve(entry.target);
                    renderThumb(entry.target);
                }
            });
        },
        { rootMargin: '200px' }
    );
    canvases.forEach((c) => io.observe(c));
}

function renderThumb(canvas) {
    if (canvas.hasAttribute('data-pdf-url')) return renderPdfThumb(canvas);
    return renderDocThumb(canvas);
}

/* ============================================================
   Thumbnail dari isi file (TXT/CSV/DOCX/XLSX/PPTX/ZIP)
   ============================================================ */

function accentRgb() {
    const raw = getComputedStyle(document.documentElement).getPropertyValue('--accent-600').trim();
    return raw ? `rgb(${raw})` : 'rgb(79 70 229)';
}

function prepareCanvas(canvas) {
    const w = canvas.clientWidth || 240;
    const h = canvas.clientHeight || 144;
    const dpr = Math.min(window.devicePixelRatio || 1, MAX_DPR);
    canvas.width = Math.max(1, Math.floor(w * dpr));
    canvas.height = Math.max(1, Math.floor(h * dpr));
    const ctx = canvas.getContext('2d');
    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    ctx.clearRect(0, 0, w, h);
    return { ctx, w, h };
}

function wrapText(ctx, text, maxWidth) {
    const out = [];
    String(text)
        .split('\n')
        .forEach((paragraph) => {
            const words = paragraph.split(/\s+/).filter(Boolean);
            if (!words.length) {
                out.push('');
                return;
            }
            let line = '';
            words.forEach((word) => {
                const test = line ? line + ' ' + word : word;
                if (ctx.measureText(test).width > maxWidth && line) {
                    out.push(line);
                    line = word;
                } else {
                    line = test;
                }
            });
            if (line) out.push(line);
        });
    return out;
}

// Tampilan "halaman kertas" — dipakai untuk txt, docx, pptx.
function drawTextPage(ctx, w, h, lines, opts = {}) {
    ctx.fillStyle = '#ffffff';
    ctx.fillRect(0, 0, w, h);

    const pad = opts.big ? 18 : 12;
    const lineHeight = opts.big ? 20 : 14;
    ctx.fillStyle = '#0f172a';
    ctx.font = (opts.big ? '600 15px ui-sans-serif, system-ui' : '13px ui-sans-serif, system-ui');
    ctx.textBaseline = 'top';

    let y = pad;
    for (const line of wrapText(ctx, lines.join('\n'), w - pad * 2)) {
        if (y + lineHeight > h - 6) break;
        ctx.fillText(line, pad, y);
        y += lineHeight;
    }

    // Aksen tipis di sisi atas supaya terasa "dokumen".
    ctx.fillStyle = accentRgb();
    ctx.fillRect(0, 0, w, 3);
}

// Tampilan tabel — dipakai untuk csv & xlsx.
function drawTable(ctx, w, h, rows) {
    ctx.fillStyle = '#ffffff';
    ctx.fillRect(0, 0, w, h);
    if (!rows.length) return;

    const cols = Math.min(4, Math.max(...rows.map((r) => r.length)));
    const rowH = Math.max(18, Math.min(28, Math.floor((h - 8) / Math.max(3, rows.length))));
    const colW = (w - 8) / cols;

    ctx.font = '11px ui-sans-serif, system-ui';
    ctx.textBaseline = 'middle';

    rows.slice(0, Math.floor((h - 8) / rowH)).forEach((row, r) => {
        const y = 4 + r * rowH;
        ctx.fillStyle = r === 0 ? accentRgb() : (r % 2 ? '#f8fafc' : '#ffffff');
        ctx.fillRect(4, y, w - 8, rowH);
        ctx.strokeStyle = '#e2e8f0';
        ctx.lineWidth = 1;
        ctx.strokeRect(4.5, y + 0.5, w - 9, rowH - 1);
        for (let c = 0; c < cols; c += 1) {
            ctx.strokeRect(4.5 + c * colW, y + 0.5, colW, rowH - 1);
            const value = String(row[c] ?? '');
            if (!value) continue;
            ctx.fillStyle = r === 0 ? '#ffffff' : '#334155';
            ctx.save();
            ctx.beginPath();
            ctx.rect(4 + c * colW + 4, y, colW - 8, rowH);
            ctx.clip();
            ctx.fillText(value, 4 + c * colW + 6, y + rowH / 2);
            ctx.restore();
        }
    });
}

// Tampilan daftar isi — dipakai untuk zip.
function drawList(ctx, w, h, items) {
    ctx.fillStyle = '#f8fafc';
    ctx.fillRect(0, 0, w, h);
    ctx.fillStyle = accentRgb();
    ctx.fillRect(0, 0, w, 3);

    ctx.font = '11px ui-monospace, SFMono-Regular, Menlo, monospace';
    ctx.textBaseline = 'top';
    ctx.fillStyle = '#475569';

    let y = 12;
    for (const item of items) {
        if (y + 14 > h - 4) break;
        ctx.fillText('• ' + item, 10, y);
        y += 14;
    }
}

async function fetchText(url) {
    const res = await fetch(url, { credentials: 'same-origin' });
    if (!res.ok) throw new Error('gagal memuat file');
    const text = await res.text();
    return text.slice(0, MAX_TEXT_BYTES);
}

async function fetchBuffer(url) {
    const res = await fetch(url, { credentials: 'same-origin' });
    if (!res.ok) throw new Error('gagal memuat file');
    const buf = await res.arrayBuffer();
    if (buf.byteLength > MAX_PARSE_BYTES) throw new Error('file terlalu besar');
    return buf;
}

export function parseCsv(text) {
    return text
        .split(/\r?\n/)
        .filter((line) => line.trim() !== '')
        .slice(0, 8)
        .map((line) => {
            const cells = [];
            let current = '';
            let inQuotes = false;
            for (const ch of line) {
                if (ch === '"') {
                    inQuotes = !inQuotes;
                } else if (ch === ',' && !inQuotes) {
                    cells.push(current.trim());
                    current = '';
                } else {
                    current += ch;
                }
            }
            cells.push(current.trim());
            return cells;
        });
}

// Ambil daftar nama file di dalam ZIP tanpa decompress isinya.
export function zipNames(unzip, bytes) {
    const names = [];
    unzip(bytes, {
        filter: (file) => {
            names.push(file.name);
            return false;
        },
    });
    return names.filter((n) => !n.endsWith('/'));
}

// --- Parser XML ringan (regex) ---------------------------------------------
// File Word/Excel/PPT selalu dibuat program, jadi strukturnya rapi dan
// aman dibaca dengan regex. Lebih ringan daripada DOMParser dan bisa
// diuji otomatis (lihat tests/js/doc-thumbs.test.js).
function decodeXml(text) {
    return String(text)
        .replace(/&lt;/g, '<')
        .replace(/&gt;/g, '>')
        .replace(/&quot;/g, '"')
        .replace(/&apos;/g, "'")
        .replace(/&#(\d+);/g, (_, d) => String.fromCharCode(Number(d)))
        .replace(/&#x([0-9a-f]+);/gi, (_, h) => String.fromCharCode(parseInt(h, 16)))
        .replace(/&amp;/g, '&');
}

function xmlTexts(xml, tag) {
    const out = [];
    const re = new RegExp(`<${tag}(?:\\s[^>]*)?>([\\s\\S]*?)</${tag}>`, 'g');
    let match;
    while ((match = re.exec(xml)) !== null) {
        out.push(decodeXml(match[1]));
    }
    return out;
}

export function docxParagraphs(files) {
    const entry = files['word/document.xml'];
    if (!entry) return [];
    const xml = new TextDecoder().decode(entry);
    const out = [];
    for (const chunk of xml.split(/<\/w:p>/)) {
        const text = xmlTexts(chunk, 'w:t').join('').trim();
        if (text) out.push(text);
        if (out.length >= 20) break;
    }
    return out;
}

export function xlsxRows(files) {
    const shared = [];
    if (files['xl/sharedStrings.xml']) {
        const xml = new TextDecoder().decode(files['xl/sharedStrings.xml']);
        for (const chunk of xml.split(/<\/si>/)) {
            const text = xmlTexts(chunk, 't').join('').trim();
            if (text) shared.push(text);
        }
    }
    const sheetKey = Object.keys(files).find((k) => /^xl\/worksheets\/sheet1\.xml$/i.test(k))
        || Object.keys(files).find((k) => /^xl\/worksheets\//i.test(k));
    if (!sheetKey) return [];

    const xml = new TextDecoder().decode(files[sheetKey]);
    const rows = [];
    for (const chunk of xml.split(/<row[\s>]/).slice(1)) {
        const cells = [];
        const cellRe = /<c\b([^>]*?)(?:\/>|>([\s\S]*?)<\/c>)/g;
        let match;
        while ((match = cellRe.exec(chunk)) !== null) {
            const attrs = match[1] || '';
            const inner = match[2] || '';
            const type = (attrs.match(/t="([^"]+)"/) || [])[1];
            let value = '';
            if (type === 's') {
                const v = (inner.match(/<v>([\s\S]*?)<\/v>/) || [])[1];
                const index = v ? parseInt(v, 10) : -1;
                value = shared[index] ?? '';
            } else if (type === 'inlineStr') {
                value = xmlTexts(inner, 't').join('');
            } else {
                const v = (inner.match(/<v>([\s\S]*?)<\/v>/) || [])[1];
                value = v ? decodeXml(v) : '';
            }
            cells.push(value);
        }
        if (cells.length) rows.push(cells);
        if (rows.length >= 8) break;
    }
    return rows;
}

export function pptxTexts(files) {
    const key = Object.keys(files).find((k) => /^ppt\/slides\/slide1\.xml$/i.test(k));
    if (!key) return [];
    const xml = new TextDecoder().decode(files[key]);
    return xmlTexts(xml, 'a:t').map((t) => t.trim()).filter(Boolean).slice(0, 12);
}

async function renderDocThumb(canvas) {
    if (canvas.dataset.rendered || canvas.dataset.rendering) return;
    canvas.dataset.rendering = '1';

    const kind = canvas.dataset.docRender;
    const url = canvas.dataset.url;

    try {
        const { ctx, w, h } = prepareCanvas(canvas);

        if (kind === 'txt') {
            const text = await fetchText(url);
            drawTextPage(ctx, w, h, text.split('\n').filter((l) => l.trim() !== ''));
        } else if (kind === 'csv') {
            const text = await fetchText(url);
            drawTable(ctx, w, h, parseCsv(text));
        } else {
            const bytes = new Uint8Array(await fetchBuffer(url));
            const unzip = await loadUnzip();

            if (kind === 'docx') {
                const files = unzip(bytes, { filter: (f) => f.name === 'word/document.xml' });
                drawTextPage(ctx, w, h, docxParagraphs(files));
            } else if (kind === 'xlsx') {
                const files = unzip(bytes, {
                    filter: (f) => /^xl\/(sharedStrings\.xml|worksheets\/)/i.test(f.name),
                });
                drawTable(ctx, w, h, xlsxRows(files));
            } else if (kind === 'pptx') {
                const files = unzip(bytes, { filter: (f) => /^ppt\/slides\/slide1\.xml$/i.test(f.name) });
                drawTextPage(ctx, w, h, pptxTexts(files), { big: true });
            } else if (kind === 'zip') {
                drawList(ctx, w, h, zipNames(unzip, bytes).slice(0, 8));
            }
        }

        canvas.dataset.rendered = '1';
    } catch (e) {
        // Biarkan ikon fallback di belakang canvas yang terlihat.
        canvas.remove();
    } finally {
        delete canvas.dataset.rendering;
    }
}

function initDocPage() {
    initDocModals();
    initDocFilter();
    initDocMenus();
    initDocLightbox();
    initDocRename();
    initDocUploadGuard();
}

// Pasang listener sekali saja per elemen (aman dipanggil ulang tiap navigasi SPA).
function bindOnce(el, event, handler) {
    if (!el) return;
    const flag = `docBound${event}`;
    if (el.dataset[flag]) return;
    el.dataset[flag] = '1';
    el.addEventListener(event, handler);
}

function showModal(el) {
    if (!el) return;
    el.classList.remove('hidden');
    el.classList.add('doc-open');
    document.body.style.overflow = 'hidden';
    syncModalOpenClass();
}

function hideModal(el) {
    if (!el) return;
    el.classList.add('hidden');
    el.classList.remove('doc-open');
    document.body.style.overflow = '';
    syncModalOpenClass();
}

function hideAllDocModals() {
    document.querySelectorAll('[data-doc-modal]').forEach(hideModal);
    syncModalOpenClass();
}

// Sinkron penanda di <html>: ada popup dokumen terbuka atau tidak.
// CSS mematikan hover-lift kartu selama popup terbuka — transform pada
// ancestor merusak position:fixed popup (terkunci ke kartu, ikut
// berpindah tiap hover) dan membuat halaman di belakang popup naik-turun.
function syncModalOpenClass() {
    const anyOpen = Array.prototype.some.call(
        document.querySelectorAll('[data-doc-modal]'),
        (el) => el.classList.contains('doc-open')
    );
    document.documentElement.classList.toggle('doc-modal-open', anyOpen);
}

// Popup upload + tutup via tombol/backdrop/Escape.
function initDocModals() {
    // Sinkron dulu: popup bisa sudah terbuka dari render server
    // (error validasi menambah class doc-open) tanpa lewat showModal().
    syncModalOpenClass();
    document.querySelectorAll('[data-doc-upload-open]').forEach((btn) => {
        bindOnce(btn, 'click', () => {
            // Elemen popup diambil saat diklik — aman setelah navigasi SPA.
            const typeInput = document.getElementById('doc-doc-type');
            const targetEl = document.getElementById('doc-upload-target');
            const projectField = document.getElementById('doc-project-field');

            // Tombol dari bagian Invoice/PO/Payment membawa jenisnya sendiri.
            const type = btn.dataset.docType || '';
            if (typeInput) typeInput.value = type;
            if (targetEl) {
                const section = btn.dataset.docSection || '';
                targetEl.textContent = section;
                targetEl.classList.toggle('hidden', !section);
            }
            // File jenis ini melekat di customer, jadi pilihan project disembunyikan.
            if (projectField) {
                projectField.classList.toggle('hidden', !!type);
                const select = projectField.querySelector('select');
                if (select && type) select.value = '';
            }
            showModal(document.getElementById('doc-upload-modal'));
        });
    });
    document.querySelectorAll('[data-doc-modal-close]').forEach((btn) => {
        bindOnce(btn, 'click', () => hideModal(btn.closest('[data-doc-modal]')));
    });
    document.querySelectorAll('[data-doc-modal]').forEach((modal) => {
        bindOnce(modal, 'click', (e) => {
            if (e.target === modal) hideModal(modal);
        });
    });
    if (!window.__docEscBound) {
        window.__docEscBound = true;
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') hideAllDocModals();
        });
    }
}

// Saring kartu: nama + project, tanpa reload.
function initDocFilter() {
    const search = document.getElementById('doc-search');
    const projectFilter = document.getElementById('doc-project-filter');
    const grid = document.getElementById('doc-grid');
    if (!grid || (!search && !projectFilter)) return;

    const apply = () => {
        const q = (search?.value || '').trim().toLowerCase();
        const proj = projectFilter?.value || '';
        let visible = 0;
        grid.querySelectorAll('.doc-card').forEach((card) => {
            const matchName = !q || (card.dataset.name || '').includes(q);
            const matchProj = !proj || (card.dataset.project || '') === proj;
            const show = matchName && matchProj;
            card.classList.toggle('hidden', !show);
            if (show) visible += 1;
        });
        document.getElementById('doc-no-results')?.classList.toggle('hidden', visible > 0);
    };

    bindOnce(search, 'input', apply);
    bindOnce(projectFilter, 'change', apply);
}

// Menu aksi kartu dokumen (tombol titik tiga).
function closeAllDocMenus() {
    document.querySelectorAll('[data-doc-menu-panel].doc-open').forEach((panel) => {
        panel.classList.remove('doc-open');
        panel.closest('.doc-card')?.classList.remove('z-20');
    });
}

function initDocMenus() {
    document.querySelectorAll('[data-doc-menu-toggle]').forEach((btn) => {
        bindOnce(btn, 'click', (e) => {
            e.stopPropagation();
            const wrap = btn.closest('[data-doc-menu]');
            const panel = wrap?.querySelector('[data-doc-menu-panel]');
            if (!panel) return;
            const willOpen = !panel.classList.contains('doc-open');
            closeAllDocMenus();
            if (willOpen) {
                panel.classList.add('doc-open');
                // Kartu yang sedang punya menu harus di atas kartu lain,
                // supaya panelnya tidak tertutupi kartu di bawahnya.
                wrap.closest('.doc-card')?.classList.add('z-20');
            }
        });
    });

    if (!window.__docMenuOutsideBound) {
        window.__docMenuOutsideBound = true;
        document.addEventListener('click', (e) => {
            if (!e.target.closest('[data-doc-menu]')) closeAllDocMenus();
        });
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') closeAllDocMenus();
        });
    }
}

// Tampilan file dalam layar.
function initDocLightbox() {
    const lightbox = document.getElementById('doc-lightbox');
    const body = document.getElementById('doc-lightbox-body');
    const nameEl = document.getElementById('doc-lightbox-name');
    const downloadEl = document.getElementById('doc-lightbox-download');
    if (!lightbox || !body) return;

    const esc = (s) => {
        const div = document.createElement('div');
        div.textContent = s ?? '';
        return div.innerHTML;
    };

    const openFrom = async (trigger) => {
        closeAllDocMenus();
        const kind = trigger.dataset.kind || 'other';
        const url = trigger.dataset.url || '';
        const filename = trigger.dataset.filename || '';
        const download = trigger.dataset.download || url;

        nameEl.textContent = filename;
        downloadEl.setAttribute('href', download);
        body.innerHTML = '';

        if (kind === 'image') {
            const img = document.createElement('img');
            img.src = url;
            img.alt = filename;
            img.className = 'max-h-[70vh] max-w-full rounded-lg object-contain';
            body.appendChild(img);
        } else if (kind === 'pdf') {
            const frame = document.createElement('iframe');
            frame.src = url;
            frame.title = filename;
            frame.className = 'h-[70vh] w-full rounded-lg bg-white';
            body.appendChild(frame);
        } else if (kind === 'video') {
            const video = document.createElement('video');
            video.controls = true;
            video.src = url;
            video.className = 'max-h-[70vh] w-full max-w-3xl rounded-lg bg-black';
            body.appendChild(video);
        } else if (kind === 'audio') {
            const audio = document.createElement('audio');
            audio.controls = true;
            audio.src = url;
            audio.className = 'w-full max-w-xl';
            body.appendChild(audio);
        } else if (CONTENT_KINDS.includes(kind)) {
            // Isi file (teks/tabel) ditampilkan langsung di layar.
            body.appendChild(await buildContentPreview(kind, url, filename));
        } else {
            const wrap = document.createElement('div');
            wrap.className = 'py-10 text-center';
            wrap.innerHTML =
                '<svg class="mx-auto mb-3 h-16 w-16 text-slate-300" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">'
                + '<path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>'
                + '<p class="mb-1 text-sm font-semibold text-slate-700">' + esc(filename) + '</p>'
                + '<p class="mb-4 text-xs text-slate-400">Preview tidak tersedia — unduh untuk membuka file.</p>';
            const dl = document.createElement('a');
            dl.href = download;
            dl.className = 'inline-block rounded-xl bg-emerald-600 px-6 py-2.5 text-sm font-medium text-white transition hover:bg-emerald-700';
            dl.textContent = 'Download';
            wrap.appendChild(dl);
            body.appendChild(wrap);
        }

        showModal(lightbox);
    };

    document.querySelectorAll('[data-doc-open]').forEach((thumb) => {
        bindOnce(thumb, 'click', () => openFrom(thumb));
    });
    document.querySelectorAll('[data-doc-open-child]').forEach((btn) => {
        bindOnce(btn, 'click', (e) => {
            e.stopPropagation();
            const card = btn.closest('.doc-card');
            const thumb = card?.querySelector('[data-doc-open]');
            if (thumb) openFrom(thumb);
        });
    });
}

// Popup ubah nama: isi aksi + nama file saat ini.
function initDocRename() {
    const modal = document.getElementById('doc-rename-modal');
    const form = document.getElementById('doc-rename-form');
    const input = document.getElementById('doc-rename-input');
    if (!modal || !form || !input) return;

    document.querySelectorAll('[data-doc-rename]').forEach((btn) => {
        bindOnce(btn, 'click', () => {
            closeAllDocMenus();
            form.setAttribute('action', btn.dataset.action || '#');
            input.value = btn.dataset.name || '';
            showModal(modal);
            setTimeout(() => input.focus(), 50);
        });
    });
}

// Cegah kirim ganda: kunci tombol saat form upload dikirim.
function initDocUploadGuard() {
    document.querySelectorAll('form[action*="/customers/"][action$="/documents"]').forEach((form) => {
        if (form.dataset.docGuardBound) return;
        form.dataset.docGuardBound = '1';
        form.addEventListener('submit', () => {
            const btn = form.querySelector('[data-doc-upload-submit]');
            if (btn && !btn.disabled) {
                btn.disabled = true;
                btn.classList.add('opacity-70', 'cursor-wait');
            }
        });
    });
}

function initDocPageSafe() {
    try {
        initDocThumbs();
        initDocPage();
    } catch (e) {
        console.error('doc page init gagal', e);
    }
}

// Guard `document` supaya modul ini bisa di-import di Node (unit test).
if (typeof document !== 'undefined') {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initDocPageSafe);
    } else {
        initDocPageSafe();
    }

    // Halaman ini dinavigasi ala SPA (Livewire) — pasang ulang listener
    // tiap konten halaman baru selesai dimuat.
    ['livewire:navigated', 'turbo:load', 'popstate'].forEach((evt) => {
        document.addEventListener(evt, initDocPageSafe);
    });
}
