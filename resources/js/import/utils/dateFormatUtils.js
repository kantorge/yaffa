/**
 * Shared date format patterns and parser for client-side date preview.
 *
 * Regexes intentionally have no trailing $ anchor so that extra content after
 * the date (e.g. ", péntek" in "2026.03.27., péntek") is silently ignored,
 * matching PHP's DateTime::createFromFormat behaviour.
 */
export const DATE_PATTERNS = [
  { format: 'Y-m-d',  regex: /^(\d{4})-(\d{2})-(\d{2})/,            y: 1, m: 2, d: 3 },
  { format: 'Y.m.d.', regex: /^(\d{4})\.(\d{2})\.(\d{2})\./,        y: 1, m: 2, d: 3 },
  { format: 'Y/m/d',  regex: /^(\d{4})\/(\d{2})\/(\d{2})/,          y: 1, m: 2, d: 3 },
  { format: 'd.m.Y',  regex: /^(\d{1,2})\.(\d{1,2})\.(\d{4})/,      y: 3, m: 2, d: 1 },
  { format: 'd/m/Y',  regex: /^(\d{1,2})\/(\d{1,2})\/(\d{4})/,      y: 3, m: 2, d: 1 },
  { format: 'm/d/Y',  regex: /^(\d{1,2})\/(\d{1,2})\/(\d{4})/,      y: 3, m: 1, d: 2 },
  { format: 'd-m-Y',  regex: /^(\d{1,2})-(\d{1,2})-(\d{4})/,        y: 3, m: 2, d: 1 },
  { format: 'd.m.y',  regex: /^(\d{1,2})\.(\d{1,2})\.(\d{2})/,      y: 3, m: 2, d: 1 },
  { format: 'd/m/y',  regex: /^(\d{1,2})\/(\d{1,2})\/(\d{2})/,      y: 3, m: 2, d: 1 },
  { format: 'm/d/y',  regex: /^(\d{1,2})\/(\d{1,2})\/(\d{2})/,      y: 3, m: 1, d: 2 },
];

/**
 * Try to parse a date string using a PHP-style format string.
 * Returns an ISO date string (YYYY-MM-DD) on success, null on failure.
 */
export function tryParseDate(value, format) {
  if (!value || !format) return null;
  const trimmed = String(value).trim();
  const pattern = DATE_PATTERNS.find((p) => p.format === format);
  if (!pattern) return null;
  const match = trimmed.match(pattern.regex);
  if (!match) return null;
  const year  = parseInt(match[pattern.y]);
  const month = parseInt(match[pattern.m]);
  const day   = parseInt(match[pattern.d]);
  const fullYear = year < 100 ? 2000 + year : year;
  if (month < 1 || month > 12 || day < 1 || day > 31) return null;
  return `${fullYear}-${String(month).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
}
