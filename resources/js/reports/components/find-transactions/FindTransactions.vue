<template>
  <div class="row">
    <div :class="sidebarCollapsed ? 'd-none' : 'col-sm-3'">
      <div class="card mb-3" id="findTransactionsActionsCard">
        <div class="card-header">
          <div class="card-title">
            {{ __('Actions') }}
          </div>
        </div>
        <ul class="list-group list-group-flush">
          <li
            class="list-group-item d-flex justify-content-between align-items-center"
          >
            <span>
              {{ __('Update') }}
            </span>
            <button
              name="reload"
              type="button"
              class="btn btn-sm btn-primary"
              :disabled="busy || !ready"
              @click="getTransactions"
            >
              <i class="fas fa-sync-alt"></i>
            </button>
          </li>
        </ul>
      </div>

      <date-range-selector
        :initial-date-from="dateFrom"
        :initial-date-to="dateTo"
        @update="onUpdateDateRange"
      ></date-range-selector>

      <find-transaction-select-card
        property="category"
        title="Category"
        placeholder="Select category"
        search-api-path="/api/v1/categories"
        search_label_field="full_name"
        details-api-path="/api/v1/categories/#id#"
        details-label-field="full_name"
        :preset-item-ids="selectedCategories"
        @update="onUpdateCategory($event)"
        @preset-ready="setReadyFlag($event)"
      ></find-transaction-select-card>

      <find-transaction-select-card
        search-api-path="/api/v1/payees"
        property="payee"
        title="Payee"
        placeholder="Select payee"
        details-api-path="/api/v1/payees/#id#"
        :preset-item-ids="selectedPayees"
        @update="onUpdatePayee($event)"
        @preset-ready="setReadyFlag($event)"
      ></find-transaction-select-card>

      <find-transaction-select-card
        search-api-path="/api/v1/accounts"
        property="account"
        title="Account"
        placeholder="Select account"
        details-api-path="/api/v1/accounts/#id#"
        :preset-item-ids="selectedAccounts"
        @update="onUpdateAccount($event)"
        @preset-ready="setReadyFlag($event)"
      ></find-transaction-select-card>

      <find-transaction-select-card
        search-api-path="/api/v1/tags"
        search_label_field="text"
        property="tag"
        title="Tag"
        placeholder="Select tag"
        details-api-path="/api/v1/tags/#id#"
        :preset-item-ids="selectedTags"
        @update="onUpdateTag($event)"
        @preset-ready="setReadyFlag($event)"
      ></find-transaction-select-card>
    </div>
    <div :class="sidebarCollapsed ? 'col-sm-12' : 'col-sm-9'">
      <div class="card">
        <div class="card-header d-flex align-items-center">
          <button
            type="button"
            class="btn btn-sm btn-outline-secondary me-2"
            @click="sidebarCollapsed = !sidebarCollapsed"
            :title="
              sidebarCollapsed ? __('Expand sidebar') : __('Collapse sidebar')
            "
          >
            <i
              :class="
                sidebarCollapsed ? 'fas fa-angles-right' : 'fas fa-angles-left'
              "
            ></i>
          </button>
          <ul class="nav nav-tabs card-header-tabs transactions-tabs-offset">
            <li class="nav-item">
              <button
                class="nav-link active"
                id="nav-summary"
                data-coreui-toggle="tab"
                data-coreui-target="#tab-summary"
                type="button"
                role="tab"
                aria-controls="tab-summary"
                aria-selected="true"
              >
                {{ __('Summary') }}
              </button>
            </li>
            <li class="nav-item">
              <button
                class="nav-link"
                id="nav-transaction-list"
                data-coreui-toggle="tab"
                data-coreui-target="#tab-transaction-list"
                type="button"
                role="tab"
                aria-controls="tab-transaction-list"
                aria-selected="false"
              >
                {{ __('List of transactions') }}
              </button>
            </li>
            <li class="nav-item">
              <button
                class="nav-link"
                id="nav-timeline-charts"
                data-coreui-toggle="tab"
                data-coreui-target="#tab-timeline-charts"
                type="button"
                role="tab"
                aria-controls="tab-timeline-charts"
                aria-selected="false"
              >
                {{ __('Timeline charts') }}
              </button>
            </li>
            <li class="nav-item">
              <button
                class="nav-link"
                id="nav-category-charts"
                data-coreui-toggle="tab"
                data-coreui-target="#tab-category-charts"
                type="button"
                role="tab"
                aria-controls="tab-category-charts"
                aria-selected="false"
              >
                {{ __('Category charts') }}
              </button>
            </li>
            <li class="nav-item">
              <button
                class="nav-link"
                id="nav-monthly-breakdown"
                data-coreui-toggle="tab"
                data-coreui-target="#tab-monthly-breakdown"
                type="button"
                role="tab"
                aria-controls="tab-monthly-breakdown"
                aria-selected="false"
              >
                {{ __('Monthly breakdown') }}
              </button>
            </li>
          </ul>
        </div>

        <div class="card-body">
          <div class="tab-content">
            <div
              class="tab-pane fade show active"
              id="tab-summary"
              role="tabpanel"
              aria-labelledby="nav-summary"
              tabindex="0"
            >
              <reporting-canvas-summary
                :transactions="transactions"
                :busy="busy"
              ></reporting-canvas-summary>
            </div>
            <div
              class="tab-pane fade"
              id="tab-transaction-list"
              role="tabpanel"
              aria-labelledby="nav-transaction-list"
              tabindex="1"
            >
              <reporting-canvas-transaction-list
                :transactions="transactions"
                :busy="busy"
                :drill-down-filter="drillDownFilter"
                :is-active="activeTab === 'transaction-list'"
                @return-to-monthly-breakdown="returnToMonthlyBreakdown"
                @clear-drill-down-filter="clearDrillDownFilter"
                @transaction-deleted="onTransactionDeleted"
              ></reporting-canvas-transaction-list>
            </div>
            <div
              class="tab-pane fade"
              id="tab-timeline-charts"
              role="tabpanel"
              aria-labelledby="nav-timeline-charts"
              tabindex="3"
            >
              <reporting-canvas-timeline
                :transactions="transactions"
                :busy="busy"
              ></reporting-canvas-timeline>
            </div>
            <div
              class="tab-pane fade"
              id="tab-category-charts"
              role="tabpanel"
              aria-labelledby="nav-category-charts"
              tabindex="4"
            >
              <reporting-canvas-categories
                :transactions="transactions"
                :busy="busy"
              ></reporting-canvas-categories>
            </div>
            <div
              class="tab-pane fade"
              id="tab-monthly-breakdown"
              role="tabpanel"
              aria-labelledby="nav-monthly-breakdown"
              tabindex="5"
            >
              <reporting-canvas-monthly-breakdown
                :transactions="transactions"
                :busy="busy"
                :is-drill-down="!!drillDownFilter"
                @drill-down="onMonthlyBreakdownDrillDown"
              ></reporting-canvas-monthly-breakdown>
            </div>
          </div>
        </div>
      </div>
    </div>

    <transaction-show-modal></transaction-show-modal>
  </div>
