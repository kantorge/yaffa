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

    <div v-else-if="transactions.length === 0" class="text-muted">
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
              <td class="sticky-col category-name" :title="row.name">{{ row.name }}</td>
              <td
                v-for="m in months"
                :key="m"
                :class="deviationClass(row.values[m] || 0, row.avg, row.min, row.max, row.nonZeroCount)"
                class="text-end"
              >
                <a
                  v-if="(row.values[m] || 0) !== 0"
                  :href="drillDownUrl(m, row.categoryIds)"
                  class="cell-link"
                >
                  {{ formatCell(row.values[m] || 0, monthlyTotalExpenses[m]) }}
                </a>
                <span v-else class="zero">&mdash;</span>
              </td>
              <td class="text-end fw-semibold">
                {{ formatCell(row.total, totalExpensesSum) }}
              </td>
              <td class="text-end">
                {{ formatCell(row.avg, totalExpensesAvg) }}
              </td>
            </tr>

            <!-- Section subtotal -->
            <tr class="subtotal-row">
              <td class="sticky-col fw-bold">
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
                  {{ formatCell(section.subtotals[m] || 0, monthlyTotalExpenses[m]) }}
                </a>
                <span v-else class="zero">&mdash;</span>
              </td>
              <td class="text-end fw-bold">
                {{ formatCell(section.subtotalSum, totalExpensesSum) }}
              </td>
              <td class="text-end fw-bold">
                {{ formatCell(section.subtotalAvg, totalExpensesAvg) }}
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
            <td class="sticky-col fw-bold">{{ __('Total expenses') }}</td>
            <td v-for="m in months" :key="m" class="text-end fw-bold">
              {{ formatAmount(monthlyTotalExpenses[m] || 0) }}
            </td>
            <td class="text-end fw-bold">{{ formatAmount(totalExpensesSum) }}</td>
            <td class="text-end fw-bold">{{ formatAmount(totalExpensesAvg) }}</td>
          </tr>

          <tr class="grand-row">
            <td class="sticky-col fw-bold">{{ __('Total income') }}</td>
            <td v-for="m in months" :key="m" class="text-end fw-bold text-success">
              {{ formatAmount(monthlyTotalIncome[m] || 0) }}
            </td>
            <td class="text-end fw-bold text-success">{{ formatAmount(totalIncomeSum) }}</td>
            <td class="text-end fw-bold text-success">{{ formatAmount(totalIncomeAvg) }}</td>
          </tr>

          <tr class="grand-row">
            <td class="sticky-col fw-bold">{{ __('Balance') }}</td>
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
            <td class="sticky-col fw-bold">{{ __(section.title) }}</td>
            <td v-for="m in months" :key="m" class="text-end fw-bold">
              <span v-if="(section.subtotals[m] || 0) !== 0">
                {{ formatCell(section.subtotals[m] || 0, monthlyTotalExpenses[m]) }}
              </span>
              <span v-else class="zero">&mdash;</span>
            </td>
            <td class="text-end fw-bold">{{ formatCell(section.subtotalSum, totalExpensesSum) }}</td>
            <td class="text-end fw-bold">{{ formatCell(section.subtotalAvg, totalExpensesAvg) }}</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script>
import { __ as translator, toFormattedCurrency } from '../../helpers';

/**
 * Section definitions mapping default_assets.categories translation keys
 * to visual groups. Each section has a title (translation key), a CSS class
 * for colored headers, and a list of category keys from default_assets.php.
 *
 * @type {Array<{titleKey: string, cssClass: string, categoryKeys: string[]}>}
 */
