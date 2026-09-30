<template>
  <div class="card left-control-panel-toggle-card">
    <div
      class="card-header d-flex align-items-center gap-2 left-control-panel-toggle-header"
    >
      <slot name="header-prefix"></slot>
      <ul class="nav nav-tabs card-header-tabs">
        <li v-for="tab in visibleTabs" :key="tab.id" class="nav-item">
          <button
            :id="`nav-${tab.id}`"
            class="nav-link"
            :class="{ active: tab.id === activeTab }"
            data-coreui-toggle="tab"
            :data-coreui-target="`#tab-${tab.id}`"
            type="button"
            role="tab"
            :aria-controls="`tab-${tab.id}`"
            :aria-selected="tab.id === activeTab ? 'true' : 'false'"
          >
            {{ tab.label }}
          </button>
        </li>
      </ul>
    </div>

    <div class="card-body">
      <div class="tab-content">
        <div
          v-if="hasTab('summary')"
          id="tab-summary"
          class="tab-pane fade"
          :class="{ 'show active': activeTab === 'summary' }"
          role="tabpanel"
          aria-labelledby="nav-summary"
          tabindex="0"
        >
          <transaction-summary
            :transactions="transactions"
            :busy="busy"
          ></transaction-summary>
        </div>
        <div
          v-if="hasTab('transaction-list')"
          id="tab-transaction-list"
          class="tab-pane fade"
          :class="{ 'show active': activeTab === 'transaction-list' }"
          role="tabpanel"
          aria-labelledby="nav-transaction-list"
          tabindex="1"
        >
          <div
            v-if="drillDownFilter"
            class="alert alert-warning d-flex flex-wrap align-items-center justify-content-between gap-2"
            role="alert"
          >
            <span>
              {{
                __(
                  'Showing a filtered subset of transactions from monthly breakdown drill-down.',
                )
              }}
            </span>
            <div class="d-flex gap-2">
              <button
                type="button"
                class="btn btn-sm btn-outline-secondary"
                @click="$emit('return-to-monthly-breakdown')"
              >
                {{ __('Return to monthly breakdown') }}
              </button>
              <button
                type="button"
                class="btn btn-sm btn-warning"
                @click="$emit('clear-drill-down-filter')"
              >
                {{ __('Clear additional filtering') }}
              </button>
            </div>
          </div>
          <transaction-table
            :transactions="listTransactions"
            :busy="busy"
            :is-active="activeTab === 'transaction-list'"
            @transaction-deleted="$emit('transaction-deleted', $event)"
          ></transaction-table>
        </div>
        <div
          v-if="hasTab('timeline-charts')"
          id="tab-timeline-charts"
          class="tab-pane fade"
          :class="{ 'show active': activeTab === 'timeline-charts' }"
          role="tabpanel"
          aria-labelledby="nav-timeline-charts"
          tabindex="3"
        >
          <transaction-timeline
            :transactions="transactions"
            :busy="busy"
          ></transaction-timeline>
        </div>
        <div
          v-if="hasTab('category-charts')"
          id="tab-category-charts"
          class="tab-pane fade"
          :class="{ 'show active': activeTab === 'category-charts' }"
          role="tabpanel"
          aria-labelledby="nav-category-charts"
          tabindex="4"
        >
          <category-details
            :transactions="transactions"
            :busy="busy"
            :matching-items-only="matchingItemsOnly"
            :category-ids="categoryIds"
            :tag-ids="tagIds"
          ></category-details>
        </div>
        <div
          v-if="hasTab('monthly-breakdown')"
          id="tab-monthly-breakdown"
          class="tab-pane fade"
          :class="{ 'show active': activeTab === 'monthly-breakdown' }"
          role="tabpanel"
          aria-labelledby="nav-monthly-breakdown"
          tabindex="5"
        >
          <monthly-breakdown
            :transactions="transactions"
            :busy="busy"
            :is-drill-down="!!drillDownFilter"
            :matching-items-only="matchingItemsOnly"
            :category-ids="categoryIds"
            :tag-ids="tagIds"
            @drill-down="$emit('drill-down', $event)"
          ></monthly-breakdown>
        </div>
        <div
          v-if="hasTab('waterfall')"
          id="tab-waterfall"
          class="tab-pane fade"
          :class="{ 'show active': activeTab === 'waterfall' }"
          role="tabpanel"
          aria-labelledby="nav-waterfall"
          tabindex="6"
        >
          <transaction-waterfall
            :transactions="transactions"
            :busy="busy"
            :matching-items-only="matchingItemsOnly"
            :category-ids="categoryIds"
            :tag-ids="tagIds"
          ></transaction-waterfall>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
  import { __ } from '@/shared/lib/i18n';
  import TransactionSummary from './TransactionSummary.vue';
  import TransactionTimeline from './TransactionTimeline.vue';
  import CategoryDetails from './CategoryDetails.vue';
  import MonthlyBreakdown from './MonthlyBreakdown.vue';
  import TransactionWaterfall from './TransactionWaterfall.vue';
  import TransactionTable from '@/shared/ui/datatable/TransactionTable.vue';

  const TAB_IDS = [
    'summary',
    'transaction-list',
    'timeline-charts',
    'category-charts',
    'monthly-breakdown',
    'waterfall',
  ];

  export default {
    name: 'TransactionReportTabs',
    components: {
      TransactionSummary,
      TransactionTimeline,
      CategoryDetails,
      MonthlyBreakdown,
      TransactionWaterfall,
      TransactionTable,
    },
    props: {
      transactions: { type: Array, required: true },
      busy: { type: Boolean, required: true },
      matchingItemsOnly: { type: Boolean, default: false },
      categoryIds: { type: Array, default: () => [] },
      tagIds: { type: Array, default: () => [] },
      // Which tabs to show, in the fixed order of TAB_IDS
      tabs: { type: Array, default: () => TAB_IDS },
      // Find transactions only: monthly breakdown drill-down, filters the list in memory
      drillDownFilter: { type: Object, default: null },
    },
    emits: [
      'drill-down',
      'transaction-deleted',
      'tab-shown',
      'return-to-monthly-breakdown',
      'clear-drill-down-filter',
    ],
    data() {
      return {
        activeTab: TAB_IDS.find((id) => this.tabs.includes(id)),
      };
    },
    computed: {
      visibleTabs() {
        const labels = {
          summary: __('Summary'),
          'transaction-list': __('List of transactions'),
          'timeline-charts': __('Timeline charts'),
          'category-charts': __('Category charts'),
          'monthly-breakdown': __('Monthly breakdown'),
          waterfall: __('Category waterfall'),
        };

        return TAB_IDS.filter((id) => this.tabs.includes(id)).map((id) => ({
          id,
          label: labels[id],
        }));
      },
      listTransactions() {
        if (!this.drillDownFilter) {
          return this.transactions;
        }

        const month = this.drillDownFilter.month;
        const categorySet = new Set(this.drillDownFilter.categories);

        return this.transactions.filter((transaction) => {
          if (!(transaction.date instanceof Date)) {
            return false;
          }

          if (transaction.year_month !== month) {
            return false;
          }

          const transactionCategories = transaction.categories || [];
          return transactionCategories.some(
            (category) => category && categorySet.has(String(category.id)),
          );
        });
      },
    },
    mounted() {
      this._tabButtons = Array.from(
        this.$el.querySelectorAll('[data-coreui-toggle="tab"]'),
      );
      this._onTabShown = (event) => {
        const tabId = event.target.getAttribute('id').replace(/^nav-/, '');
        this.activeTab = tabId;
        this.$emit('tab-shown', tabId);
      };
      this._tabButtons.forEach((button) =>
        button.addEventListener('shown.coreui.tab', this._onTabShown),
      );
    },
    beforeUnmount() {
      this._tabButtons.forEach((button) =>
        button.removeEventListener('shown.coreui.tab', this._onTabShown),
      );
    },
    methods: {
      hasTab(id) {
        return this.tabs.includes(id);
      },
      showTab(id) {
        this.$el.querySelector(`#nav-${id}`)?.click();
      },
      __,
    },
  };
</script>
