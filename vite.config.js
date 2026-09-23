import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { local } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
            // Self-hosted at build time (no runtime CDN). Kalpurush isn't on Google/
            // Bunny Fonts or Fontsource, so it's checked in locally (from omicronlab.com,
            // its original distributor) rather than fetched remotely at build time.
            // `fontaine` (a devDependency) generates the metric-matched "kalpurush
            // Fallback" companion family used below, so text doesn't reflow once the
            // real font finishes loading.
            fonts: [
                local('kalpurush', { src: 'resources/fonts/kalpurush.ttf' }),
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
