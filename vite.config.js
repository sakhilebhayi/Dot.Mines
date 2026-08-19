import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js', 'resources/js/live-map.js'],
            refresh: true,
        }),
        tailwindcss(),
    ],
    build: {
        rollupOptions: {
            output: {
                // Function form: Vite 8's rolldown bundler does not accept
                // the object form ("manualChunks is not a function").
                manualChunks(id) {
                    if (id.includes('node_modules/alpinejs/')) {
                        return 'vendor';
                    }
                    if (id.includes('node_modules/chart.js/')) {
                        return 'charts';
                    }
                    if (id.includes('node_modules/leaflet/')) {
                        return 'maps';
                    }
                },
            },
        },
        chunkSizeWarningLimit: 1000,
        minify: 'terser',
        terserOptions: {
            compress: {
                drop_console: true,
                drop_debugger: true,
            },
        },
    },
    server: {
        hmr: {
            host: 'localhost',
        },
    },
});
