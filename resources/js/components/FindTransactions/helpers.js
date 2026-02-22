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
    locale: filters.locale || (window.YAFFA && window.YAFFA.locale) || null,
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
    accounts: urlParams.getAll('accounts[]'),
    categories: urlParams.getAll('categories[]'),
    payees: urlParams.getAll('payees[]'),
    tags: urlParams.getAll('tags[]'),
    locale: (window.YAFFA && window.YAFFA.locale) || null,
  });
}

/**
 * Round a number to 2 decimal places with EPSILON correction
 * for IEEE 754 floating-point precision errors.
 *
 * @param {number} num
 * @returns {number} Rounded value
 */
export function round2(num) {
  return Math.round((num + Number.EPSILON) * 100) / 100;
}

/**
 * Determine transaction type flags from transaction object.
 * Helps distinguish between deposit, withdrawal, and transfer types
 * with fallback to transaction_type_id constants.
 *
 * @param {Object} tx - Transaction object
 * @returns {{isDeposit: boolean, isWithdrawal: boolean, isTransfer: boolean, isInvestment: boolean}}
 */
export function getTransactionTypeFlags(tx) {
  if (!tx || !tx.transaction_type) {
    return { isDeposit: false, isWithdrawal: false, isTransfer: false, isInvestment: false };
  }

  const txTypeName = tx.transaction_type?.name || tx.transaction_type?.type;
  const txTypeId = tx.transaction_type_id;
  const txTypeBase = tx.transaction_type?.type;

  return {
    isDeposit: txTypeName === 'deposit' || txTypeId === 2,
    isWithdrawal: txTypeName === 'withdrawal' || txTypeId === 1,
    isTransfer: txTypeName === 'transfer' || txTypeId === 3,
    isInvestment: txTypeBase === 'investment',
  };
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
 * @returns {Object<string, {values: Object, depositValues: Object, withdrawalValues: Object, categoryIds: Set, depositTotal: number, withdrawalTotal: number, rawName: string, parentName: string, parentId: number}>}
 */
export function aggregateTransactionsByCategory(transactions) {
  const data = {};

  transactions.forEach((transaction) => {
    if (!transaction.date || !(transaction.date instanceof Date)) return;

    const typeFlags = getTransactionTypeFlags(transaction);
    if (typeFlags.isTransfer || typeFlags.isInvestment) return;

    const month = transaction.year_month;

    if (transaction.transaction_items) {
      transaction.transaction_items.forEach((item) => {
        if (!item.category) return;

        // Category ID is mandatory on a database level, but we add an untranlated fallback name for safety in case of data issues
        const categoryName = item.category.full_name || item.category.name || 'Error: no category assigned';
        const categoryId = item.category.id;
        let rawAmount = Number(item.amount_in_base || 0);
        if (!isFinite(rawAmount)) rawAmount = 0;
        const amountAbs = Math.abs(rawAmount);
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
        const isDepositTx = typeFlags.isDeposit || (!typeFlags.isWithdrawal && rawAmount < 0);

        if (isDepositTx) {
          data[categoryName].depositTotal += amountAbs;
          data[categoryName].depositValues[month] =
            (data[categoryName].depositValues[month] || 0) + amountAbs;
        } else {
          data[categoryName].withdrawalTotal += amountAbs;
          data[categoryName].withdrawalValues[month] =
            (data[categoryName].withdrawalValues[month] || 0) + amountAbs;
        }
      });
    }
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
      nonZeroAvg,
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