import { __ } from '@/shared/lib/i18n';
import presetCalculators from '@/shared/lib/date/presetDates';

function formatDate(date) {
  const y = date.getFullYear();
  const m = String(date.getMonth() + 1).padStart(2, '0');
  const day = String(date.getDate()).padStart(2, '0');
  return `${y}-${m}-${day}`;
}

// A real calendar date in YYYY-MM-DD form (rejects e.g. 2024-02-30)
export function isValidDateString(value) {
  if (!/^\d{4}-\d{2}-\d{2}$/.test(value)) return false;
  const [y, m, d] = value.split('-').map(Number);
  return formatDate(new Date(y, m - 1, d)) === value;
}

/**
 * Read `<prefix>date_preset`, `<prefix>date_from` and `<prefix>date_to` from the URL.
 * Unusable values (unknown preset, malformed date, start after end) are dropped, and a
 * user-facing warning is returned for each, so the caller can run the search without them.
 *
 * @param {URLSearchParams} urlParams
 * @param {string} [prefix]
 * @returns {{ preset: string|null, dateFrom: string|null, dateTo: string|null, warnings: string[] }}
 */
export function readDateRangeParams(urlParams, prefix = '') {
  const warnings = [];
  const read = (name, isValid) => {
    const param = prefix + name;
    const value = urlParams.get(param) || null;
    if (value && !isValid(value)) {
      warnings.push(
        __('Invalid value ":value" for :param. The search runs without it.', {
          value,
          param,
        }),
      );
      return null;
    }
    return value;
  };

  const preset = read('date_preset', (value) =>
    Object.hasOwn(presetCalculators, value),
  );
  let dateFrom = read('date_from', isValidDateString);
  let dateTo = read('date_to', isValidDateString);

  // Can't tell which end is wrong, so neither is trusted
  if (dateFrom && dateTo && dateFrom > dateTo) {
    warnings.push(
      __(
        'Start date :from is later than end date :to. The search runs without a date range.',
        { from: dateFrom, to: dateTo },
      ),
    );
    dateFrom = null;
    dateTo = null;
  }

  return { preset, dateFrom, dateTo, warnings };
}
