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
