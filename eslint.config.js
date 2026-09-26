const { defineConfig } = require('eslint/config');

const globals = require('globals');
const js = require('@eslint/js');
const vue = require('eslint-plugin-vue');

const { FlatCompat } = require('@eslint/eslintrc');

const compat = new FlatCompat({
    baseDirectory: __dirname,
    recommendedConfig: js.configs.recommended,
    allConfig: js.configs.all,
});

module.exports = defineConfig([
    js.configs.recommended,
    ...vue.configs['flat/recommended'],
    ...compat.extends('plugin:prettier/recommended'),
    {
        languageOptions: {
            globals: {
                ...globals.browser,
                ...globals.node,
                // Bootstrapped in resources/js/app.js / bootstrap.js, or via
                // Ziggy's @routes / laracasts/utilities JavaScript::put()
                // Blade directives, and shared across the non-module <script>
                // tags on a given page.
                $: 'readonly',
                jQuery: 'readonly',
                __: 'readonly',
                axios: 'readonly',
                coreui: 'readonly',
                csrfToken: 'readonly',
                route: 'readonly',
                account: 'writable',
                categories: 'writable',
                categoryPreferences: 'writable',
                currency: 'writable',
                scheduleData: 'writable',
                table: 'writable',
                tags: 'writable',
                transactionData: 'writable',
            },
            ecmaVersion: 2020,
            parserOptions: {},
        },
        rules: {
            'no-unused-vars': [
                'error',
                {
                    args: 'after-used',
                    argsIgnorePattern: '^_',
                    caughtErrors: 'all',
                    caughtErrorsIgnorePattern: '^_',
                },
            ],
        },
    },
    {
        // Single-word names are the established convention for these
        // components: they're registered as top-level page mounts
        // (Dashboard) or are unambiguous within their own feature folder
        // (transactions/components/display/{Item,Schedule}.vue). Renaming
        // would ripple into every import/registration for no real benefit.
        files: [
            'resources/js/dashboard/components/Dashboard.vue',
            'resources/js/dashboard/index.js',
            'resources/js/transactions/components/display/Item.vue',
            'resources/js/transactions/components/display/Schedule.vue',
        ],
        rules: {
            'vue/multi-word-component-names': 'off',
        },
    },
    {
        // Page-entry scripts intentionally mount more than one small Vue
        // island per page in this multi-page app (see resources/js/CLAUDE.md
        // - "Vue components are mounted as self-contained islands"). Splitting
        // these into one-component-per-file wouldn't change behavior.
        files: ['resources/js/account/show.js', 'resources/js/import/index.js'],
        rules: {
            'vue/one-component-per-file': 'off',
        },
    },
]);
