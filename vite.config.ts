import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';
import laravel from 'laravel-vite-plugin';
import { fileURLToPath, URL } from 'node:url';
import { defineConfig } from 'vite';

export default defineConfig({
    resolve: {
        alias: {
            '@': fileURLToPath(new URL('./resources/js', import.meta.url)),
        },
    },

    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.tsx'],
            ssr: 'resources/js/ssr.tsx',
            refresh: true,
        }),
        react({
            babel: {
                plugins: ['babel-plugin-react-compiler'],
            },
        }),
        tailwindcss(),
    ],
    esbuild: {
        jsx: 'automatic',
    },
    build: {
        // Task 7.2.3: Bundle size optimization
        rollupOptions: {
            output: {
                // Use Vite's default chunking for maximum compatibility
                chunkFileNames: 'js/[name]-[hash].js',
                entryFileNames: 'js/[name]-[hash].js',
                assetFileNames: 'assets/[name]-[hash][extname]',
            },
        },
        // Increase chunk size warning limit (500kb instead of 500kb default)
        chunkSizeWarningLimit: 1000,
        // Minification options
        minify: 'esbuild',
        target: 'esnext',
    },
    // Performance optimizations
    optimizeDeps: {
        include: ['react', 'react-dom', '@inertiajs/react'],
        exclude: [],
    },
});
