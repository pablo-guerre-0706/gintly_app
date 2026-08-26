import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import { resolve } from 'node:path';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
            ],
            refresh: true,
        }),

        tailwindcss(),
    ],

    resolve: {
        alias: {
            '@': resolve(process.cwd(), 'resources/js'),
        },
    },

    build: {
        target: 'es2020',
        cssCodeSplit: true,
        sourcemap: false,
    },
});
