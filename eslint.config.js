const {
    defineConfig,
} = require("eslint/config");

const globals = require("globals");
const js = require("@eslint/js");
const vue = require("eslint-plugin-vue");

const {
    FlatCompat,
} = require("@eslint/eslintrc");

const compat = new FlatCompat({
    baseDirectory: __dirname,
    recommendedConfig: js.configs.recommended,
    allConfig: js.configs.all
});

module.exports = defineConfig([
    js.configs.recommended,
    ...vue.configs['flat/recommended'],
    ...compat.extends("plugin:prettier/recommended"),
    {
        languageOptions: {
            globals: {
                ...globals.browser,
                ...globals.node,
            },
            ecmaVersion: 2020,
            parserOptions: {},
        },
        rules: {
            "vue/html-indent": ["error", 2],
            indent: ["error", 2],
        },
    },
]);
