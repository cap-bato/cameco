import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';
import laravel from 'laravel-vite-plugin';
import { defineConfig } from 'vite';

export default defineConfig({
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
        wayfinder({
            formVariants: true,
        }),
    ],
    esbuild: {
        jsx: 'automatic',
    },
    build: {
        // Task 7.2.3: Bundle size optimization
        rollupOptions: {
            output: {
                // Code splitting for better caching
                manualChunks(id) {
                    // Vendor chunks (libraries)
                    if (id.includes('node_modules')) {
                        // Separate large UI libraries
                        if (id.includes('@radix-ui')) {
                            return 'vendor-radix';
                        }
                        if (id.includes('recharts') || id.includes('d3-')) {
                            return 'vendor-charts';
                        }
                        if (id.includes('react') || id.includes('react-dom')) {
                            return 'vendor-react';
                        }
                        if (id.includes('lucide-react')) {
                            return 'vendor-icons';
                        }
                        // All other vendor code
                        return 'vendor';
                    }
                    
                    // Application code splitting by module
                    if (id.includes('resources/js/pages/HR/Timekeeping')) {
                        return 'timekeeping';
                    }
                    if (id.includes('resources/js/pages/HR/Employee')) {
                        return 'employee';
                    }
                    if (id.includes('resources/js/pages/HR/ATS')) {
                        return 'ats';
                    }
                    if (id.includes('resources/js/pages/Payroll')) {
                        return 'payroll';
                    }
                    if (id.includes('resources/js/components/timekeeping')) {
                        return 'timekeeping-components';
                    }
                },
                // Optimize chunk names for better caching
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
