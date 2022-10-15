export function toFormattedCurrency(input, locale, currencySettings, nonBreakingSpaces) {
    var nonBreakingSpaces = !!nonBreakingSpaces;

    var result = input.toLocaleString(
        locale,
        {
            style: 'currency',
            currency: currencySettings.iso_code,
            currencyDisplay: 'narrowSymbol',
            minimumFractionDigits: currencySettings.num_digits,
            maximumFractionDigits: currencySettings.num_digits
        }
    );

    if (nonBreakingSpaces) {
        result = result.replace(/\s/g, '&nbsp;');
    }

    return result;
}
