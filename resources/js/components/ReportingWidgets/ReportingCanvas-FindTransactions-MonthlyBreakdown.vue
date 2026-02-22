<template>
  <div class="reporting-monthly-breakdown">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h2>{{ __('Monthly breakdown') }}</h2>
      <div class="form-check form-switch">
        <input
          class="form-check-input"
          type="checkbox"
          id="percentageToggle"
          v-model="showPercentages"
        />
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

    <div
      v-else-if="transactions.length === 0 && !cachedCategoryData"
      class="text-muted"
    >
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
                  :href="getCategoryLink(row.categoryIds[0])"
                  class="category-link"
                  >{{ row.displayName }}</a
                >
                <span v-else>{{ row.displayName }}</span>
              </td>
              <td
                v-for="m in months"
                :key="m"
                :class="
                  deviationClass(
                    row.values[m] || 0,
                    row.nonZeroAvg,
                    row.nonZeroCount,
                    row.isIncome,
                  )
                "
                class="text-end"
              >
                <a
                  v-if="(row.values[m] || 0) !== 0"
                  href="#"
                  @click.prevent="emitDrillDown(m, row.categoryIds)"
                  class="cell-link"
                >
                  {{
                    formatCell(
                      row.values[m] || 0,
                      row.isIncome
                        ? monthlyTotalIncome[m]
                        : monthlyTotalExpenses[m],
                      row.isIncome,
                    )
                  }}
                </a>
                <span v-else class="zero">&mdash;</span>
              </td>
              <td class="text-end fw-semibold">
                {{
                  formatCell(
                    row.total,
                    row.isIncome ? totalIncomeSum : totalExpensesSum,
                    row.isIncome,
                  )
                }}
              </td>
              <td class="text-end">
                {{
                  formatCell(
                    row.avg,
                    row.isIncome ? totalIncomeAvg : totalExpensesAvg,
                    row.isIncome,
                  )
                }}
              </td>
            </tr>

            <!-- Section subtotal -->
            <tr class="subtotal-row">
              <td
                class="sticky-col fw-bold"
                :title="__('Subtotal') + ': ' + __(section.title)"
              >
                {{ __('Subtotal') }}: {{ __(section.title) }}
              </td>
              <td v-for="m in months" :key="m" class="text-end fw-bold">
                <a
                  v-if="(section.subtotals[m] || 0) !== 0"
                  href="#"
                  @click.prevent="emitDrillDown(m, section.allCategoryIds)"
                  class="cell-link"
                >
                  {{
                    formatCell(
                      section.subtotals[m] || 0,
                      section.isIncome
                        ? monthlyTotalIncome[m]
                        : monthlyTotalExpenses[m],
                      section.isIncome,
                    )
                  }}
                </a>
                <span v-else class="zero">&mdash;</span>
              </td>
              <td class="text-end fw-bold">
                {{
                  formatCell(
                    section.subtotalSum,
                    section.isIncome ? totalIncomeSum : totalExpensesSum,
                    section.isIncome,
                  )
                }}
              </td>
              <td class="text-end fw-bold">
                {{
                  formatCell(
                    section.subtotalAvg,
                    section.isIncome ? totalIncomeAvg : totalExpensesAvg,
                    section.isIncome,
                  )
                }}
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
            <td class="sticky-col fw-bold" :title="__('Total expenses')">
              {{ __('Total expenses') }}
            </td>
            <td v-for="m in months" :key="m" class="text-end fw-bold">
              {{ formatAmount(monthlyTotalExpenses[m] || 0, false) }}
            </td>
            <td class="text-end fw-bold">
              {{ formatAmount(totalExpensesSum, false) }}
            </td>
            <td class="text-end fw-bold">
              {{ formatAmount(totalExpensesAvg, false) }}
            </td>
          </tr>

          <tr class="grand-row">
            <td class="sticky-col fw-bold" :title="__('Total income')">
              {{ __('Total income') }}
            </td>
            <td
              v-for="m in months"
              :key="m"
              class="text-end fw-bold text-success"
            >
              {{ formatAmount(monthlyTotalIncome[m] || 0, true) }}
            </td>
            <td class="text-end fw-bold text-success">
              {{ formatAmount(totalIncomeSum, true) }}
            </td>
            <td class="text-end fw-bold text-success">
              {{ formatAmount(totalIncomeAvg, true) }}
            </td>
          </tr>

          <tr class="grand-row">
            <td class="sticky-col fw-bold" :title="__('Balance')">
              {{ __('Balance') }}
            </td>
            <td
              v-for="m in months"
              :key="m"
              class="text-end fw-bold"
              :class="
                (monthlyBalance[m] || 0) >= 0 ? 'text-success' : 'text-danger'
              "
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
          <tr>
            <td :colspan="months.length + 3" class="spacer-row"></td>
          </tr>
          <tr
            v-for="(section, si) in computedSections"
            :key="'recap-' + si"
            class="subtotal-row"
          >
            <td class="sticky-col fw-bold" :title="__(section.title)">
              {{ __(section.title) }}
            </td>
            <td v-for="m in months" :key="m" class="text-end fw-bold">
              <span v-if="(section.subtotals[m] || 0) !== 0">
                {{
                  formatCell(
                    section.subtotals[m] || 0,
                    section.isIncome
                      ? monthlyTotalIncome[m]
                      : monthlyTotalExpenses[m],
                    section.isIncome,
                  )
                }}
              </span>
              <span v-else class="zero">&mdash;</span>
            </td>
            <td class="text-end fw-bold">
              {{
                formatCell(
                  section.subtotalSum,
                  section.isIncome ? totalIncomeSum : totalExpensesSum,
                  section.isIncome,
                )
              }}
            </td>
            <td class="text-end fw-bold">
              {{
                formatCell(
                  section.subtotalAvg,
                  section.isIncome ? totalIncomeAvg : totalExpensesAvg,
                  section.isIncome,
                )
              }}
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script>
  import { __, toFormattedCurrency } from '@/i18n';
  import {
    buildBreakdownCacheKey,
    round2,
    aggregateTransactionsByCategory,
    processCategoryGroup,
    calculateDeviationClass,
    buildSectionHierarchy,
    calculateMonthlyTotalsByType,
  } from '../FindTransactions/helpers';

  const SECTION_CSS_CLASSES = [
    's-section-0',
    's-section-1',
    's-section-2',
    's-section-3',
    's-section-4',
    's-section-5',
    's-section-6',
    's-section-7',
  ];
  const DEVIATION_LEVEL_1 = 0.05;
  const DEVIATION_LEVEL_2 = 0.1;
  const DEVIATION_LEVEL_3 = 0.15;

  export default {
    name: 'ReportingCanvasFindTransactionsMonthlyBreakdown',
    emits: ['drill-down'],
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
            const m =
              tx.date.getFullYear() +
              '-' +
              String(tx.date.getMonth() + 1).padStart(2, '0');
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
       * @returns {Object<string, {values: Object<string, number>, categoryIds: Set<number>, depositTotal: number, withdrawalTotal: number, rawName: string}>}
       */
      categoryData() {
        if (this.cachedCategoryData) {
          return this.cachedCategoryData;
        }

        return aggregateTransactionsByCategory(this.transactions, __);
      },

      /**
       * Group categories into sections based on their parent category.
       * Each unique parent category becomes its own section, sorted by total descending.
       * Categories without a parent go into an "Other income/expenses" section.
       *
       * @returns {Array<{title: string, cssClass: string, rows: Array, subtotals: Object, subtotalSum: number, subtotalAvg: number, allCategoryIds: number[]}>}
       */
      computedSections() {
        return buildSectionHierarchy(
          this.categoryData,
          this.months,
          this.months.length || 1,
          SECTION_CSS_CLASSES,
          __,
        );
      },

      /** @returns {Object<string, number>} Monthly total expense amounts keyed by YYYY-MM */
      monthlyTotalExpenses() {
        return calculateMonthlyTotalsByType(
          this.categoryData,
          this.months,
          false,
        );
      },

      totalExpensesSum() {
        return Object.values(this.monthlyTotalExpenses).reduce(
          (a, b) => a + b,
          0,
        );
      },

      totalExpensesAvg() {
        const n = this.months.length || 1;
        return round2(this.totalExpensesSum / n);
      },

      /** @returns {Object<string, number>} Monthly total income amounts keyed by YYYY-MM */
      monthlyTotalIncome() {
        return calculateMonthlyTotalsByType(
          this.categoryData,
          this.months,
          true,
        );
      },

      totalIncomeSum() {
        return Object.values(this.monthlyTotalIncome).reduce(
          (a, b) => a + b,
          0,
        );
      },

      totalIncomeAvg() {
        const n = this.months.length || 1;
        return round2(this.totalIncomeSum / n);
      },

      /** @returns {Object<string, number>} Monthly balance (income - expenses) keyed by YYYY-MM */
      monthlyBalance() {
        const balance = {};
        this.months.forEach((m) => {
          balance[m] =
            (this.monthlyTotalIncome[m] || 0) -
            (this.monthlyTotalExpenses[m] || 0);
        });
        return balance;
      },

      balanceSum() {
        return this.totalIncomeSum - this.totalExpensesSum;
      },

      balanceAvg() {
        const n = this.months.length || 1;
        return round2(this.balanceSum / n);
      },
    },

    watch: {
      transactions(newVal) {
        // Always clear cache to ensure fresh calculation
        this.cachedCategoryData = null;

        // Save aggregated results to sessionStorage after Vue recalculates
        if (newVal && newVal.length > 0) {
          this.$nextTick(() => {
            this.saveBreakdownCache();
          });
        }
      },
    },

    methods: {
      getCategoryLink(categoryId) {
        return window.route('categories.edit', {
          category: categoryId,
        });
      },

      emitDrillDown(month, categoryIds) {
        const [year, mon] = month.split('-').map(Number);
        const lastDay = new Date(year, mon, 0).getDate();
        const dateFrom = `${year}-${String(mon).padStart(2, '0')}-01`;
        const dateTo = `${year}-${String(mon).padStart(2, '0')}-${String(lastDay).padStart(2, '0')}`;

        this.$emit('drill-down', {
          dateFrom,
          dateTo,
          categories: [...new Set(categoryIds)].map((id) => String(id)),
        });
      },

      __,

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
              depositTotal: catData[key].depositTotal,
              withdrawalTotal: catData[key].withdrawalTotal,
              rawName: catData[key].rawName,
              parentName: catData[key].parentName,
              parentId: catData[key].parentId,
            };
          });

          sessionStorage.setItem(
            'yaffa_breakdown_cache',
            JSON.stringify({
              key: buildBreakdownCacheKey(),
              categoryData: serializable,
            }),
          );
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
              depositTotal: categoryData[k].depositTotal,
              withdrawalTotal: categoryData[k].withdrawalTotal,
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
       * TODO: make this locale-aware
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
      formatAmount(value, isIncome = null) {
        if (value === 0) return '—';

        const normalized =
          isIncome === null ? value : isIncome ? value : -value;
        const sign = normalized > 0 ? '+' : '-';
        return `${sign} ${toFormattedCurrency(
          round2(Math.abs(normalized)),
          this.locale,
          this.baseCurrency,
        )}`;
      },

      /**
       * Format a cell value as currency or percentage depending on the toggle.
       * @param {number} value - The cell amount
       * @param {number} monthTotal - Total expenses for that month (used for percentage mode)
       * @returns {string}
       */
      formatCell(value, monthTotal, isIncome = null) {
        if (value === 0) return '—';

        const normalized =
          isIncome === null ? value : isIncome ? value : -value;

        if (this.showPercentages && monthTotal > 0) {
          return `${((Math.abs(normalized) / monthTotal) * 100).toFixed(1)}%`;
        }

        const sign = normalized > 0 ? '+' : '-';
        return `${sign} ${toFormattedCurrency(
          round2(Math.abs(normalized)),
          this.locale,
          this.baseCurrency,
        )}`;
      },

      /**
       * Return a CSS class for deviation highlighting based on percentage
       * deviation from the category's average across non-zero months.
       * For expenses: above average = red (bad), below = green (good).
       * For income: above average = green (good), below = red (bad).
       * Requires at least 3 non-zero months to activate.
       *
       * @param {number} value - The cell amount
       * @param {number} avg - Category average across non-zero months
       * @param {number} nonZeroCount - Number of months with non-zero values
       * @param {boolean} isIncome - Whether this is an income category
       * @returns {string} CSS class name or empty string
       */
      deviationClass(value, avg, nonZeroCount, isIncome) {
        return calculateDeviationClass(value, avg, nonZeroCount, isIncome, {
          level1: DEVIATION_LEVEL_1,
          level2: DEVIATION_LEVEL_2,
          level3: DEVIATION_LEVEL_3,
        });
      },

      toFormattedCurrency,
    },
  };
