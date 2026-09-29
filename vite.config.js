import vue from '@vitejs/plugin-vue'
import { defineConfig } from 'vite'
import vuetify, { transformAssetUrls } from 'vite-plugin-vuetify'

// https://vite.dev/config/
export default defineConfig(({ command }) => ({
    base: command === 'serve' ? '/' : './',
    server: {
        host: 'localhost',
        cors: true,
        origin: 'http://localhost:5173',
    },
    build: {
        outDir: '../dist',
        emptyOutDir: true,
        transpile: ['vuetify'],
        manifest: 'manifest.json',
        rollupOptions: {
            output: {
                entryFileNames: 'wc-recurring-settings.js',
                assetFileNames: 'wc-recurring-settings.[ext]'
            }
        },
    },
    plugins: [vue(), vuetify({ autoImport: true })],
    vue: {
        template: {
            transformAssetUrls,
        },
    },
    root: 'browser',
}))
