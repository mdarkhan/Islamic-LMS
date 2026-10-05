import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
        tailwindcss(),
    ],
    build: {
        // Kalpurush (resources/fonts/kalpurush.woff2, referenced by url() in app.css) is
        // force-inlined as a base64 data: URI regardless of Vite's default 4 KiB
        // assetsInlineLimit. This app has no client-side routing, so every navigation
        // is a fresh document; a self-hosted font served as its own file is always a
        // second async request racing first paint, which shows the wrong font for a
        // moment no matter how HTTP caching or font-display are tuned. Inlining it
        // into the render-blocking stylesheet removes that request entirely. See the
        // font-face comment in app.css and CLAUDE.md for the full history.
        assetsInlineLimit: (filePath) => filePath.endsWith('kalpurush.woff2') ? true : undefined,
    },
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
