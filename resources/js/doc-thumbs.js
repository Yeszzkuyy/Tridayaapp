/**
 * Thumbnail dokumen ala Drive untuk kartu dokumen customer.
 *
 * - <img> menangani file gambar langsung (URL preview privat, same-origin).
 * - <canvas data-pdf-url="..."> digambar halaman pertamanya via PDF.js,
 *   dimuat malas saat kartu terlihat. Gagal render = ikon fallback di
 *   belakang canvas tetap terlihat (canvas transparan).
 */
import * as pdfjsLib from 'pdfjs-dist';
import workerUrl from 'pdfjs-dist/build/pdf.worker.min.mjs?url';

pdfjsLib.GlobalWorkerOptions.workerSrc = workerUrl;

const MAX_WIDTH = 480;
const MAX_DPR = 2;

async function renderPdfThumb(canvas) {
    if (canvas.dataset.rendered || canvas.dataset.rendering) return;
    canvas.dataset.rendering = '1';

    try {
        const pdf = await pdfjsLib.getDocument({
            url: canvas.dataset.pdfUrl,
            withCredentials: true,
        }).promise;
        const page = await pdf.getPage(1);
        const boxWidth = canvas.clientWidth || MAX_WIDTH;
        const scale = Math.min(boxWidth / page.getViewport({ scale: 1 }).width, 2);
        const viewport = page.getViewport({ scale });
        const dpr = Math.min(window.devicePixelRatio || 1, MAX_DPR);

        canvas.width = Math.floor(viewport.width * dpr);
        canvas.height = Math.floor(viewport.height * dpr);

        const ctx = canvas.getContext('2d');
        ctx.scale(dpr, dpr);
        ctx.fillStyle = '#ffffff';
        ctx.fillRect(0, 0, viewport.width, viewport.height);

        await page.render({ canvasContext: ctx, viewport }).promise;
        await pdf.destroy();
        canvas.dataset.rendered = '1';
    } catch (e) {
        // Biarkan ikon fallback di belakang canvas yang terlihat.
        canvas.remove();
    } finally {
        delete canvas.dataset.rendering;
    }
}

function initDocThumbs() {
    const canvases = document.querySelectorAll('canvas[data-pdf-url]:not([data-rendered])');
    if (!canvases.length || !('IntersectionObserver' in window)) {
        canvases.forEach((c) => renderPdfThumb(c));
        return;
    }

    const io = new IntersectionObserver(
        (entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    io.unobserve(entry.target);
                    renderPdfThumb(entry.target);
                }
            });
        },
        { rootMargin: '200px' }
    );
    canvases.forEach((c) => io.observe(c));
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initDocThumbs);
} else {
    initDocThumbs();
}

function initDocPage() {
    initDocModals();
    initDocFilter();
    initDocLightbox();
    initDocRename();
    initDocUploadGuard();
}

function showModal(el) {
    if (!el) return;
    el.classList.remove('hidden');
    el.classList.add('doc-open');
    document.body.style.overflow = 'hidden';
}

function hideModal(el) {
    if (!el) return;
    el.classList.add('hidden');
    el.classList.remove('doc-open');
    document.body.style.overflow = '';
}

function hideAllDocModals() {
    document.querySelectorAll('[data-doc-modal]').forEach(hideModal);
}

// Popup upload + tutup via tombol/backdrop/Escape.
function initDocModals() {
    const typeInput = document.getElementById('doc-doc-type');
    const targetEl = document.getElementById('doc-upload-target');
    const projectField = document.getElementById('doc-project-field');

    document.querySelectorAll('[data-doc-upload-open]').forEach((btn) => {
        btn.addEventListener('click', () => {
            // Tombol dari bagian Invoice/PO/Payment membawa jenisnya sendiri.
            const type = btn.dataset.docType || '';
            if (typeInput) typeInput.value = type;
            if (targetEl) {
                const section = btn.dataset.docSection || '';
                targetEl.textContent = section ? section : '';
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
        btn.addEventListener('click', () => hideModal(btn.closest('[data-doc-modal]')));
    });
    document.querySelectorAll('[data-doc-modal]').forEach((modal) => {
        modal.addEventListener('click', (e) => {
            if (e.target === modal) hideModal(modal);
        });
    });
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') hideAllDocModals();
    });
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

    search?.addEventListener('input', apply);
    projectFilter?.addEventListener('change', apply);
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

    const openFrom = (trigger) => {
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
        thumb.addEventListener('click', () => openFrom(thumb));
    });
    document.querySelectorAll('[data-doc-open-child]').forEach((btn) => {
        btn.addEventListener('click', (e) => {
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
        btn.addEventListener('click', () => {
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

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initDocPage);
} else {
    initDocPage();
}
