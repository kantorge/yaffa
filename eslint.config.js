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
]);
