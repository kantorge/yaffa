const amChartsLocaleLoaders = {
    en_US: () => import('@amcharts/amcharts4/lang/en_US'),
    fr_FR: () => import('@amcharts/amcharts4/lang/fr_FR'),
    hu_HU: () => import('@amcharts/amcharts4/lang/hu_HU'),
    pl_PL: () => import('@amcharts/amcharts4/lang/pl_PL'),
};

const amChartsLanguageFallbackLocales = {
    en: 'en_US',
    fr: 'fr_FR',
    hu: 'hu_HU',
    pl: 'pl_PL',
};

export function normalizeAmChartsLocale(locale) {
    if (!locale || typeof locale !== 'string') {
        return null;
    }

    return locale.replace('-', '_');
}

export function resolveAmChartsLocaleCandidates(locale, language) {
    const normalizedLocale = normalizeAmChartsLocale(locale);
    const normalizedLanguage = typeof language === 'string'
        ? language.toLowerCase()
        : null;

    return [
        normalizedLocale,
        amChartsLanguageFallbackLocales[normalizedLanguage],
        amChartsLanguageFallbackLocales.en,
    ].filter((candidate, index, list) => candidate && list.indexOf(candidate) === index);
}

export async function loadAmChartsLocale(locale, language) {
    const candidates = resolveAmChartsLocaleCandidates(locale, language);

    for (const candidate of candidates) {
        const loader = amChartsLocaleLoaders[candidate];
        if (!loader) {
            continue;
        }

        try {
            const localeModule = await loader();
            return localeModule?.default || localeModule;
        } catch (_error) {
            continue;
        }
    }

    return null;
}

export async function applyAmChartsLocalization(chart, locale = window.YAFFA?.locale, language = window.YAFFA?.language) {
    if (!chart) {
        return null;
    }

    if (chart.numberFormatter && locale) {
        chart.numberFormatter.intlLocales = locale;
    }
    if (chart.dateFormatter && locale) {
        chart.dateFormatter.intlLocales = locale;
    }

    const localeObject = await loadAmChartsLocale(locale, language);
    if (localeObject && chart.language) {
        chart.language.locale = localeObject;
    }

    return localeObject;
}
