<template>
  <div>
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h2>{{ __('Monthly breakdown') }}</h2>
      <div class="form-check form-switch">
        <input
          class="form-check-input"
          type="checkbox"
          id="percentageToggle"
          v-model="showPercentages"
        >
        <label class="form-check-label" for="percentageToggle">
          {{ __('Show percentages') }}
        </label>
      </div>
    </div>

    <ul class="list-group list-group-flush" v-if="busy">
      <li
        aria-hidden="true"
        class="list-group-item placeholder-glow"
        v-for="i in 8"
        :key="i"
      >
        <span class="placeholder col-12"></span>
      </li>
    </ul>

    <div v-else-if="transactions.length === 0 && !cachedCategoryData" class="text-muted">
      {{ __('No transactions to display') }}
    </div>

    <div v-else class="table-responsive breakdown-table-wrapper">
      <table class="table table-sm table-bordered table-hover breakdown-table">
        <thead>
          <tr>
            <th class="sticky-col">{{ __('Category') }}</th>
            <th v-for="m in months" :key="m">{{ formatMonthHeader(m) }}</th>
            <th>{{ __('Total') }}</th>
            <th>{{ __('Avg/month') }}</th>
          </tr>
        </thead>
        <tbody>
          <template v-for="(section, si) in computedSections" :key="si">
            <!-- Section header -->
            <tr class="section-header" :class="section.cssClass">
              <td :colspan="months.length + 3" class="fw-bold">
                {{ __(section.title) }}
              </td>
            </tr>

            <!-- Category rows -->
            <tr v-for="row in section.rows" :key="row.name">
              <td class="sticky-col category-name" :title="row.name">
                <a
                  v-if="row.categoryIds.length === 1"
                  :href="'/categories/' + row.categoryIds[0] + '/edit'"
                  class="category-link"
                >{{ row.displayName }}</a>
                <span v-else>{{ row.displayName }}</span>
              </td>
              <td
                v-for="m in months"
                :key="m"
                :class="deviationClass(row.values[m] || 0, row.nonZeroAvg, row.nonZeroCount)"
                class="text-end"
              >
                <a
                  v-if="(row.values[m] || 0) !== 0"
                  :href="drillDownUrl(m, row.categoryIds)"
                  class="cell-link"
                >
                  {{ formatCell(row.values[m] || 0, section.isIncome ? monthlyTotalIncome[m] : monthlyTotalExpenses[m]) }}
                </a>
                <span v-else class="zero">&mdash;</span>
              </td>
              <td class="text-end fw-semibold">
                {{ formatCell(row.total, section.isIncome ? totalIncomeSum : totalExpensesSum) }}
              </td>
              <td class="text-end">
                {{ formatCell(row.avg, section.isIncome ? totalIncomeAvg : totalExpensesAvg) }}
              </td>
            </tr>

            <!-- Section subtotal -->
            <tr class="subtotal-row">
              <td class="sticky-col fw-bold" :title="__('Subtotal') + ': ' + __(section.title)">
                {{ __('Subtotal') }}: {{ __(section.title) }}
              </td>
              <td
                v-for="m in months"
                :key="m"
                class="text-end fw-bold"
              >
                <a
                  v-if="(section.subtotals[m] || 0) !== 0"
                  :href="drillDownUrl(m, section.allCategoryIds)"
                  class="cell-link"
                >
                  {{ formatCell(section.subtotals[m] || 0, section.isIncome ? monthlyTotalIncome[m] : monthlyTotalExpenses[m]) }}
                </a>
                <span v-else class="zero">&mdash;</span>
              </td>
              <td class="text-end fw-bold">
                {{ formatCell(section.subtotalSum, section.isIncome ? totalIncomeSum : totalExpensesSum) }}
              </td>
              <td class="text-end fw-bold">
                {{ formatCell(section.subtotalAvg, section.isIncome ? totalIncomeAvg : totalExpensesAvg) }}
              </td>
            </tr>
          </template>

          <!-- Grand summary -->
          <tr class="section-header s-summary">
            <td :colspan="months.length + 3" class="fw-bold">
              {{ __('Summary') }}
            </td>
          </tr>

          <tr class="grand-row">
            <td class="sticky-col fw-bold" :title="__('Total expenses')">{{ __('Total expenses') }}</td>
            <td v-for="m in months" :key="m" class="text-end fw-bold">
              {{ formatAmount(monthlyTotalExpenses[m] || 0) }}
            </td>
            <td class="text-end fw-bold">{{ formatAmount(totalExpensesSum) }}</td>
            <td class="text-end fw-bold">{{ formatAmount(totalExpensesAvg) }}</td>
          </tr>

          <tr class="grand-row">
            <td class="sticky-col fw-bold" :title="__('Total income')">{{ __('Total income') }}</td>
            <td v-for="m in months" :key="m" class="text-end fw-bold text-success">
              {{ formatAmount(monthlyTotalIncome[m] || 0) }}
            </td>
            <td class="text-end fw-bold text-success">{{ formatAmount(totalIncomeSum) }}</td>
            <td class="text-end fw-bold text-success">{{ formatAmount(totalIncomeAvg) }}</td>
          </tr>

          <tr class="grand-row">
            <td class="sticky-col fw-bold" :title="__('Balance')">{{ __('Balance') }}</td>
            <td
              v-for="m in months"
              :key="m"
              class="text-end fw-bold"
              :class="(monthlyBalance[m] || 0) >= 0 ? 'text-success' : 'text-danger'"
            >
              {{ formatAmount(monthlyBalance[m] || 0) }}
            </td>
            <td
              class="text-end fw-bold"
              :class="balanceSum >= 0 ? 'text-success' : 'text-danger'"
            >
              {{ formatAmount(balanceSum) }}
            </td>
            <td
              class="text-end fw-bold"
              :class="balanceAvg >= 0 ? 'text-success' : 'text-danger'"
            >
              {{ formatAmount(balanceAvg) }}
            </td>
          </tr>

          <!-- Section subtotals recap -->
          <tr><td :colspan="months.length + 3" class="spacer-row"></td></tr>
          <tr
            v-for="(section, si) in computedSections"
            :key="'recap-' + si"
            class="subtotal-row"
          >
            <td class="sticky-col fw-bold" :title="__(section.title)">{{ __(section.title) }}</td>
            <td v-for="m in months" :key="m" class="text-end fw-bold">
              <span v-if="(section.subtotals[m] || 0) !== 0">
                {{ formatCell(section.subtotals[m] || 0, section.isIncome ? monthlyTotalIncome[m] : monthlyTotalExpenses[m]) }}
              </span>
              <span v-else class="zero">&mdash;</span>
            </td>
            <td class="text-end fw-bold">{{ formatCell(section.subtotalSum, section.isIncome ? totalIncomeSum : totalExpensesSum) }}</td>
            <td class="text-end fw-bold">{{ formatCell(section.subtotalAvg, section.isIncome ? totalIncomeAvg : totalExpensesAvg) }}</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script>
