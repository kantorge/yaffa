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
