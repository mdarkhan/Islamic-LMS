import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
            // Self-hosted at build time (no runtime CDN). Bengali-first, with a
            // Latin companion. Fonts are downloaded and served from the build output.
            fonts: [
                bunny('Noto Sans Bengali', { weights: [400, 500, 600, 700] }),
                bunny('Hind Siliguri', { weights: [400, 500, 600, 700] }),
                bunny('Inter', { weights: [400, 500, 600, 700] }),
            ],
        }),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
