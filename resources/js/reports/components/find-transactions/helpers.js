import Decimal from 'decimal.js';
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
    locale: filters.locale || (window.YAFFA && window.YAFFA.userSettings.locale) || null,
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

/**
 * Round a number to 2 decimal places using exact decimal arithmetic (decimal.js),
 * avoiding IEEE 754 floating-point precision errors.
 *
 * @param {number} num
 * @returns {number} Rounded value
 */
export function round2(num) {
  return new Decimal(num).toDecimalPlaces(2).toNumber();
}

/**
 * Determine transaction type flags from transaction object.
 * Helps distinguish between deposit, withdrawal, and transfer types
 * with fallback to transaction_type_id constants.
 *
 * @param {Object} transaction - Transaction object
 * @returns {{isDeposit: boolean, isWithdrawal: boolean, isTransfer: boolean, isInvestment: boolean}}
 */
export function getTransactionTypeFlags(transaction) {
  if (!transaction || !transaction.transaction_type) {
    return { isDeposit: false, isWithdrawal: false, isTransfer: false, isInvestment: false };
  }

  return {
    isDeposit: transaction.transaction_type === 'deposit',
    isWithdrawal: transaction.transaction_type === 'withdrawal',
    isTransfer: transaction.transaction_type === 'transfer',
    isInvestment: transaction.config_type === 'investment',
  };
}

/**
 * Determine whether a transaction item matches the currently active item-level filters
 * (category and/or tag). An item counts as matching if it satisfies ANY of the active
 * filters. This is a pragmatic approximation rather than a precise re-derivation of the
 * backend's transaction-level query: when category AND tag filters are both active, the
 * backend can match a transaction via two different items (one per filter), so a strict
 * "matches both" check per item would incorrectly exclude items that legitimately
 * contributed to the match.
 *
 * When neither filter is active, every item matches (nothing to narrow).
 *
 * @param {Object} item - Transaction item with `category` (and `category.parent`) and `tags`
 * @param {Object} [filters]
 * @param {string[]} [filters.categoryIds] - Selected category ids (parent selections
 *   also match their children, mirroring the backend's category expansion)
 * @param {string[]} [filters.tagIds] - Selected tag ids
 * @returns {boolean}
 */
export function itemMatchesActiveFilters(item, { categoryIds = [], tagIds = [] } = {}) {
  if (categoryIds.length === 0 && tagIds.length === 0) {
    return true;
  }

  const matchesCategory =
    categoryIds.length > 0 &&
    !!item.category &&
    (categoryIds.includes(String(item.category.id)) ||
      (item.category.parent &&
        categoryIds.includes(String(item.category.parent.id))));

  const matchesTag =
    tagIds.length > 0 &&
    (item.tags || []).some((tag) => tag && tagIds.includes(String(tag.id)));

  return Boolean(matchesCategory || matchesTag);
}

/**
 * Aggregate transactions into a category data map.
 * Groups transactions by full category name, separates deposits/withdrawals,
 * and calculates monthly values per category.
 *
 * Skips transfers and investment transactions.
 * Requires transactions to have parsed Date objects in transaction.date, which is generally expected
 *
 * @param {Array} transactions - Array of transaction objects
 * @param {Object} [options]
 * @param {boolean} [options.matchingItemsOnly] - When true, only items matching the
 *   active category/tag filters are counted (see itemMatchesActiveFilters)
 * @param {string[]} [options.categoryIds] - Currently selected category ids
 * @param {string[]} [options.tagIds] - Currently selected tag ids
 * @returns {Object<string, {values: Object, depositValues: Object, withdrawalValues: Object, categoryIds: Set, depositTotal: number, withdrawalTotal: number, rawName: string, parentName: string, parentId: number}>}
 */
