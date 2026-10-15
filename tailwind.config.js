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
        './app/**/*.php',
    ],

    theme: {
        extend: {
            colors: {
                blue: cssColorScale('blue'),
                indigo: cssColorScale('indigo'),
                accent: cssColorScale('accent', ''),
            },
            fontFamily: {
                sans: ['Plus Jakarta Sans', 'PJSFallback', ...defaultTheme.fontFamily.sans],
                display: ['Exo 2', 'Exo2Fallback', 'Plus Jakarta Sans', ...defaultTheme.fontFamily.sans],
            },
        },
    },

    plugins: [forms],
};
