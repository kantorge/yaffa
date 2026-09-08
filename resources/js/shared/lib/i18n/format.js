/**
 * @param {number} input The number to be formatted as currency.
 * @param {string} locale The locale to be used for formatting.
 * @param {Object} currencySettings Object with settings to apply. Expected key(s): iso_code. Optional key(s): generic_decimal_precision, detailed_decimal_precision.
 * @property {string} currencySettings.iso_code
 * @property {number|null} currencySettings.generic_decimal_precision
 * @property {number|null} currencySettings.detailed_decimal_precision
 * @param {'generic'|'detailed'} [precision='generic'] Whether to apply generic or detailed decimal precision from the currency settings.
 *
 * @type {string}
 */
import { parseIsoDate } from '@/shared/lib/helpers';
import { STORAGE_SCALE } from '@/shared/lib/money/scale';

// Building an Intl.NumberFormat/DateTimeFormat is one of the more expensive things you can do in
// JS (locale data resolution + pattern compilation) - Number/Date.prototype.toLocaleString()
// build a throwaway one on every single call, with no cache of their own. Pages that format the
// same handful of (locale, currency/options) combinations across thousands of table rows or
// chart points pay that cost every time. These caches make it pay it once per distinct
// combination instead. Each page here is a fresh module load (this app is not a SPA - see
// resources/js/CLAUDE.md), so these never accumulate beyond what a single page actually uses.
const numberFormatterCache = new Map();
const dateTimeFormatterCache = new Map();

function formatterCacheKey(locale, options) {
    return locale + '::' + JSON.stringify(options ?? {});
}

/**
 * Returns a cached Intl.NumberFormat for the given locale/options, building it only once per
 * distinct combination actually used on the page.
 *
 * @param {string} locale
 * @param {Intl.NumberFormatOptions} [options]
 * @returns {Intl.NumberFormat}
 */
export function getCachedNumberFormatter(locale, options) {
    const key = formatterCacheKey(locale, options);

    if (!numberFormatterCache.has(key)) {
        numberFormatterCache.set(key, new Intl.NumberFormat(locale, options));
    }

    return numberFormatterCache.get(key);
}

/**
 * Returns a cached Intl.DateTimeFormat for the given locale/options - see
 * getCachedNumberFormatter() above for why this is worth doing.
 *
 * @param {string} locale
 * @param {Intl.DateTimeFormatOptions} [options]
 * @returns {Intl.DateTimeFormat}
 */
export function getCachedDateTimeFormatter(locale, options) {
    const key = formatterCacheKey(locale, options);

    if (!dateTimeFormatterCache.has(key)) {
        dateTimeFormatterCache.set(key, new Intl.DateTimeFormat(locale, options));
    }

    return dateTimeFormatterCache.get(key);
}

export function toFormattedCurrency(input, locale, currencySettings, precision = 'generic') {
    // Fallback to raw input if currency settings are missing
    if (!currencySettings || !currencySettings.iso_code) {
        return input.toString();
    }

    // If input is not a number, return it as is
    if (input === null || input === undefined) {
        return '';
    }
    if (isNaN(input)) {
        return input.toString();
    }

    // API money fields (MoneyCast) serialize as decimal strings; Number() is required for the
    // currency style options below to take effect.
    input = Number(input);

    // 'detailed' (rate/price fields): floor is the currency's conventional precision, ceiling
    // is the field's storage scale - Intl.NumberFormat trims trailing zeros between the two, so
    // a value with real fractional content up to the storage scale still shows all of it.
    // 'generic' (everyday balances/totals): no floor - Intl.NumberFormat never pads a whole
    // number with zeros it doesn't have. The currency's configured precision is a ceiling only,
    // rounding any real fractional content down to at most that many digits.
    const minDigits = precision === 'detailed'
        ? (currencySettings.detailed_decimal_precision ?? currencySettings.generic_decimal_precision ?? 0)
        : 0;
    const maxDigits = precision === 'detailed'
        ? STORAGE_SCALE.PRICE
        : (currencySettings.generic_decimal_precision ?? 0);

    return getCachedNumberFormatter(locale, {
        style: 'currency',
        currency: currencySettings.iso_code,
        currencyDisplay: 'narrowSymbol',
        minimumFractionDigits: minDigits,
        maximumFractionDigits: maxDigits,
    }).format(input);
}

