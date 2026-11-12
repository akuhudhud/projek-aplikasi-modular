
import { defineConfig } from 'vitest/config';
import react from '@vitejs/plugin-react';

export default defineConfig({
    plugins: [react()],

    server: {
        host: '0.0.0.0',
        port: 5173,
        strictPort: true,

        proxy: {
            '/api': {
                target: 'http://127.0.0.1:8000',
                changeOrigin: true,
            },
            '/web': {
                target: 'http://127.0.0.1:8000',
                changeOrigin: true,
            },
            '/sanctum': {
                target: 'http://127.0.0.1:8000',
                changeOrigin: true,
            },
        },
    },

    build: {
        outDir: 'dist',
        emptyOutDir: true,
        manifest: 'manifest.json',
    },

    test: {
        environment: 'jsdom',
        setupFiles: ['./src/testSetup.ts'],
    },
});
