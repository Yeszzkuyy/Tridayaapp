import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

const cssColorScale = (name, prefix = 'theme-') => Object.fromEntries(
    [50, 100, 200, 300, 400, 500, 600, 700, 800, 900, 950].map((shade) => [
        shade,
        `rgb(var(--${prefix}${name}-${shade}) / <alpha-value>)`,
    ]),
);

export default {
    darkMode: 'class',
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        // Kelas Tailwind yang dipakai island React (notification center, dsb.)
        // wajib ikut discan — tanpa ini kelasnya tidak ter-compile ke app.css.
        './resources/js/**/*.{js,jsx}',
        './app/**/*.php',
    ],

    theme: {
        extend: {
            colors: {
                blue: cssColorScale('blue'),
                indigo: cssColorScale('indigo'),
                accent: cssColorScale('accent', ''),
                content: {
                    primary: 'var(--text-primary)',
                    secondary: 'var(--text-secondary)',
                    tertiary: 'var(--text-tertiary)',
                },
                surface: {
                    DEFAULT: 'var(--surface)',
                    elevated: 'var(--surface-elevated)',
                },
                line: 'var(--border)',
                success: 'var(--success)',
                warning: 'var(--warning)',
                danger: 'var(--danger)',
                info: 'var(--info)',
            },
            fontSize: {
                xs: ['12px', { lineHeight: '1.35' }],
                sm: ['13px', { lineHeight: '1.45' }],
                base: ['14px', { lineHeight: '1.5' }],
                lg: ['16px', { lineHeight: '1.4' }],
                xl: ['18px', { lineHeight: '1.3' }],
                '2xl': ['22px', { lineHeight: '1.25' }],
                '3xl': ['27px', { lineHeight: '1.15' }],
                '4xl': ['32px', { lineHeight: '1.1' }],
                '5xl': ['40px', { lineHeight: '1' }],
            },
            fontFamily: {
                sans: ['Plus Jakarta Sans', 'PJSFallback', ...defaultTheme.fontFamily.sans],
                display: ['Exo 2', 'Exo2Fallback', 'Plus Jakarta Sans', ...defaultTheme.fontFamily.sans],
                outfit: ['Outfit', 'Plus Jakarta Sans', ...defaultTheme.fontFamily.sans],
                urbanist: ['Urbanist', 'Plus Jakarta Sans', ...defaultTheme.fontFamily.sans],
            },
        },
    },

    plugins: [forms],
};