const SECTION_DEFINITIONS = [
  {
    titleKey: 'Daily living expenses',
    cssClass: 's-living',
    categoryKeys: [
      'food', 'dining_out', 'groceries', 'clothing', 'personal_care',
      'medications', 'healthcare', 'doctor_visits', 'vision_and_dental_care',
      'fuel', 'parking', 'transportation', 'public_transport',
      'vehicle_maintenance', 'car_payments',
      'subscriptions', 'entertainment', 'entertainment_and_leisure',
      'events', 'hobbies_and_activities', 'vacation_and_travel',
      'household_products', 'home_improvements',
      'maintenance_and_repairs', 'repairs_maintenance',
      'pet_care', 'gifts_and_donations', 'books_and_supplies',
      'miscellaneous', 'credit_card',
      'school_and_university_fees', 'education_and_development',
      'courses_and_training',
    ],
  },
  {
    titleKey: 'Fixed obligations',
    cssClass: 's-obligations',
    categoryKeys: [
      'rent_and_mortgage', 'rent', 'housing',
      'utilities', 'electricity', 'water', 'water_sewer',
      'gas_and_heating', 'internet_cable',
      'insurance', 'car_insurance', 'home_insurance',
      'health_insurance', 'life_insurance', 'disability_insurance',
      'loans', 'personal_loans', 'student_loans',
      'debt_repayment', 'property_taxes', 'legal_fees', 'fines',
    ],
  },
  {
    titleKey: 'Savings and investments',
    cssClass: 's-savings',
    categoryKeys: [
      'mortgage_overpayments',
    ],
  },
  {
    titleKey: 'Income',
    cssClass: 's-income',
    categoryKeys: [
      'salary', 'main_job', 'bonuses', 'side_job',
      'freelance_work', 'rental_income', 'other_income',
      'government_benefits', 'social_assistance', 'child_allowance',
    ],
  },
];

/**
 * Build a lookup map from default_assets.categories keys to their translated names.
 * Uses window.YAFFA.translations to resolve translation keys, enabling locale-aware
 * category matching regardless of the user's language.
 *
 * @returns {Object<string, {fullKey: string, translated: string|null, matchNames: string[]}>}
 */
