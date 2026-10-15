import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';

export default defineConfig({
    plugins: [
        react(),
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js', 'resources/js/teknisi-calendar.js', 'resources/js/pipeline-dnd.js', 'resources/js/rubber-segment.jsx', 'resources/js/glide-select.jsx'],
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