import { __ as translator, toFormattedCurrency, buildBreakdownCacheKey } from '../../helpers';

/** Rotating color palette for section headers */
const SECTION_CSS_CLASSES = [
  's-section-0', 's-section-1', 's-section-2', 's-section-3',
  's-section-4', 's-section-5', 's-section-6', 's-section-7',
];

/**
 * Process a list of category names into sorted rows with totals, subtotals, and statistics.
 *
 * @param {string[]} categoryNames - Category names to process
 * @param {Object} catData - Category data map from categoryData computed
 * @param {string[]} months - Sorted month strings
 * @param {number} monthCount - Number of months (for average calculation)
 * @returns {{rows: Array, subtotals: Object, subtotalSum: number, subtotalAvg: number, allCategoryIds: number[]}}
 */
function processCategoryGroup(categoryNames, catData, months, monthCount) {
  const rows = categoryNames.map((catName) => {
    const entry = catData[catName];
    const values = entry.values;
    const total = months.reduce((sum, m) => sum + (values[m] || 0), 0);
    const nonZeroCount = months.map((m) => values[m] || 0).filter((v) => v > 0).length;
    const avg = nonZeroCount > 0 ? total / monthCount : 0;
    const nonZeroAvg = nonZeroCount > 0 ? total / nonZeroCount : 0;

    return {
      name: catName,
      displayName: entry.rawName || catName,
      values,
      total: Math.round(total * 100) / 100,
      avg: Math.round(avg * 100) / 100,
      nonZeroAvg,
      nonZeroCount,
      categoryIds: Array.from(entry.categoryIds),
    };
  });

  rows.sort((a, b) => b.total - a.total);

  const subtotals = {};
  months.forEach((m) => {
    subtotals[m] = rows.reduce((sum, r) => sum + (r.values[m] || 0), 0);
  });
  const subtotalSum = Math.round(rows.reduce((sum, r) => sum + r.total, 0) * 100) / 100;
  const subtotalAvg = Math.round((subtotalSum / monthCount) * 100) / 100;
  const allCategoryIds = rows.flatMap((r) => r.categoryIds);

  return { rows, subtotals, subtotalSum, subtotalAvg, allCategoryIds };
}