</script>

<style scoped lang="scss">
  //@import './ReportingCanvas-FindTransactions-MonthlyBreakdown';
  @import '@coreui/coreui/scss/functions';
  @import '../../../sass/_variables';

  .reporting-monthly-breakdown {
    /* Section colors - computed from SCSS variables */
    --rb-blue-100: #{$blue-100};
    --rb-blue-700: #{$blue-700};
    --rb-orange-100: #{$orange-100};
    --rb-orange-700: #{$orange-700};
    --rb-green-100: #{$green-100};
    --rb-green-200: #{$green-200};
    --rb-green-300: #{$green-300};
    --rb-green-700: #{$green-700};
    --rb-teal-100: #{$teal-100};
    --rb-teal-700: #{$teal-700};
    --rb-purple-100: #{$purple-100};
    --rb-purple-700: #{$purple-700};
    --rb-pink-100: #{$pink-100};
    --rb-pink-700: #{$pink-700};
    --rb-yellow-100: #{$yellow-100};
    --rb-yellow-700: #{$yellow-700};
    --rb-cyan-100: #{$cyan-100};
    --rb-cyan-700: #{$cyan-700};
    --rb-red-100: #{$red-100};
    --rb-red-200: #{$red-200};
    --rb-red-300: #{$red-300};
    --rb-gray-100: #{$gray-100};
    --rb-gray-200: #{$gray-200};
    --rb-gray-400: #{$gray-400};
    --rb-gray-500: #{$gray-500};
    --rb-gray-700: #{$gray-700};
    --rb-gray-800: #{$gray-800};
  }

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
    background: white;
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

  .breakdown-table tbody tr.s-section-0 > td {
    background-color: var(--rb-blue-100);
    color: var(--rb-blue-700);
  }
  .breakdown-table tbody tr.s-section-1 > td {
    background-color: var(--rb-orange-100);
    color: var(--rb-orange-700);
  }
  .breakdown-table tbody tr.s-section-2 > td {
    background-color: var(--rb-green-100);
    color: var(--rb-green-700);
  }
  .breakdown-table tbody tr.s-section-3 > td {
    background-color: var(--rb-teal-100);
    color: var(--rb-teal-700);
  }
  .breakdown-table tbody tr.s-section-4 > td {
    background-color: var(--rb-purple-100);
    color: var(--rb-purple-700);
  }
  .breakdown-table tbody tr.s-section-5 > td {
    background-color: var(--rb-pink-100);
    color: var(--rb-pink-700);
  }
  .breakdown-table tbody tr.s-section-6 > td {
    background-color: var(--rb-yellow-100);
    color: var(--rb-yellow-700);
  }
  .breakdown-table tbody tr.s-section-7 > td {
    background-color: var(--rb-cyan-100);
    color: var(--rb-cyan-700);
  }
  .breakdown-table tbody tr.s-other > td {
    background-color: var(--rb-gray-100);
    color: var(--rb-gray-700);
  }
  .breakdown-table tbody tr.s-summary > td {
    background-color: var(--rb-gray-200);
    color: var(--rb-gray-800);
  }

  .breakdown-table tbody tr.subtotal-row > td {
    font-weight: 700;
    border-top: 2px solid var(--rb-gray-500);
    background-color: var(--rb-gray-100);
    padding-bottom: 0.75rem;
  }

  .subtotal-row .sticky-col {
    background: var(--rb-gray-100);
  }

  .breakdown-table tbody tr.grand-row > td {
    font-weight: 700;
    font-size: 1.05em;
    border-top: 2px solid var(--rb-gray-800);
    background-color: var(--rb-gray-200);
  }

  .grand-row .sticky-col {
    background: var(--rb-gray-200);
  }

  .zero {
    color: var(--rb-gray-400);
    font-size: 0.85em;
  }

  .cell-link {
    color: inherit;
    text-decoration: none;
  }

  .cell-link:hover {
    text-decoration: underline;
    color: var(--rb-blue-700);
  }

  .spacer-row {
    height: 8px;
    border: 0 !important;
    background: transparent !important;
  }

  /* Deviation highlighting — high specificity to override Bootstrap table-hover */
  .table-hover > tbody > tr > .bg-deviation-high-1,
  .bg-deviation-high-1 {
    background-color: var(--rb-red-100);
  }
  .table-hover > tbody > tr > .bg-deviation-high-2,
  .bg-deviation-high-2 {
    background-color: var(--rb-red-200);
  }
  .table-hover > tbody > tr > .bg-deviation-high-3,
  .bg-deviation-high-3 {
    background-color: var(--rb-red-300);
  }
  .table-hover > tbody > tr > .bg-deviation-low-1,
  .bg-deviation-low-1 {
    background-color: var(--rb-green-100);
  }
  .table-hover > tbody > tr > .bg-deviation-low-2,
  .bg-deviation-low-2 {
    background-color: var(--rb-green-200);
  }
  .table-hover > tbody > tr > .bg-deviation-low-3,
  .bg-deviation-low-3 {
    background-color: var(--rb-green-300);
  }
</style>
