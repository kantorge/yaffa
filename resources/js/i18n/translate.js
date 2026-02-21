/**
 * Function to translate strings using the predefined translations in the window.YAFFA.translations object.
 *
 * @param {string} key The translation key.
 * @param {Object} replace An object with key/value pairs to replace in the translation string.
 * @property {string} replace.key The key to replace in the translation string.
 * @property {string} replace.value The value to replace the key with.
 * @returns {string}
 */
export function __(key, replace = {}) {
    let translation = window.YAFFA.translations[key] || key;

    // If the replace object is empty, return the translation as is
    if (Object.keys(replace).length === 0) {
        return translation;
    }

    for (const [replaceKey, value] of Object.entries(replace)) {
        translation = translation.replace(':' + replaceKey, String(value));
    }

    return translation;
}

export const translate = __;