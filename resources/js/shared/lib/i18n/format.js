/**
 * @param {number} input The number to be formatted as currency.
 * @param {string} locale The locale to be used for formatting.
 * @param {Object} currencySettings Object with settings to apply. Expected key(s): iso_code. Optional key(s): min_digits, max_digits, generic_decimal_precision, detailed_decimal_precision.
 * @property {string} currencySettings.iso_code
 * @property {number} currencySettings.min_digits
 * @property {number} currencySettings.max_digits
 * @property {number|null} currencySettings.generic_decimal_precision
 * @property {number|null} currencySettings.detailed_decimal_precision
 * @param {'generic'|'detailed'} [precision='generic'] Whether to apply generic or detailed decimal precision from the currency settings.
 *
 * @type {string}
 */
import { parseIsoDate } from '@/shared/lib/helpers';

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

    let minDigits = currencySettings.min_digits || 0;
    // Allow maxDigits to be 0 if explicitly set, otherwise default to undefined to prevent issues with potential null values
    let maxDigits = currencySettings.max_digits ?? undefined;

    if (precision === 'generic' && currencySettings.generic_decimal_precision != null) {
        minDigits = currencySettings.generic_decimal_precision;
        maxDigits = currencySettings.generic_decimal_precision;
    } else if (precision === 'detailed' && currencySettings.detailed_decimal_precision != null) {
        minDigits = currencySettings.detailed_decimal_precision;
        maxDigits = currencySettings.detailed_decimal_precision;
    }

    return input.toLocaleString(
        locale,
        {
            style: 'currency',
            currency: currencySettings.iso_code,
            currencyDisplay: 'narrowSymbol',
            minimumFractionDigits: minDigits,
            maximumFractionDigits: maxDigits,
        }
    );
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

    return date.toLocaleDateString(locale, dateOptions);
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
        numberFormat = new Intl.NumberFormat(locale, {
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