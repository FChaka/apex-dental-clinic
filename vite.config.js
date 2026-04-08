import react from '@vitejs/plugin-react';
import laravel from 'laravel-vite-plugin';
import {
    defineConfig
} from 'vite';
import tailwindcss from "@tailwindcss/vite";

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.tsx'],
            ssr: 'resources/js/ssr.jsx',
            refresh: true,
        }),
        react(),
        tailwindcss(),
    ],
    esbuild: {
        jsx: 'automatic',
    },
    server: {
        host: true,
        proxy: {
            // Same-origin API during Vite dev: cookies + CSRF work without cross-host XSRF issues. Preserves Host for tenant subdomains.
            '^/(api|sanctum)': {
                target: 'http://127.0.0.1:8000',
                changeOrigin: false,
            },
        },
    },
});