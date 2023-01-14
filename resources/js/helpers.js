/**
 * @param {number} input The number to be formatted as currency.
 * @param {string} locale The locale to be used for formatting.
 * @param {Object} currencySettings Object with settings to apply. Expected keys: iso_code, num_digits.
 *
 * @type {string}
 */
export function toFormattedCurrency(input, locale, currencySettings) {
    return input.toLocaleString(
        locale,
        {
            style: 'currency',
            currency: currencySettings.iso_code,
            currencyDisplay: 'narrowSymbol',
            minimumFractionDigits: currencySettings.num_digits,
            maximumFractionDigits: currencySettings.num_digits
        }
    );
}
