import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';
import path from 'node:path';

// Build do frontend publico - React 18 + TS + Tailwind 4
// Saida em ../public/build (consumida pela view frontend.home via manifest.json)
export default defineConfig({
    plugins: [react(), tailwindcss()],
    resolve: {
        alias: { '@': path.resolve(__dirname, 'src') },
    },
    build: {
        manifest: true,
        rollupOptions: {
            input: 'src/main.tsx',
        },
    },
    server: {
        port: 5173,
        proxy: {
            '/api':       { target: 'http://localhost:8000', changeOrigin: true },
            '/uploads':   { target: 'http://localhost:8000', changeOrigin: true },
            '/manifest':  { target: 'http://localhost:8000', changeOrigin: true },
        },
    },
});