export function aggregateTransactionsByCategory(transactions, options = {}) {
  const { matchingItemsOnly = false, categoryIds = [], tagIds = [] } = options;
  const data = {};

  transactions.forEach((transaction) => {
    // Sanity check for date
    if (!transaction.date || !(transaction.date instanceof Date)) return;

    const typeFlags = getTransactionTypeFlags(transaction);

    // For the category level breakdown, transfers and investments are not relevant, as they are not associated with a category
    if (typeFlags.isTransfer || typeFlags.isInvestment) return;

    const month = transaction.year_month;

    // Skip transactions without items, as they cannot be categorized
    if (!transaction.transaction_items) {
      return;
    }

    transaction.transaction_items.forEach((item) => {
      // Theoretically, all transaction items should have a category due to database constraints, but we add a safety check here just in case of data issues
      if (!item.category) {
        return;
      }

      if (
        matchingItemsOnly &&
        !itemMatchesActiveFilters(item, { categoryIds, tagIds })
      ) {
        return;
      }

      // Category ID is mandatory on a database level, but we add an untranslated fallback name for safety in case of data issues
      const categoryName = item.category.full_name || item.category.name || 'Error: no category assigned';
      const categoryId = item.category.id;

      let amount = Number(item.amount_in_base || 0);

      if (!isFinite(amount)) {
        amount = 0;
      }

      const parentName = item.category.parent?.name || null;
      const parentId = item.category.parent?.id || null;

      if (!data[categoryName]) {
        data[categoryName] = {
          values: {},
          depositValues: {},
          withdrawalValues: {},
          categoryIds: new Set(),
          depositTotal: 0,
          withdrawalTotal: 0,
          rawName: item.category.name || categoryName,
          parentName,
          parentId,
        };
      }

      data[categoryName].categoryIds.add(categoryId);

      if (typeFlags.isDeposit) {
        data[categoryName].depositTotal += amount;
        data[categoryName].depositValues[month] =
          (data[categoryName].depositValues[month] || 0) + amount;
      } else {
        data[categoryName].withdrawalTotal += amount;
        data[categoryName].withdrawalValues[month] =
          (data[categoryName].withdrawalValues[month] || 0) + amount;
      }
    });
  });

  // Calculate net values per month: income = deposits - withdrawals
  Object.values(data).forEach((entry) => {
    const isIncome = entry.depositTotal > entry.withdrawalTotal;
    const months = new Set([
      ...Object.keys(entry.depositValues),
      ...Object.keys(entry.withdrawalValues),
    ]);

    months.forEach((month) => {
      const deposits = entry.depositValues[month] || 0;
      const withdrawals = entry.withdrawalValues[month] || 0;
      entry.values[month] = isIncome
        ? deposits - withdrawals
        : withdrawals - deposits;
    });
  });

  return data;
}

/**
 * Process a list of category names into sorted rows with totals, subtotals, and statistics.
 * Used to transform categoryData into display rows for a section.
 *
 * @param {string[]} categoryNames - Category names to process
 * @param {Object} catData - Category data map from aggregateTransactionsByCategory()
 * @param {string[]} months - Sorted month strings (YYYY-MM)
 * @param {number} monthCount - Total number of months (for average calculation)
 * @returns {{rows: Array<{name, displayName, values, total, avg, nonZeroAvg, nonZeroCount, categoryIds, isIncome}>, subtotals: Object, subtotalSum: number, subtotalAvg: number, allCategoryIds: number[]}}
 */
export function processCategoryGroup(categoryNames, catData, months, monthCount) {
  const rows = categoryNames.map((catName) => {
    const entry = catData[catName];
    const values = entry.values;
    const total = months.reduce((sum, month) => sum + (values[month] || 0), 0);
    const nonZeroCount = months
      .map((month) => values[month] || 0)
      .filter((v) => v !== 0).length;
    const avg = nonZeroCount > 0 ? total / monthCount : 0;
    const nonZeroAvg = nonZeroCount > 0 ? total / nonZeroCount : 0;

    return {
      name: catName,
      displayName: entry.rawName || catName,
      values,
      total: round2(total),
      avg: round2(avg),
      nonZeroAvg: round2(nonZeroAvg),
      nonZeroCount,
      categoryIds: Array.from(entry.categoryIds),
      isIncome: entry.depositTotal > entry.withdrawalTotal,
    };
  });

  rows.sort((a, b) => b.total - a.total);

  const subtotals = {};
  months.forEach((month) => {
    subtotals[month] = rows.reduce((sum, r) => sum + (r.values[month] || 0), 0);
  });
  const subtotalSum = round2(rows.reduce((sum, r) => sum + r.total, 0));
  const subtotalAvg = round2(subtotalSum / monthCount);
  const allCategoryIds = rows.flatMap((r) => r.categoryIds);

  return { rows, subtotals, subtotalSum, subtotalAvg, allCategoryIds };
}

