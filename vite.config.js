import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import path from 'path';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/js/app.js',
            ],
            refresh: true,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
    ],
    resolve: {
        alias: [
            // Use the full build of Vue that includes the template compiler
            { find: 'vue', replacement: 'vue/dist/vue.esm-bundler.js' },
            // Define aliases for easier imports
            { find: '@', replacement: path.resolve(__dirname, 'resources/js') },
            // Prevent bundling optional amCharts PDF export dependencies
            { find: /^pdfmake\/build\/pdfmake\.js$/, replacement: path.resolve(__dirname, 'resources/js/shims/noop-module.js') },
            { find: /^(\.\.\/)+pdfmake\/vfs_fonts(\.js)?$/, replacement: path.resolve(__dirname, 'resources/js/shims/noop-module.js') },
        ],
    },
    optimizeDeps: {
        exclude: ['pdfmake', 'pdfmake/build/pdfmake.js'],
    },
    define: {
        // Define Vue feature flags for better tree-shaking
        __VUE_OPTIONS_API__: true,
        __VUE_PROD_DEVTOOLS__: false,
        __VUE_PROD_HYDRATION_MISMATCH_DETAILS__: false,
    },
    server: {
        hmr: {
            host: 'localhost',
        },
    },
});
