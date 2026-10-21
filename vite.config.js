import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';
import fs from 'node:fs';
import path from 'node:path';

// Worker PDF.js disalin sendiri sebagai aset (bukan di-import dari modul).
// Kalau worker di-import, Vite menyatukannya ke chunk pdfjs dan chunk itu
// ikut terunduh saat halaman dibuka — padahal PDF.js baru dipakai saat ada
// file PDF yang terlihat. Dengan aset terpisah, PDF.js bisa dimuat malas.
const pdfjsWorkerAsset = {
    name: 'pdfjs-worker-asset',
    apply: 'build',
    generateBundle() {
        const worker = path.resolve(process.cwd(), 'node_modules/pdfjs-dist/build/pdf.worker.min.mjs');
        if (!fs.existsSync(worker)) return;
        this.emitFile({
            type: 'asset',
            fileName: 'pdf.worker.min.mjs',
            source: fs.readFileSync(worker),
        });
    },
};

export default defineConfig({
    plugins: [
        react(),
        pdfjsWorkerAsset,
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js', 'resources/js/doc-thumbs.js', 'resources/js/teknisi-calendar.js', 'resources/js/pipeline-dnd.js', 'resources/js/rubber-segment.jsx', 'resources/js/glide-select.jsx', 'resources/js/notification-center.jsx', 'resources/js/submit-loader.js'],
            refresh: true,
        }),
    ],
    build: {
        // minify default (oxc) — sudah minify + tree-shake tanpa paket tambahan
        cssMinify: true,
        cssCodeSplit: true,
        reportCompressedSize: true,
        chunkSizeWarningLimit: 500,
        rollupOptions: {
            output: {
                codeSplitting: {
                    groups: [
                        { name: 'alpine', test: /node_modules[\\/]alpinejs/ },
                        { name: 'charts', test: /node_modules[\\/]apexcharts/ },
                        { name: 'markdown', test: /node_modules[\\/](marked|dompurify)/ },
                        { name: 'dnd', test: /node_modules[\\/]sortablejs/ },
                        { name: 'calendar', test: /node_modules[\\/]@fullcalendar/ },
                        { name: 'react', test: /node_modules[\\/](react|react-dom|motion|framer-motion)/ },
                    ],
                },
            },
        },
    },
});
