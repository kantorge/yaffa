/**
 * Serialize flat request params the way Select2's `$.ajax` call did.
 *
 * Backends branch on whether a key is present (e.g. `GET /api/v1/accounts` without `q` returns
 * the most-used accounts, with `q` - even empty - it searches), so this is behaviour, not style:
 * - `undefined` is omitted (jQuery's deep-extend of `data` drops undefined keys),
 * - `null` is sent as an empty value (`key=`),
 * - everything else is stringified (`true` -> `true`).
 *
 * @param {Object} params
 * @returns {string} query string without the leading `?`
 */
export function toQueryString(params) {
  return Object.entries(params)
    .filter(([, value]) => value !== undefined)
    .map(
      ([key, value]) =>
        `${encodeURIComponent(key)}=${encodeURIComponent(value ?? '')}`,
    )
    .join('&');
}
