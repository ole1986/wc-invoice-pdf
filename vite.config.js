import vue from '@vitejs/plugin-vue'
import { defineConfig } from 'vite'
import vuetify, { transformAssetUrls } from 'vite-plugin-vuetify'

// https://vite.dev/config/
export default defineConfig({
    build: {
        transpile: ['vuetify'],
    },
    plugins: [vue(), vuetify({ autoImport: true })],
    vue: {
        template: {
            transformAssetUrls,
        },
    },
    root: 'browser',
})