/**
 * Formats a plain (non-currency) number - a quantity, a count, an exchange rate - using the same
 * cached-formatter mechanism as toFormattedCurrency(). Use this instead of calling
 * Number.prototype.toLocaleString() directly wherever the same (locale, options) combination is
 * likely to be formatted repeatedly (a DataTables column, a chart, a v-for list).
 *
 * @param {number|string} input
 * @param {string} locale
 * @param {Intl.NumberFormatOptions} [options]
 * @returns {string} '' for null/undefined input, the raw stringified input if it isn't numeric.
 */
export function toFormattedNumber(input, locale, options = undefined) {
    if (input === null || input === undefined) {
        return '';
    }
    if (isNaN(input)) {
        return input.toString();
    }

    return getCachedNumberFormatter(locale, options).format(Number(input));
}

/**
 * @param {*} input The value to be formatted as a date. Accepted as-is if already a Date. Otherwise
 * parsed via parseIsoDate (when allowIsoParse is true and input is a string) or via the native Date
 * constructor.
 * @param {string} locale The locale to be used for formatting.
 * @param {*} fallback Value returned when input is null/undefined, or does not resolve to a valid Date.
 * @param {boolean} [allowIsoParse=false] Whether to parse a string input as a "YYYY-MM-DD" date-only
 * value via parseIsoDate, so it lands on the correct local calendar day instead of being shifted by
 * the UTC-midnight interpretation the native Date constructor applies to such strings.
 * @param {Object} [dateOptions] Options object forwarded to toLocaleDateString (e.g. { year: 'numeric', month: 'short', day: 'numeric' }).
 *
 * @type {string}
 */
export function toFormattedDate(input, locale, fallback, allowIsoParse = false, dateOptions = undefined) {
    if (input === null || input === undefined) {
        return fallback;
    }

    let date = input;

    if (!(date instanceof Date)) {
        date = (allowIsoParse && typeof input === 'string')
            ? parseIsoDate(input)
            : new Date(input);
    }

    if (!(date instanceof Date) || Number.isNaN(date.getTime())) {
        return fallback;
    }

    return getCachedDateTimeFormatter(locale, dateOptions).format(date);
}

// Date.prototype.toLocaleString()'s own default (no options) formats both the date and time
// parts as numeric - Intl.DateTimeFormat's default (no options) formats the date part only, so
// toFormattedDateTime() below needs to spell this out explicitly to be a faithful, cacheable
// replacement for a bare `date.toLocaleString()` call.
const DEFAULT_DATE_TIME_OPTIONS = {
    year: 'numeric',
    month: 'numeric',
    day: 'numeric',
    hour: 'numeric',
    minute: 'numeric',
    second: 'numeric',
};

/**
 * Formats a date+time value the same way a bare `date.toLocaleString()` call would (by default),
 * via the cached formatter mechanism - use this instead of calling
 * Date.prototype.toLocaleString() directly wherever the same timestamps are formatted repeatedly
 * (a DataTables column, a v-for list of log entries, etc).
 *
 * @param {*} input Accepted as-is if already a Date, otherwise parsed via `new Date()`.
 * @param {string} locale
 * @param {*} [fallback=''] Value returned when input is null/undefined or not a valid date.
 * @param {Intl.DateTimeFormatOptions} [options] Defaults to matching toLocaleString()'s own
 * default (full numeric date + time) - pass explicit options to customize.
 * @returns {string}
 */
export function toFormattedDateTime(input, locale, fallback = '', options = DEFAULT_DATE_TIME_OPTIONS) {
    if (input === null || input === undefined) {
        return fallback;
    }

    const date = input instanceof Date ? input : new Date(input);

    if (Number.isNaN(date.getTime())) {
        return fallback;
    }

    return getCachedDateTimeFormatter(locale, options).format(date);
}

/**
 * Gets the currency symbol for a given locale and ISO currency code.
 *
 * @param {string} locale - The locale string (e.g., 'en-US', 'de-DE')
 * @param {string} iso_code - The ISO 4217 currency code (e.g., 'USD', 'EUR')
 *
 * @returns {string} The currency symbol for the specified locale and currency
 */
export function getCurrencySymbol(locale, iso_code) {
    if (!iso_code) {
        return '';
    }

    let numberFormat;

    try {
        numberFormat = getCachedNumberFormatter(locale, {
            style: 'currency',
            currency: iso_code,
            currencyDisplay: 'narrowSymbol',
        });
    } catch (e) {
        return '';
    }

    const symbol = numberFormat.format(0).match(/[^0-9,.\s]+/);
    return symbol ? symbol[0] : '';
}