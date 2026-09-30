<template>
  <div>
    <div class="row">
      <div class="col-12 col-lg-3">
        <category-overview-card
          :category="category"
          :overview="overview"
          :base-currency="baseCurrency"
        ></category-overview-card>

        <div id="categoryActionsCard" class="card mb-3">
          <div class="card-header">
            <div
              class="card-title collapse-control"
              data-coreui-toggle="collapse"
              data-coreui-target="#categoryActions"
            >
              <i class="fa fa-angle-down"></i>
              {{ __('Actions') }}
            </div>
          </div>
          <ul
            id="categoryActions"
            class="list-group list-group-flush collapse show"
          >
            <li
              v-for="action in actions"
              :key="action.id"
              class="list-group-item d-flex justify-content-between align-items-center"
            >
              {{ action.label }}
              <a
                :id="action.id"
                class="btn btn-sm btn-primary"
                :href="action.url"
                :title="action.label"
              >
                <i class="fa" :class="action.icon"></i>
              </a>
            </li>
          </ul>
        </div>

        <date-range-filter-card
          ref="dateRange"
          :initial-preset="defaultDatePreset"
          @update="onUpdateDateRange"
        ></date-range-filter-card>

        <category-budgets-card
          :budgets="budgets"
          :base-currency="baseCurrency"
        ></category-budgets-card>
        <category-schedules-card
          :category-id="category.id"
        ></category-schedules-card>
        <category-related-card
          :category="category"
          :learning-entries="learningEntries"
        ></category-related-card>
      </div>

      <div class="col-12 col-lg-9">
        <transaction-report-tabs
          :transactions="transactions"
          :busy="busy"
          :tabs="tabs"
          :matching-items-only="true"
          :category-ids="categoryIds"
          :use-breakdown-cache="false"
          @drill-down="onDrillDown"
          @transaction-deleted="onTransactionDeleted"
        ></transaction-report-tabs>
      </div>
    </div>

    <transaction-show-modal></transaction-show-modal>
  </div>
</template>

<script>
  import { __ } from '@/shared/lib/i18n';
  import { processTransaction } from '@/shared/lib/helpers';
  import * as toastHelpers from '@/shared/lib/toast';
  import DateRangeFilterCard from '@/shared/ui/date/DateRangeFilterCard.vue';
  import TransactionReportTabs from '@/shared/ui/reports/TransactionReportTabs.vue';
  import TransactionShowModal from '@/transactions/components/display/Modal.vue';
  import CategoryBudgetsCard from './CategoryBudgetsCard.vue';
  import CategoryOverviewCard from './CategoryOverviewCard.vue';
  import CategoryRelatedCard from './CategoryRelatedCard.vue';
  import CategorySchedulesCard from './CategorySchedulesCard.vue';

  export default {
    name: 'CategoryShowPage',
    components: {
      CategoryBudgetsCard,
      CategoryOverviewCard,
      CategoryRelatedCard,
      CategorySchedulesCard,
      DateRangeFilterCard,
      TransactionReportTabs,
      TransactionShowModal,
    },
    data() {
      return {
        category: window.category,
        overview: window.overview,
        budgets: window.budgets,
        learningEntries: window.learningEntries,
        baseCurrency: window.baseCurrency,
        dateFrom: null,
        dateTo: null,
        busy: false,
        transactions: [],
        requestCounter: 0,
      };
    },
    computed: {
      // The category split and waterfall are meaningless for a single category
      tabs() {
        return [
          'summary',
          'transaction-list',
          'timeline-charts',
          'monthly-breakdown',
        ];
      },
      // "none" means: don't load data until the user picks a range
      defaultDatePreset() {
        return window.YAFFA.userSettings.account_details_date_range || 'none';
      },
      // The API already includes the children of a requested parent
      categoryIds() {
        // The widgets compare ids as strings
        return [
          this.category.id,
          ...this.category.children.map((c) => c.id),
        ].map(String);
      },
      actions() {
        return [
          {
            id: 'categoryEditButton',
            label: __('Edit category'),
            url: this.route('categories.edit', { category: this.category.id }),
            icon: 'fa-edit',
          },
          {
            id: 'categoryMergeButton',
            label: __('Merge into an other category'),
            url: this.route('categories.merge.form', {
              categorySource: this.category.id,
            }),
            icon: 'fa-random',
          },
          {
            id: 'categoryFindButton',
            label: __('Find transactions'),
            url: this.route('reports.transactions', {
              categories: [this.category.id],
            }),
            icon: 'fa-search',
          },
          {
            id: 'categoryBudgetChartButton',
            label: __('Budget chart'),
            url: this.route('reports.budgetchart', {
              categories: [this.category.id],
            }),
            icon: 'fa-chart-line',
          },
        ];
      },
    },
    methods: {
      onUpdateDateRange(event) {
        this.dateFrom = event.dateFrom;
        this.dateTo = event.dateTo;
        this.getTransactions();
      },
      getTransactions() {
        const requestId = ++this.requestCounter;
        this.busy = true;

        window.axios
          .get('/api/v1/transactions', {
            params: {
              categories: [this.category.id],
              date_from: this.dateFrom,
              date_to: this.dateTo,
            },
          })
          .then((response) => {
            // A newer range selection superseded this request
            if (requestId === this.requestCounter) {
              this.transactions = response.data.data.map(processTransaction);
            }
          })
          .catch((error) => {
            toastHelpers.showErrorToast(
              __('Error getting transactions: :error', { error }),
            );
          })
          .finally(() => {
            if (requestId === this.requestCounter) {
              this.busy = false;
            }
          });
      },
      onTransactionDeleted(transactionId) {
        this.transactions = this.transactions.filter(
          (transaction) => Number(transaction.id) !== Number(transactionId),
        );
        this.refreshOverview();
      },
      // The lifetime figures come from the server, so a deletion needs a fresh copy
      refreshOverview() {
        window.axios
          .get(
            this.route('api.v1.categories.overview', {
              category: this.category.id,
            }),
          )
          .then((response) => {
            this.overview = response.data;
          })
          .catch(() => {
            toastHelpers.showErrorToast(
              __('Error while refreshing the overview'),
            );
          });
      },
      // No in-page drill-down here: hand over to Find transactions
      onDrillDown(event) {
        window.location.href = this.route('reports.transactions', {
          categories: event.categories?.length
            ? event.categories
            : [this.category.id],
          date_from: event.dateFrom,
          date_to: event.dateTo,
        });
      },
      __,
    },
  };
</script>