export default {
  name: 'ReportingCanvasFindTransactionsMonthlyBreakdown',
  props: {
    transactions: {
      type: Array,
      required: false,
      default: () => [],
    },
    busy: {
      type: Boolean,
      required: true,
    },
    isDrillDown: {
      type: Boolean,
      default: false,
    },
  },
  data() {
    return {
      showPercentages: false,
      baseCurrency: window.YAFFA.baseCurrency,
      locale: window.YAFFA.locale,
      cachedCategoryData: null,
    };
  },
  mounted() {
    this.loadBreakdownCache();
  },
  computed: {
    /** @returns {string[]} Sorted unique YYYY-MM month strings extracted from transactions or cached data */
    months() {
      // When using cached data, extract months from categoryData values
      if (this.cachedCategoryData) {
        const monthSet = new Set();
        Object.values(this.cachedCategoryData).forEach((entry) => {
          Object.keys(entry.values).forEach((m) => monthSet.add(m));
        });
        return Array.from(monthSet).sort();
      }

      const monthSet = new Set();
      this.transactions.forEach((tx) => {
        if (tx.date instanceof Date) {
          const m = tx.date.getFullYear() + '-' + String(tx.date.getMonth() + 1).padStart(2, '0');
          monthSet.add(m);
        }
      });
      return Array.from(monthSet).sort();
    },

    /**
     * Aggregate transaction items by category name and month.
     * Skips transfers and investment transactions.
     * Uses cached data when available to avoid reprocessing.
     *
     * @returns {Object<string, {values: Object<string, number>, categoryIds: Set<number>, isIncome: boolean, rawName: string}>}
     */
    categoryData() {
      if (this.cachedCategoryData) {
        return this.cachedCategoryData;
      }

      const data = {};

      this.transactions.forEach((tx) => {
        if (!tx.date || !(tx.date instanceof Date)) return;
        // Skip transfers
        if (tx.transaction_type_id === 3) return;
        // Skip investment transactions
        if (tx.transaction_type?.type === 'investment') return;

        const month = tx.date.getFullYear() + '-' + String(tx.date.getMonth() + 1).padStart(2, '0');
        const isWithdrawal = tx.transaction_type_id === 1;
        const isDeposit = tx.transaction_type_id === 2;

        if (tx.transaction_items) {
          tx.transaction_items.forEach((item) => {
            if (!item.category) return;
            const catName = item.category.full_name || item.category.name || 'Uncategorized';
            const catId = item.category.id;
            const amount = Math.abs(item.amount_in_base || item.amount || 0);
            const parentName = item.category.parent?.name || null;
            const parentId = item.category.parent?.id || null;

            if (!data[catName]) {
              data[catName] = {
                values: {},
                categoryIds: new Set(),
                isIncome: isDeposit,
                rawName: item.category.name || catName,
                parentName,
                parentId,
              };
            }

            data[catName].categoryIds.add(catId);
            if (!data[catName].values[month]) {
              data[catName].values[month] = 0;
            }
            data[catName].values[month] += amount;
          });
        }
      });

      return data;
    },

    /**
     * Group categories into sections based on their parent category.
     * Each unique parent category becomes its own section, sorted by total descending.
     * Categories without a parent go into an "Other expenses" section.
     *
     * @returns {Array<{title: string, cssClass: string, rows: Array, subtotals: Object, subtotalSum: number, subtotalAvg: number, allCategoryIds: number[]}>}
     */
    computedSections() {
      const catData = this.categoryData;
      const months = this.months;
      const n = months.length || 1;

      // Group categories by parent name
      const groups = {};
      const noParent = [];

      Object.keys(catData).forEach((catName) => {
        const entry = catData[catName];
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
          groups[parentName].reduce((sum, c) => sum + months.reduce((s, m) => s + (catData[c].values[m] || 0), 0), 0),
        ]),
      );
      const sortedParents = Object.keys(groups).sort((a, b) => parentTotals[b] - parentTotals[a]);

      // Build sections from parent groups
      const sections = [];
      sortedParents.forEach((parentName, idx) => {
        const group = processCategoryGroup(groups[parentName], catData, months, n);
        const isIncome = groups[parentName].every((c) => catData[c].isIncome);
        sections.push({
          title: parentName,
          cssClass: SECTION_CSS_CLASSES[idx % SECTION_CSS_CLASSES.length],
          isIncome,
          ...group,
        });
      });

      // Add "Other" section for parentless categories
      if (noParent.length > 0) {
        const group = processCategoryGroup(noParent, catData, months, n);
        const isIncome = noParent.every((c) => catData[c].isIncome);
        sections.push({
          title: 'Other expenses',
          cssClass: 's-other',
          isIncome,
          ...group,
        });
      }

      return sections;
    },

    /** @returns {Object<string, number>} Monthly total expense amounts keyed by YYYY-MM */
    monthlyTotalExpenses() {
      const catData = this.categoryData;
      const totals = {};
      this.months.forEach((m) => { totals[m] = 0; });
      Object.values(catData).forEach((entry) => {
        if (!entry.isIncome) {
          this.months.forEach((m) => {
            totals[m] += entry.values[m] || 0;
          });
        }
      });
      return totals;
    },

    totalExpensesSum() {
      return Object.values(this.monthlyTotalExpenses).reduce((a, b) => a + b, 0);
    },

    totalExpensesAvg() {
      const n = this.months.length || 1;
      return Math.round((this.totalExpensesSum / n) * 100) / 100;
    },

    /** @returns {Object<string, number>} Monthly total income amounts keyed by YYYY-MM */
    monthlyTotalIncome() {
      const catData = this.categoryData;
      const totals = {};
      this.months.forEach((m) => { totals[m] = 0; });
      Object.values(catData).forEach((entry) => {
        if (entry.isIncome) {
          this.months.forEach((m) => {
            totals[m] += entry.values[m] || 0;
          });
        }
      });
      return totals;
    },

    totalIncomeSum() {
      return Object.values(this.monthlyTotalIncome).reduce((a, b) => a + b, 0);
    },

    totalIncomeAvg() {
      const n = this.months.length || 1;
      return Math.round((this.totalIncomeSum / n) * 100) / 100;
    },

    /** @returns {Object<string, number>} Monthly balance (income - expenses) keyed by YYYY-MM */
    monthlyBalance() {
      const balance = {};
      this.months.forEach((m) => {
        balance[m] = (this.monthlyTotalIncome[m] || 0) - (this.monthlyTotalExpenses[m] || 0);
      });
      return balance;
    },

    balanceSum() {
      return this.totalIncomeSum - this.totalExpensesSum;
    },

    balanceAvg() {
      const n = this.months.length || 1;
      return Math.round((this.balanceSum / n) * 100) / 100;
    },
  },

  watch: {
    transactions(newVal) {
      if (newVal && newVal.length > 0) {
        // If breakdown cache is already loaded and matches, skip reprocessing
        if (this.cachedCategoryData) {
          const currentKey = buildBreakdownCacheKey();
          try {
            const cached = sessionStorage.getItem('yaffa_breakdown_cache');
            if (cached) {
              const { key } = JSON.parse(cached);
              if (key === currentKey) return;
            }
          } catch (e) {
            console.warn('Failed to check breakdown cache key:', e);
          }
        }
        // Clear cached data so computed properties recalculate from fresh transactions
        this.cachedCategoryData = null;
        // Save aggregated results to sessionStorage after Vue recalculates
        this.$nextTick(() => {
          this.saveBreakdownCache();
        });
      }
    },
  },

  methods: {
    __: function (string, replace) {
      return translator(string, replace);
    },

    saveBreakdownCache() {
      try {
        // Don't overwrite cache on drill-down pages
        if (this.isDrillDown) return;

        // Serialize categoryData: convert Sets to Arrays for JSON
        const serializable = {};
        const catData = this.categoryData;
        Object.keys(catData).forEach((key) => {
          serializable[key] = {
            values: catData[key].values,
            categoryIds: Array.from(catData[key].categoryIds),
            isIncome: catData[key].isIncome,
            rawName: catData[key].rawName,
            parentName: catData[key].parentName,
            parentId: catData[key].parentId,
          };
        });

        sessionStorage.setItem('yaffa_breakdown_cache', JSON.stringify({
          key: buildBreakdownCacheKey(),
          categoryData: serializable,
        }));
      } catch (e) {
        console.warn('Failed to save breakdown cache:', e);
      }
    },

    loadBreakdownCache() {
      try {
        const cached = sessionStorage.getItem('yaffa_breakdown_cache');
        if (!cached) return;
        const { key, categoryData } = JSON.parse(cached);
        if (key !== buildBreakdownCacheKey()) return;

        // Restore categoryData with Sets
        const restored = {};
        Object.keys(categoryData).forEach((k) => {
          restored[k] = {
            values: categoryData[k].values,
            categoryIds: new Set(categoryData[k].categoryIds),
            isIncome: categoryData[k].isIncome,
            rawName: categoryData[k].rawName,
            parentName: categoryData[k].parentName,
            parentId: categoryData[k].parentId,
          };
        });

        this.cachedCategoryData = restored;
      } catch (e) {
        console.warn('Failed to load breakdown cache:', e);
      }
    },

    /**
     * Format a YYYY-MM month string as MM.YYYY header.
     * @param {string} month - Month in YYYY-MM format
     * @returns {string} Month in MM.YYYY format
     */
    formatMonthHeader(month) {
      const [year, mon] = month.split('-');
      return `${mon}.${year}`;
    },

    /**
     * Format a numeric value as a localized currency string, or a dash for zero.
     * @param {number} value
     * @returns {string}
     */
    formatAmount(value) {
      if (value === 0) return '—';
      return toFormattedCurrency(
        Math.round(value * 100) / 100,
        this.locale,
        this.baseCurrency,
      );
    },

    /**
     * Format a cell value as currency or percentage depending on the toggle.
     * @param {number} value - The cell amount
     * @param {number} monthTotal - Total expenses for that month (used for percentage mode)
     * @returns {string}
     */
    formatCell(value, monthTotal) {
      if (value === 0) return '—';
      if (this.showPercentages && monthTotal > 0) {
        return ((value / monthTotal) * 100).toFixed(1) + '%';
      }
      return toFormattedCurrency(
        Math.round(value * 100) / 100,
        this.locale,
        this.baseCurrency,
      );
    },

    /**
     * Return a CSS class for deviation highlighting based on percentage
     * deviation from the category's average across non-zero months.
     * Requires at least 3 non-zero months to activate.
     *
     * @param {number} value - The cell amount
     * @param {number} avg - Category average across non-zero months
     * @param {number} nonZeroCount - Number of months with non-zero values
     * @returns {string} CSS class name or empty string
     */
    deviationClass(value, avg, nonZeroCount) {
      if (nonZeroCount < 3 || value === 0 || avg === 0) return '';

      const deviation = (value - avg) / avg;

      if (deviation > 0.15) return 'bg-deviation-high-3';
      if (deviation > 0.10) return 'bg-deviation-high-2';
      if (deviation > 0.05) return 'bg-deviation-high-1';

      if (deviation < -0.15) return 'bg-deviation-low-3';
      if (deviation < -0.10) return 'bg-deviation-low-2';
      if (deviation < -0.05) return 'bg-deviation-low-1';

      return '';
    },

    /**
     * Build a drill-down URL filtered by month and categories.
     * Includes tab=transaction-list to auto-open that tab, and return_to
     * with the current page URL so the user can navigate back.
     *
     * @param {string} month - Month in YYYY-MM format
     * @param {number[]} categoryIds - Category IDs to filter by
     * @returns {string} Full URL with query parameters
     */
    drillDownUrl(month, categoryIds) {
      const [year, mon] = month.split('-').map(Number);
      const lastDay = new Date(year, mon, 0).getDate();
      const dateFrom = `${year}-${String(mon).padStart(2, '0')}-01`;
      const dateTo = `${year}-${String(mon).padStart(2, '0')}-${String(lastDay).padStart(2, '0')}`;

      const params = [`date_from=${dateFrom}`, `date_to=${dateTo}`];
      const uniqueIds = [...new Set(categoryIds)];
      uniqueIds.forEach((id) => params.push(`categories[]=${id}`));
      params.push('tab=transaction-list');
      const returnUrl = new URL(window.location.href);
      returnUrl.searchParams.set('tab', 'monthly-breakdown');
      params.push('return_to=' + encodeURIComponent(returnUrl.href));

      return `/reports/transactions?${params.join('&')}`;
    },

    toFormattedCurrency,
  },
};
</script>

