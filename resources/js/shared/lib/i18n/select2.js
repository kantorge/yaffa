const select2I18nLoaders = {
    en: () => import('select2/dist/js/i18n/en.js'),
    fr: () => import('select2/dist/js/i18n/fr.js'),
    hu: () => import('select2/dist/js/i18n/hu.js'),
    pl: () => import('select2/dist/js/i18n/pl.js'),
};

export function loadSelect2Language(lang) {
    const loader = select2I18nLoaders[lang] || select2I18nLoaders.en;
    if (loader) {
        return loader();
    }

    return Promise.resolve();
}
