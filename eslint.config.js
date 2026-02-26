const {
    defineConfig,
} = require("eslint/config");

const globals = require("globals");
const js = require("@eslint/js");

const {
    FlatCompat,
} = require("@eslint/eslintrc");

const compat = new FlatCompat({
    baseDirectory: __dirname,
    recommendedConfig: js.configs.recommended,
    allConfig: js.configs.all
});

module.exports = defineConfig([{
    languageOptions: {
        globals: {
            ...globals.browser,
            ...globals.node,
        },

        ecmaVersion: 2020,
        parserOptions: {},
    },

    extends: compat.extends(
        "eslint:recommended",
        "plugin:vue/vue3-recommended",
        "plugin:prettier/recommended",
    ),

    rules: {
        "vue/html-indent": ["error", 2],
        indent: ["error", 2],
    },
}]);