<style scoped>
.breakdown-table-wrapper {
  overflow-x: auto;
}

.breakdown-table {
  font-size: 0.8em;
  table-layout: fixed;
}

.breakdown-table th,
.breakdown-table td {
  padding: 3px 6px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.sticky-col {
  position: sticky;
  left: 0;
  background: #fff;
  z-index: 1;
  min-width: 180px;
  max-width: 220px;
}

.category-name {
  text-overflow: ellipsis;
  overflow: hidden;
}

.category-link {
  color: inherit;
  text-decoration: none;
}
.category-link:hover {
  text-decoration: underline;
}

.section-header td {
  font-size: 1.05em;
  padding: 6px 10px;
  border-radius: 2px;
}

.s-section-0 td { background: #e3f2fd; color: #1565c0; }
.s-section-1 td { background: #fff3e0; color: #e65100; }
.s-section-2 td { background: #e8f5e9; color: #2e7d32; }
.s-section-3 td { background: #e0f2f1; color: #00695c; }
.s-section-4 td { background: #f3e5f5; color: #6a1b9a; }
.s-section-5 td { background: #fce4ec; color: #c2185b; }
.s-section-6 td { background: #fff8e1; color: #f57f17; }
.s-section-7 td { background: #e0f7fa; color: #00838f; }
.s-other td { background: #f5f5f5; color: #616161; }
.s-summary td { background: #e0e0e0; color: #212121; }

.subtotal-row td {
  font-weight: 700;
  border-top: 2px solid #999;
  background: #fafafa;
}

.subtotal-row .sticky-col {
  background: #fafafa;
}

.grand-row td {
  font-weight: 700;
  font-size: 1.05em;
  border-top: 2px solid #333;
  background: #f0f0f0;
}

.grand-row .sticky-col {
  background: #f0f0f0;
}

.zero {
  color: #bbb;
  font-size: 0.85em;
}

.cell-link {
  color: inherit;
  text-decoration: none;
}

.cell-link:hover {
  text-decoration: underline;
  color: #1565c0;
}

.spacer-row {
  height: 8px;
  border: 0 !important;
  background: transparent !important;
}

/* Deviation highlighting */
.bg-deviation-high-1 { background-color: #ffebee !important; }
.bg-deviation-high-2 { background-color: #ffcdd2 !important; }
.bg-deviation-high-3 { background-color: #ef9a9a !important; }
.bg-deviation-low-1 { background-color: #e8f5e9 !important; }
.bg-deviation-low-2 { background-color: #c8e6c9 !important; }
.bg-deviation-low-3 { background-color: #a5d6a7 !important; }
</style>