/**
 * Calculate CSS class for cell deviation highlighting.
 * Compares value against non-zero average to highlight unusual months.
 *
 * For expenses: above average = red (bad), below = green (good).
 * For income: above average = green (good), below = red (bad).
 * Requires minimum 3 non-zero months to activate.
 *
 * @param {number} value - Cell amount
 * @param {number} nonZeroAvg - Average across non-zero months
 * @param {number} nonZeroCount - Number of non-zero months
 * @param {boolean} isIncome - Whether this is an income category
 * @param {Object} [deviationLevels={level1: 0.05, level2: 0.1, level3: 0.15}] - Deviation thresholds
 * @returns {string} CSS class name or empty string
 */
export function calculateDeviationClass(
  value,
  nonZeroAvg,
  nonZeroCount,
  isIncome,
) {
  if (nonZeroCount < 3 || value === 0 || nonZeroAvg === 0) return '';

  // As long as we don't expect the levels to be configurable by the user,
  // we can keep them hardcoded here for simplicity
  const deviationLevels = { level1: 0.05, level2: 0.1, level3: 0.15 };

  const deviation = (value - nonZeroAvg) / nonZeroAvg;
  const above = isIncome ? 'low' : 'high';
  const below = isIncome ? 'high' : 'low';

  if (deviation > deviationLevels.level3) return `bg-deviation-${above}-3`;
  if (deviation > deviationLevels.level2) return `bg-deviation-${above}-2`;
  if (deviation > deviationLevels.level1) return `bg-deviation-${above}-1`;

  if (deviation < -deviationLevels.level3) return `bg-deviation-${below}-3`;
  if (deviation < -deviationLevels.level2) return `bg-deviation-${below}-2`;
  if (deviation < -deviationLevels.level1) return `bg-deviation-${below}-1`;

  return '';
}

/**
 * Build section hierarchy from category data.
 * Groups categories by parent, sorts by total amount descending,
 * assigns CSS classes from rotating palette, and creates "Other" section.
 *
 * @param {Object} categoryData - Category data map from aggregateTransactionsByCategory()
 * @param {string[]} months - Sorted month strings (YYYY-MM)
 * @param {number} monthCount - Total number of months
 * @param {Array<string>} [sectionCssClasses] - Rotating CSS class names (default: 8-color palette)
 * @param {Function} [translateFn] - i18n function for section titles (e.g., "Other income")
 * @returns {Array<{title: string, cssClass: string, isIncome: boolean, rows: Array, subtotals: Object, subtotalSum: number, subtotalAvg: number, allCategoryIds: number[]}>}
 */
export function buildSectionHierarchy(
  categoryData,
  months,
  monthCount,
  sectionCssClasses = [
    's-section-0',
    's-section-1',
    's-section-2',
    's-section-3',
    's-section-4',
    's-section-5',
    's-section-6',
    's-section-7',
  ],
  translateFn = (s) => s,
) {
  // Group categories by parent name
  const groups = {};
  const noParent = [];

  Object.keys(categoryData).forEach((catName) => {
    const entry = categoryData[catName];
    if (entry.parentName) {
      if (!groups[entry.parentName]) groups[entry.parentName] = [];
      groups[entry.parentName].push(catName);
    } else {
      noParent.push(catName);
    }
  });

  // Pre-calculate totals per parent group, then sort descending
  const parentTotals = Object.fromEntries(
    Object.keys(groups).map((parentName) => [
      parentName,
      groups[parentName].reduce(
        (sum, c) =>
          sum +
          months.reduce((sum, month) => sum + (categoryData[c].values[month] || 0), 0),
        0,
      ),
    ]),
  );
  const sortedParents = Object.keys(groups).sort(
    (a, b) => parentTotals[b] - parentTotals[a],
  );

  // Build sections from parent groups
  const sections = [];
  sortedParents.forEach((parentName, idx) => {
    const group = processCategoryGroup(
      groups[parentName],
      categoryData,
      months,
      monthCount,
    );
    const isIncome = groups[parentName].every(
      (c) => categoryData[c].depositTotal > categoryData[c].withdrawalTotal,
    );
    sections.push({
      title: parentName,
      cssClass: sectionCssClasses[idx % sectionCssClasses.length],
      isIncome,
      ...group,
    });
  });

  // Add "Other" section for parentless categories
  if (noParent.length > 0) {
    const group = processCategoryGroup(noParent, categoryData, months, monthCount);
    const isIncome = noParent.every(
      (c) => categoryData[c].depositTotal > categoryData[c].withdrawalTotal,
    );
    const otherTitle = isIncome ? translateFn('Other income') : translateFn('Other expenses');
    sections.push({
      title: otherTitle,
      cssClass: 's-other',
      isIncome,
      ...group,
    });
  }

  return sections;
}

