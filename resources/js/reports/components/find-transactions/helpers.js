import { getArrayParamFromUrl } from '@/shared/lib/helpers';

/**
 * Build a cache key string from a filter object.
 *
 * @param {Object} filters - Filter values to include in the key
 * @param {string} filters.date_from
 * @param {string} filters.date_to
 * @param {Array} filters.accounts
 * @param {Array} filters.categories
 * @param {Array} filters.payees
 * @param {Array} filters.tags
 * @param {Array} filters.types
 * @param {Array} filters.investments
 * @returns {string} JSON-serialized key
 */
export function buildFilterCacheKey(filters) {
  return JSON.stringify({
    date_from: filters.date_from || null,
    date_to: filters.date_to || null,
    accounts: (filters.accounts || []).slice().sort(),
    categories: (filters.categories || []).slice().sort(),
    payees: (filters.payees || []).slice().sort(),
    tags: (filters.tags || []).slice().sort(),
    types: (filters.types || []).slice().sort(),
    investments: (filters.investments || []).slice().sort(),
    locale:
      filters.locale ||
      (window.YAFFA && window.YAFFA.userSettings.locale) ||
      null,
  });
}

/**
 * Build a cache key from URL query parameters.
 * Convenience wrapper around buildFilterCacheKey for use in components
 * that read filters from the URL (e.g. MonthlyBreakdown).
 *
 * @param {string} [searchString=window.location.search]
 * @returns {string} JSON-serialized key
 */
export function buildBreakdownCacheKey(searchString = window.location.search) {
  const urlParams = new URLSearchParams(searchString);
  return buildFilterCacheKey({
    date_from: urlParams.get('date_from'),
    date_to: urlParams.get('date_to'),
    accounts: getArrayParamFromUrl(urlParams, 'accounts'),
    categories: getArrayParamFromUrl(urlParams, 'categories'),
    payees: getArrayParamFromUrl(urlParams, 'payees'),
    tags: getArrayParamFromUrl(urlParams, 'tags'),
    types: getArrayParamFromUrl(urlParams, 'types'),
    investments: getArrayParamFromUrl(urlParams, 'investments'),
    locale: (window.YAFFA && window.YAFFA.userSettings.locale) || null,
  });
}