function buildCategoryKeyMap() {
  const translations = window.YAFFA?.translations || {};
  const map = {};

  // The default_assets.php keys are like "default_assets.categories.food" -> "Food"
  // But in the JSON translations, they might be stored differently.
  // The category names in the DB are stored as the translated value directly,
  // or as plain user-defined names. We need to match against both.

  // First, try to get translations for default_assets keys
  const defaultKeys = [
    'food', 'dining_out', 'groceries', 'clothing', 'personal_care',
    'medications', 'healthcare', 'doctor_visits', 'vision_and_dental_care',
    'fuel', 'parking', 'transportation', 'public_transport',
    'vehicle_maintenance', 'car_payments', 'subscriptions',
    'entertainment', 'entertainment_and_leisure', 'events',
    'hobbies_and_activities', 'vacation_and_travel',
    'household_products', 'home_improvements',
    'maintenance_and_repairs', 'repairs_maintenance',
    'pet_care', 'gifts_and_donations', 'books_and_supplies',
    'miscellaneous', 'credit_card', 'school_and_university_fees',
    'education_and_development', 'courses_and_training',
    'rent_and_mortgage', 'rent', 'housing', 'utilities', 'electricity',
    'water', 'water_sewer', 'gas_and_heating', 'internet_cable',
    'insurance', 'car_insurance', 'home_insurance',
    'health_insurance', 'life_insurance', 'disability_insurance',
    'loans', 'personal_loans', 'student_loans',
    'debt_repayment', 'property_taxes', 'legal_fees', 'fines',
    'mortgage_overpayments',
    'salary', 'main_job', 'bonuses', 'side_job',
    'freelance_work', 'rental_income', 'other_income',
    'government_benefits', 'social_assistance', 'child_allowance',
  ];

  for (const key of defaultKeys) {
    // The DB stores the name as the full translation key or translated value
    const fullKey = `default_assets.categories.${key}`;
    const translated = translations[fullKey] || null;

    // Store both the full key and translated name as possible matches
    map[key] = {
      fullKey,
      translated,
      // Build list of names that should match this key
      matchNames: [fullKey],
    };
    if (translated) {
      map[key].matchNames.push(translated);
    }
  }

  return map;
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
  },
  data() {
    return {
      showPercentages: false,
      baseCurrency: window.YAFFA.baseCurrency,
      locale: window.YAFFA.locale,
    };
  },
  computed: {
    /** @returns {string[]} Sorted unique YYYY-MM month strings extracted from transactions */
    months() {
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
     *
     * @returns {Object<string, {values: Object<string, number>, categoryIds: Set<number>, isIncome: boolean, rawName: string}>}
     */
    categoryData() {
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

            if (!data[catName]) {
              data[catName] = {
                values: {},
                categoryIds: new Set(),
                isIncome: isDeposit,
                rawName: item.category.name || catName,
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
     * Match categories to predefined sections using translation key lookups,
     * compute per-row totals/averages, section subtotals, and collect category IDs.
     * Unmatched categories are grouped into an "Other expenses" section.
     *
     * @returns {Array<{title: string, cssClass: string, rows: Array, subtotals: Object, subtotalSum: number, subtotalAvg: number, allCategoryIds: number[]}>}
     */
    computedSections() {
      const catKeyMap = buildCategoryKeyMap();
      const catData = this.categoryData;
      const months = this.months;
      const n = months.length || 1;

      // Build reverse map: categoryName (lowercase) -> sectionIndex
      const nameToSection = {};
      SECTION_DEFINITIONS.forEach((section, si) => {
        section.categoryKeys.forEach((key) => {
          const info = catKeyMap[key];
          if (info) {
            info.matchNames.forEach((name) => {
              nameToSection[name.toLowerCase()] = si;
            });
          }
        });
      });

      // Assign each category to a section
      const sectionCategories = SECTION_DEFINITIONS.map(() => []);
      const otherCategories = [];

      Object.keys(catData).forEach((catName) => {
        const entry = catData[catName];
        const lookupName = (entry.rawName || catName).toLowerCase();
        const lookupFullName = catName.toLowerCase();

        let sectionIdx = nameToSection[lookupName] ?? nameToSection[lookupFullName] ?? null;

        if (sectionIdx !== null) {
          sectionCategories[sectionIdx].push(catName);
        } else {
          otherCategories.push(catName);
        }
      });

      // Build section objects
      const sections = SECTION_DEFINITIONS.map((def, si) => {
        const rows = sectionCategories[si].map((catName) => {
          const entry = catData[catName];
          const values = entry.values;
          const total = months.reduce((sum, m) => sum + (values[m] || 0), 0);
          const nonZeroValues = months.map((m) => values[m] || 0).filter((v) => v > 0);
          const nonZeroCount = nonZeroValues.length;
          const avg = nonZeroCount > 0 ? total / n : 0;
          const min = nonZeroValues.length ? Math.min(...nonZeroValues) : 0;
          const max = nonZeroValues.length ? Math.max(...nonZeroValues) : 0;

          return {
            name: catName,
            values,
            total: Math.round(total * 100) / 100,
            avg: Math.round(avg * 100) / 100,
            min,
            max,
            nonZeroCount,
            categoryIds: Array.from(entry.categoryIds),
          };
        });

        // Sort by total descending
        rows.sort((a, b) => b.total - a.total);

        // Subtotals
        const subtotals = {};
        months.forEach((m) => {
          subtotals[m] = rows.reduce((sum, r) => sum + (r.values[m] || 0), 0);
        });
        const subtotalSum = Math.round(rows.reduce((sum, r) => sum + r.total, 0) * 100) / 100;
        const subtotalAvg = Math.round((subtotalSum / n) * 100) / 100;

        // All category IDs in this section
        const allCategoryIds = rows.flatMap((r) => r.categoryIds);

        return {
          title: def.titleKey,
          cssClass: def.cssClass,
          rows,
          subtotals,
          subtotalSum,
          subtotalAvg,
          allCategoryIds,
        };
      });

      // Add "Other" section if there are unmatched categories
      if (otherCategories.length > 0) {
        const rows = otherCategories.map((catName) => {
          const entry = catData[catName];
          const values = entry.values;
          const total = months.reduce((sum, m) => sum + (values[m] || 0), 0);
          const nonZeroValues = months.map((m) => values[m] || 0).filter((v) => v > 0);
          const nonZeroCount = nonZeroValues.length;
          const avg = nonZeroCount > 0 ? total / n : 0;
          const min = nonZeroValues.length ? Math.min(...nonZeroValues) : 0;
          const max = nonZeroValues.length ? Math.max(...nonZeroValues) : 0;

          return {
            name: catName,
            values,
            total: Math.round(total * 100) / 100,
            avg: Math.round(avg * 100) / 100,
            min,
            max,
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
        const subtotalAvg = Math.round((subtotalSum / n) * 100) / 100;
        const allCategoryIds = rows.flatMap((r) => r.categoryIds);

        sections.push({
          title: 'Other expenses',
          cssClass: 's-other',
          rows,
          subtotals,
          subtotalSum,
          subtotalAvg,
          allCategoryIds,
        });
      }

      return sections;
    },

    /** @returns {Object<string, number>} Monthly total expense amounts keyed by YYYY-MM */
    monthlyTotalExpenses() {
      const totals = {};
      this.computedSections.forEach((section) => {
        this.months.forEach((m) => {
          if (!totals[m]) totals[m] = 0;
          totals[m] += section.subtotals[m] || 0;
        });
      });
      return totals;
    },

    totalExpensesSum() {
      return this.computedSections.reduce((sum, s) => sum + s.subtotalSum, 0);
    },

    totalExpensesAvg() {
      const n = this.months.length || 1;
      return Math.round((this.totalExpensesSum / n) * 100) / 100;
    },

    /** @returns {Object<string, number>} Monthly total income amounts keyed by YYYY-MM */
    monthlyTotalIncome() {
      const totals = {};
      this.transactions.forEach((tx) => {
        if (!tx.date || !(tx.date instanceof Date)) return;
        if (tx.transaction_type_id !== 2) return;
        const month = tx.date.getFullYear() + '-' + String(tx.date.getMonth() + 1).padStart(2, '0');
        if (!totals[month]) totals[month] = 0;
        if (tx.transaction_items) {
          tx.transaction_items.forEach((item) => {
            totals[month] += Math.abs(item.amount_in_base || item.amount || 0);
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

  methods: {
    __: function (string, replace) {
      return translator(string, replace);
    },

    /**
     * Format a YYYY-MM month string as a short MM header.
     * @param {string} month - Month in YYYY-MM format
     * @returns {string} Month in MM format
     */
    formatMonthHeader(month) {
      return month.substring(5);
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
     * Return a CSS class for deviation highlighting based on where the value
     * falls relative to the category's min, avg, and max across months.
     * Color intensity scales proportionally within the [min, max] range.
     * Requires at least 3 non-zero months to activate.
     *
     * @param {number} value - The cell amount
     * @param {number} avg - Category average across months
     * @param {number} min - Minimum non-zero value across months
     * @param {number} max - Maximum non-zero value across months
     * @param {number} nonZeroCount - Number of months with non-zero values
     * @returns {string} CSS class name or empty string
     */
    deviationClass(value, avg, min, max, nonZeroCount) {
      if (nonZeroCount < 3 || value === 0 || avg === 0 || min === max) return '';

      if (value > avg) {
        const intensity = (max - avg) > 0 ? (value - avg) / (max - avg) : 0;
        if (intensity > 0.66) return 'bg-deviation-high-3';
        if (intensity > 0.33) return 'bg-deviation-high-2';
        return 'bg-deviation-high-1';
      }
      if (value < avg) {
        const intensity = (avg - min) > 0 ? (avg - value) / (avg - min) : 0;
        if (intensity > 0.66) return 'bg-deviation-low-3';
        if (intensity > 0.33) return 'bg-deviation-low-2';
        return 'bg-deviation-low-1';
      }
      return '';
    },

    /**
     * Build a URL to the transactions report filtered by month and category IDs.
     * @param {string} month - Month in YYYY-MM format
     * @param {number[]} categoryIds - Category IDs to filter by
     * @returns {string} URL with query parameters
     */
    drillDownUrl(month, categoryIds) {
      const [year, mon] = month.split('-').map(Number);
      const lastDay = new Date(year, mon, 0).getDate();
      const dateFrom = `${year}-${String(mon).padStart(2, '0')}-01`;
      const dateTo = `${year}-${String(mon).padStart(2, '0')}-${String(lastDay).padStart(2, '0')}`;

      const params = [`date_from=${dateFrom}`, `date_to=${dateTo}`];
      const uniqueIds = [...new Set(categoryIds)];
      uniqueIds.forEach((id) => params.push(`categories[]=${id}`));

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

.section-header td {
  font-size: 1.05em;
  padding: 6px 10px;
  border-radius: 2px;
}

.s-living td { background: #e3f2fd; color: #1565c0; }
.s-obligations td { background: #fff3e0; color: #e65100; }
.s-savings td { background: #e8f5e9; color: #2e7d32; }
.s-income td { background: #e0f2f1; color: #00695c; }
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