/**
 * Calculate monthly totals for a specific transaction type (income/expenses).
 * Sums all values for each month across the specified transaction type.
 *
 * @param {Object} categoryData - Category data map from aggregateTransactionsByCategory()
 * @param {string[]} months - Sorted month strings (YYYY-MM)
 * @param {boolean} isIncome - If true, sum income; if false, sum expenses
 * @returns {Object<string, number>} Monthly totals keyed by YYYY-MM
 */
export function calculateMonthlyTotalsByType(categoryData, months, isIncome) {
  const totals = {};
  months.forEach((m) => {
    totals[m] = 0;
  });

  Object.values(categoryData).forEach((entry) => {
    const entryIsIncome = entry.depositTotal > entry.withdrawalTotal;
    if (entryIsIncome === isIncome) {
      months.forEach((m) => {
        totals[m] += entry.values[m] || 0;
      });
    }
  });

  return totals;
}

/**
 * Transaction types eligible for the investment income/payment waterfall bucket.
 * Mirrors TransactionType::investmentTypesWithAmountValues() on the backend: only
 * these investment types carry a well-defined cashflow amount.
 */
const INVESTMENT_CASHFLOW_TYPES = ['dividend', 'interest_yield'];

/**
 * Aggregate transactions into waterfall-ready {category, value} rows, mirroring the
 * grouping used by the dashboard's category waterfall widget: standard transactions
 * (excluding transfers) are grouped by their top-level category (the parent's name,
 * or the category's own name when it has no parent), and eligible investment
 * transactions are grouped into a single income/payment bucket.
 *
 * Requires transaction_items to carry `amount_in_base` and, for investment
 * transactions, `cashflow_value` + `currencyRateToBase` on the transaction itself
 * (both already provided by the /api/v1/transactions response).
 *
 * @param {Array} transactions - Processed transaction objects (see processTransaction)
 * @param {Function} [translateFn] - i18n function for the investment bucket labels
 * @param {Object} [options]
 * @param {boolean} [options.matchingItemsOnly] - When true, only items matching the
 *   active category/tag filters are counted (see itemMatchesActiveFilters)
 * @param {string[]} [options.categoryIds] - Currently selected category ids
 * @param {string[]} [options.tagIds] - Currently selected tag ids
 * @returns {Array<{category: string, value: number}>}
 */
export function aggregateTransactionsForWaterfall(
  transactions,
  translateFn = (s) => s,
  options = {},
) {
  const { matchingItemsOnly = false, categoryIds = [], tagIds = [] } = options;
  const dataByCategory = {};

  transactions.forEach((transaction) => {
    if (transaction.config_type === 'standard') {
      if (transaction.transaction_type === 'transfer') return;

      (transaction.transaction_items || []).forEach((item) => {
        if (!item.category) return;

        if (
          matchingItemsOnly &&
          !itemMatchesActiveFilters(item, { categoryIds, tagIds })
        ) {
          return;
        }

        const topCategory = item.category.parent || item.category;
        const label = topCategory.name;
        const amount = Number(item.amount_in_base || 0);
        const signed = transaction.transaction_type === 'withdrawal' ? -amount : amount;

        dataByCategory[label] =
          (dataByCategory[label] || 0) + (isFinite(signed) ? signed : 0);
      });
    } else if (transaction.config_type === 'investment') {
      if (!INVESTMENT_CASHFLOW_TYPES.includes(transaction.transaction_type)) return;

      const rate = transaction.currencyRateToBase ?? 1;
      const amount = Number(transaction.cashflow_value || 0) * rate;
      const label = translateFn(amount < 0 ? 'Investment payment' : 'Investment income');

      dataByCategory[label] =
        (dataByCategory[label] || 0) + (isFinite(amount) ? amount : 0);
    }
  });

  return Object.entries(dataByCategory).map(([category, value]) => ({
    category,
    value,
  }));
}