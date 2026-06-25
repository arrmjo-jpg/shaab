import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';
import { cpSync, existsSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { dirname, resolve } from 'node:path';

const projectRoot = dirname(fileURLToPath(import.meta.url));

// Copy pdf.js cMaps + standard fonts into the (gitignored) build output so the reader
// renders CID-keyed / non-embedded fonts (Arabic hardening). Runs on build — the app
// serves built assets; reader.js references /build/pdfjs/{cmaps,standard_fonts}/.
function copyPdfjsAssets() {
    return {
        name: 'copy-pdfjs-assets',
        apply: 'build',
        closeBundle() {
            const pairs = [
                ['node_modules/pdfjs-dist/cmaps', 'public/build/pdfjs/cmaps'],
                ['node_modules/pdfjs-dist/standard_fonts', 'public/build/pdfjs/standard_fonts'],
            ];
            for (const [from, to] of pairs) {
                const src = resolve(projectRoot, from);
                if (existsSync(src)) cpSync(src, resolve(projectRoot, to), { recursive: true });
            }
        },
    };
}

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js', 'resources/js/broadcast.js', 'resources/js/epaper.js', 'resources/js/ads.js', 'resources/js/polls.js'],
            refresh: true,
            fonts: [
                bunny('Instrument Sans', {
                    weights: [400, 500, 600],
                }),
            ],
        }),
        tailwindcss(),
        copyPdfjsAssets(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
