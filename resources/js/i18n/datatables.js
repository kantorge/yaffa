const dataTablesLocaleLoaders = {
    en_GB: () => import('datatables.net-plugins/i18n/en-GB.mjs'),
    fr_FR: () => import('datatables.net-plugins/i18n/fr-FR.mjs'),
    hu: () => import('datatables.net-plugins/i18n/hu.mjs'),
    pl: () => import('datatables.net-plugins/i18n/pl.mjs'),
};

const dataTablesLanguageFallbackLocales = {
    en: 'en_GB',
    fr: 'fr_FR',
    hu: 'hu',
    pl: 'pl',
};

export function normalizeDataTablesLocale(locale) {
    if (!locale || typeof locale !== 'string') {
        return null;
    }

    return locale.replace('-', '_');
}

export function resolveDataTablesLocaleCandidates(locale, language) {
    const normalizedLocale = normalizeDataTablesLocale(locale);
    const normalizedLanguage = typeof language === 'string'
        ? language.toLowerCase()
        : null;

    return [
        normalizedLocale,
        dataTablesLanguageFallbackLocales[normalizedLanguage],
        dataTablesLanguageFallbackLocales.en,
    ].filter((candidate, index, list) => candidate && list.indexOf(candidate) === index);
}

export async function loadDataTablesLanguage(locale = window.YAFFA?.locale, language = window.YAFFA?.language) {
    const candidates = resolveDataTablesLocaleCandidates(locale, language);

    for (const candidate of candidates) {
        const loader = dataTablesLocaleLoaders[candidate];
        if (!loader) {
            continue;
        }

        try {
            const languageModule = await loader();
            return languageModule?.default || languageModule;
        } catch (_error) {
            continue;
        }
    }

    return null;
}

function applyDataTablesLanguageDefaults(languageOptions) {
    if (!languageOptions) {
        return;
    }

    if ($.fn?.dataTable?.defaults) {
        $.extend(true, $.fn.dataTable.defaults, {
            language: languageOptions,
        });
    }

    if (window.DataTable?.defaults) {
        window.DataTable.defaults.language = languageOptions;
    }
}

function applyDataTablesLanguageToExistingTables(languageOptions) {
    if (!languageOptions || !$.fn?.dataTable?.tables) {
        return;
    }

    const tables = $.fn.dataTable.tables({
        api: true,
    });

    if (!tables || typeof tables.iterator !== 'function') {
        return;
    }

    tables.iterator('table', function (settings) {
        settings.oLanguage = $.extend(true, {}, settings.oLanguage, languageOptions);
    });

    tables.draw(false);
}

export async function initializeDataTablesI18n(locale = window.YAFFA?.locale, language = window.YAFFA?.language) {
    const languageOptions = await loadDataTablesLanguage(locale, language);

    if (!languageOptions) {
        return null;
    }

    window.YAFFA = window.YAFFA || {};
    window.YAFFA.dataTablesLanguage = languageOptions;

    applyDataTablesLanguageDefaults(languageOptions);
    applyDataTablesLanguageToExistingTables(languageOptions);

    return languageOptions;
}

export function getDataTablesLanguageOptions() {
    return window.YAFFA?.dataTablesLanguage || null;
}