</template>

<script>
  import { __ } from '@/shared/lib/i18n';
  import { processTransaction } from '@/shared/lib/helpers';
  import { buildFilterCacheKey, buildBreakdownCacheKey } from './helpers';
  import * as toastHelpers from '@/shared/lib/toast';
  import FindTransactionSelectCard from './FindTransactionSelectCard.vue';
  import DateRangeSelector from '@/shared/ui/date/DateRangeSelector.vue';
  import ReportingCanvasFindTransactionsCategoryDetails from '../widgets/ReportingCanvas-FindTransactions-CategoryDetails.vue';
  import ReportingCanvasFindTransactionsSummary from '../widgets/ReportingCanvas-FindTransactions-Summary.vue';
  import ReportingCanvasFindTransactionsTimeline from '../widgets/ReportingCanvas-FindTransactions-Timeline.vue';
  import ReportingCanvasFindTransactionsMonthlyBreakdown from '../widgets/ReportingCanvas-FindTransactions-MonthlyBreakdown.vue';
  import ReportingCanvasFindTransactionsTransactionList from '../widgets/ReportingCanvas-FindTransactions-TransactionList.vue';
  import TransactionShowModal from '@/transactions/components/display/Modal.vue';

  export default {
    name: 'FindTransactions',
    components: {
      'find-transaction-select-card': FindTransactionSelectCard,
      'transaction-show-modal': TransactionShowModal,
      'date-range-selector': DateRangeSelector,
      'reporting-canvas-categories':
        ReportingCanvasFindTransactionsCategoryDetails,
      'reporting-canvas-summary': ReportingCanvasFindTransactionsSummary,
      'reporting-canvas-timeline': ReportingCanvasFindTransactionsTimeline,
      'reporting-canvas-monthly-breakdown':
        ReportingCanvasFindTransactionsMonthlyBreakdown,
      'reporting-canvas-transaction-list':
        ReportingCanvasFindTransactionsTransactionList,
    },
    data() {
      const urlParams = new URLSearchParams(window.location.search);
      return {
        busy: false,
        ready: false,
        sidebarCollapsed: false,
        activeTab: 'summary',
        dateFrom: urlParams.get('date_from') || null,
        dateTo: urlParams.get('date_to') || null,
        selectedAccounts: this.getUrlParams('accounts'),
        selectedCategories: this.getUrlParams('categories'),
        selectedPayees: this.getUrlParams('payees'),
        selectedTags: this.getUrlParams('tags'),
        returnTo: this.sanitizeReturnTo(urlParams.get('return_to')),
        initialTab: urlParams.get('tab') || null,
        skippedTransactionLoad: false,
        drillDownFilter: null,
        presetsReady: {
          category: false,
          payee: false,
          account: false,
          tag: false,
        },
        transactions: [],
      };
    },
    methods: {
      setReadyFlag(event) {
        this.presetsReady[event] = true;
        this.ready = Object.values(this.presetsReady).every(
          (item) => item === true,
        );
      },
      onUpdateDateRange(event) {
        this.drillDownFilter = null;
        this.dateFrom = event.dateFrom;
        this.dateTo = event.dateTo;
        this.rebuildUrl();
      },
      onUpdateCategory(event) {
        this.drillDownFilter = null;
        this.selectedCategories = event;
        this.rebuildUrl();
      },
      onUpdatePayee(event) {
        this.drillDownFilter = null;
        this.selectedPayees = event;
        this.rebuildUrl();
      },
      onUpdateAccount(event) {
        this.drillDownFilter = null;
        this.selectedAccounts = event;
        this.rebuildUrl();
      },
      onUpdateTag(event) {
        this.drillDownFilter = null;
        this.selectedTags = event;
        this.rebuildUrl();
      },
      onMonthlyBreakdownDrillDown(event) {
        // Keep original query context and apply a lightweight in-memory filter for list view.
        this.drillDownFilter = {
          month: event.dateFrom.slice(0, 7),
          categories: [
            ...new Set((event.categories || []).map((id) => String(id))),
          ],
        };

        this.rebuildUrl('transaction-list');
        if (this.skippedTransactionLoad && this.transactions.length === 0) {
          this.skippedTransactionLoad = false;
          this.getTransactions({ keepDrillDown: true });
        }

        this.$nextTick(() => {
          const tabButton = this.$el.querySelector('#nav-transaction-list');
          if (tabButton) {
            tabButton.click();
          }
        });
      },
      returnToMonthlyBreakdown() {
        this.$nextTick(() => {
          const tabButton = this.$el.querySelector('#nav-monthly-breakdown');
          if (tabButton) {
            tabButton.click();
          }
        });
      },
      clearDrillDownFilter() {
        if (!this.drillDownFilter) {
          return;
        }

        this.drillDownFilter = null;
        this.rebuildUrl('transaction-list', this.returnTo);
      },
      rebuildUrl(tab = null, returnTo = null) {
        let params = [];

        // Date from
        if (this.dateFrom) {
          params.push('date_from=' + this.dateFrom);
        }

        // Date to
        if (this.dateTo) {
          params.push('date_to=' + this.dateTo);
        }

        // Accounts
        const accounts = this.selectedAccounts.map(
          (item) => 'accounts[]=' + item,
        );
        params.push(...accounts);

        // Categories
        const categories = this.selectedCategories.map(
          (item) => 'categories[]=' + item,
        );
        params.push(...categories);

        // Payees
        const payees = this.selectedPayees.map((item) => 'payees[]=' + item);
        params.push(...payees);

        // Tags
        const tags = this.selectedTags.map((item) => 'tags[]=' + item);
        params.push(...tags);

        if (tab) {
          params.push('tab=' + encodeURIComponent(tab));
        }

        if (returnTo) {
          params.push('return_to=' + encodeURIComponent(returnTo));
        }

        window.history.pushState(
          '',
          '',
          window.location.origin +
            window.location.pathname +
            '?' +
            params.join('&'),
        );
      },

      getCacheKey() {
        return buildFilterCacheKey({
          date_from: this.dateFrom,
          date_to: this.dateTo,
          accounts: this.selectedAccounts,
          categories: this.selectedCategories,
          payees: this.selectedPayees,
          tags: this.selectedTags,
        });
      },

      loadFromCache() {
        try {
          const cached = sessionStorage.getItem('yaffa_transactions_cache');
          if (!cached) return false;
          const { key, data } = JSON.parse(cached);
          if (key !== this.getCacheKey()) return false;
          this.transactions = data.map(processTransaction);
          return true;
        } catch (e) {
          console.warn('Failed to load transactions from cache:', e);
          return false;
        }
      },

      saveToCache(data) {
        try {
          sessionStorage.setItem(
            'yaffa_transactions_cache',
            JSON.stringify({
              key: this.getCacheKey(),
              data: data,
            }),
          );
        } catch (e) {
          console.warn('Failed to save transactions to cache:', e);
        }
      },

      hasBreakdownCache() {
        try {
          const cached = sessionStorage.getItem('yaffa_breakdown_cache');
          if (!cached) return false;
          const { key } = JSON.parse(cached);
          return key === buildBreakdownCacheKey();
        } catch (e) {
          console.warn('Failed to check breakdown cache:', e);
          return false;
        }
      },

      removeTransactionFromCache(transactionId) {
        try {
          const cached = sessionStorage.getItem('yaffa_transactions_cache');
          if (!cached) {
            return;
          }

          const payload = JSON.parse(cached);
          if (!Array.isArray(payload?.data)) {
            return;
          }

          payload.data = payload.data.filter(
            (transaction) => Number(transaction.id) !== Number(transactionId),
          );

          sessionStorage.setItem(
            'yaffa_transactions_cache',
            JSON.stringify(payload),
          );
        } catch (e) {
          console.warn('Failed to update transactions cache:', e);
        }
      },

      invalidateBreakdownCache() {
        try {
          sessionStorage.removeItem('yaffa_breakdown_cache');
        } catch (e) {
          console.warn('Failed to invalidate breakdown cache:', e);
        }
      },

      onTransactionDeleted(transactionId) {
        const previousCount = this.transactions.length;
        this.transactions = this.transactions.filter(
          (transaction) => Number(transaction.id) !== Number(transactionId),
        );

        if (this.transactions.length === previousCount) {
          return;
        }

        this.removeTransactionFromCache(transactionId);
        this.invalidateBreakdownCache();
      },

      getTransactions(options = null) {
        const keepDrillDown = !!(options && options.keepDrillDown === true);
        this.busy = true;
        if (!keepDrillDown) {
          this.drillDownFilter = null;
        }

        window.axios
          .get('/api/v1/transactions', {
            params: {
              date_from: this.dateFrom,
              date_to: this.dateTo,
              accounts: this.selectedAccounts,
              categories: this.selectedCategories,
              payees: this.selectedPayees,
              tags: this.selectedTags,
            },
          })
          .then((response) => {
            // Only cache when this is the original query, not a drill-down
            if (!this.returnTo) {
              this.saveToCache(response.data.data);
            }
            this.transactions = response.data.data.map(processTransaction);
          })
          .catch((error) => {
            toastHelpers.showErrorToast(
              __('Error getting transactions: :error', {
                error: error,
              }),
            );
          })
          .finally(() => {
            this.busy = false;
          });
      },

      sanitizeReturnTo(value) {
        if (!value) return null;
        // Only allow relative paths starting with /
        if (value.startsWith('/') && !value.startsWith('//')) return value;
        return null;
      },

      /**
       * This helper function is intended to provide flexibility in getting URL parameters, in terms of various array formats.
       * E.g. if paramName is 'tags', it should return anything that is in the URL like: 'tags[]' or 'tags[0]'
       *
       * @param paramName
       * @returns {string[]} Array of URL parameters
       */
      getUrlParams(paramName) {
        const urlParams = new URLSearchParams(window.location.search);
        const regex = new RegExp(`^${paramName}\\[(\\d)?\\]$`);

        let params = [];

        urlParams.forEach((value, key) => {
          if (regex.test(key)) {
            params.push(value);
          }
        });

        return params;
      },
      __,
    },

    watch: {
      // When all preselected filters are ready, get the transactions
      ready: function (newReady) {
        if (newReady) {
          // When returning to monthly-breakdown, check if the breakdown component
          // has its own lightweight cache — skip heavy transaction cache parsing
          if (
            this.initialTab === 'monthly-breakdown' &&
            this.hasBreakdownCache()
          ) {
            this.skippedTransactionLoad = true;
            return;
          }
          if (this.initialTab && this.loadFromCache()) {
            return;
          }
          this.getTransactions();
        }
      },
    },

    mounted() {
      // Handle tab switching to keep tab state in sync with URL and loading behavior
      this._allTabs = Array.from(
        this.$el.querySelectorAll('[data-coreui-toggle="tab"]'),
      );
      this._onTabShown = (event) => {
        const tabId = (event.target.getAttribute('id') || '').replace(
          /^nav-/,
          '',
        );
        this.activeTab = tabId || 'summary';
        const targetId = event.target.getAttribute('data-coreui-target');

        // Keep tab selection reflected in URL
        this.rebuildUrl(tabId || null, this.returnTo);

        // Lazily load all transactions if they were skipped for the breakdown tab
        if (targetId !== '#tab-monthly-breakdown') {
          if (this.skippedTransactionLoad && this.transactions.length === 0) {
            this.skippedTransactionLoad = false;
            this.getTransactions();
          }
        } else if (this.drillDownFilter) {
          this.drillDownFilter = null;
          this.rebuildUrl('monthly-breakdown');
        }
      };
      this._allTabs.forEach((tab) =>
        tab.addEventListener('shown.coreui.tab', this._onTabShown),
      );

      // Auto-switch to requested tab (e.g. from monthly breakdown drill-down)
      if (this.initialTab) {
        this.$nextTick(() => {
          const tabButton = this.$el.querySelector('#nav-' + this.initialTab);
          if (tabButton) {
            tabButton.click();
          }
        });
      } else {
        this.activeTab = 'summary';
      }

      this.ready = true;
    },

    beforeUnmount() {
      if (this._allTabs) {
        this._allTabs.forEach((tab) =>
          tab.removeEventListener('shown.coreui.tab', this._onTabShown),
        );
      }
    },
  };
</script>

<style scoped>
  .transactions-tabs-offset {
    margin-left: 10px;
  }
</style>
