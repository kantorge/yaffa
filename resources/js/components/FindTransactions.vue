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
        search-api-path="/api/assets/category"
        search_label_field="text"
        details-api-path="/api/assets/category/#id#"
        details-label-field="full_name"
        :preset-item-ids="selectedCategories"
        @update="onUpdateCategory($event)"
        @preset-ready="setReadyFlag($event)"
      ></find-transaction-select-card>

      <find-transaction-select-card
        search-api-path="/api/assets/payee"
        property="payee"
        title="Payee"
        placeholder="Select payee"
        details-api-path="/api/assets/payee/#id#"
        :preset-item-ids="selectedPayees"
        @update="onUpdatePayee($event)"
        @preset-ready="setReadyFlag($event)"
      ></find-transaction-select-card>

      <find-transaction-select-card
        search-api-path="/api/assets/account"
        property="account"
        title="Account"
        placeholder="Select account"
        details-api-path="/api/assets/account/#id#"
        :preset-item-ids="selectedAccounts"
        @update="onUpdateAccount($event)"
        @preset-ready="setReadyFlag($event)"
      ></find-transaction-select-card>

      <find-transaction-select-card
        search-api-path="/api/assets/tag"
        search_label_field="text"
        property="tag"
        title="Tag"
        placeholder="Select tag"
        details-api-path="/api/assets/tag/#id#"
        :preset-item-ids="selectedTags"
        @update="onUpdateTag($event)"
        @preset-ready="setReadyFlag($event)"
      ></find-transaction-select-card>
    </div>
    <div :class="sidebarCollapsed ? 'col-sm-12' : 'col-sm-9'" class="position-relative">
      <button
        type="button"
        class="transactions-sidebar-toggle"
        @click="sidebarCollapsed = !sidebarCollapsed"
        :title="sidebarCollapsed ? __('Expand sidebar') : __('Collapse sidebar')"
      >
        <i :class="sidebarCollapsed ? 'fas fa-angle-right' : 'fas fa-angle-left'"></i>
      </button>
      <div class="card">
        <div class="card-header d-flex align-items-center">
          <ul class="nav nav-tabs card-header-tabs">
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
              <table
                class="table table-bordered table-hover no-footer"
                ref="dataTable"
              ></table>
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
  import { __, processTransaction, buildFilterCacheKey, buildBreakdownCacheKey } from '../helpers';
  import * as toastHelpers from '../toast';
  import * as dataTableHelpers from './dataTableHelper';
  import FindTransactionSelectCard from './FindTransactionSelectCard.vue';
  import DateRangeSelector from './DateRangeSelector.vue';
  import ReportingCanvasFindTransactionsCategoryDetails from './ReportingWidgets/ReportingCanvas-FindTransactions-CategoryDetails.vue';
  import ReportingCanvasFindTransactionsSummary from './ReportingWidgets/ReportingCanvas-FindTransactions-Summary.vue';
  import ReportingCanvasFindTransactionsTimeline from './ReportingWidgets/ReportingCanvas-FindTransactions-Timeline.vue';
  import ReportingCanvasFindTransactionsMonthlyBreakdown from './ReportingWidgets/ReportingCanvas-FindTransactions-MonthlyBreakdown.vue';
  import TransactionShowModal from './../components/TransactionDisplay/Modal.vue';

  import 'datatables.net-bs5';

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
      'reporting-canvas-monthly-breakdown': ReportingCanvasFindTransactionsMonthlyBreakdown,
    },
    data() {
      const urlParams = new URLSearchParams(window.location.search);
      return {
        busy: false,
        ready: false,
        sidebarCollapsed: false,
        dataTable: null,
        dateFrom: urlParams.get('date_from') || null,
        dateTo: urlParams.get('date_to') || null,
        selectedAccounts: this.getUrlParams('accounts'),
        selectedCategories: this.getUrlParams('categories'),
        selectedPayees: this.getUrlParams('payees'),
        selectedTags: this.getUrlParams('tags'),
        returnTo: this.sanitizeReturnTo(urlParams.get('return_to')),
        initialTab: urlParams.get('tab') || null,
        cachedDataPending: false,
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
          categories: [...new Set((event.categories || []).map((id) => String(id)))],
        };

        this.rebuildUrl('transaction-list');
        if (this.skippedTransactionLoad && this.transactions.length === 0) {
          this.skippedTransactionLoad = false;
          this.getTransactions({ keepDrillDown: true });
        } else {
          this.cachedDataPending = true;
        }

        this.$nextTick(() => {
          const tabButton = this.$el.querySelector('#nav-transaction-list');
          if (tabButton) {
            tabButton.click();
          }
        });
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
          this.cachedDataPending = true;
          return true;
        } catch (e) {
          console.warn('Failed to load transactions from cache:', e);
          return false;
        }
      },

      getListTransactions() {
        if (!this.drillDownFilter) {
          return this.transactions;
        }

        const month = this.drillDownFilter.month;
        const categorySet = new Set(this.drillDownFilter.categories);

        return this.transactions.filter((tx) => {
          if (!(tx.date instanceof Date)) return false;
          const txMonth = `${tx.date.getFullYear()}-${String(tx.date.getMonth() + 1).padStart(2, '0')}`;
          if (txMonth !== month) return false;
          const txCategories = tx.categories || [];
          return txCategories.some((category) => category && categorySet.has(String(category.id)));
        });
      },

      redrawDataTable() {
        if (!this.dataTable) return;
        this.dataTable.clear();
        this.dataTable.rows.add(this.getListTransactions());
        this.dataTable.draw();
      },

      populateDataTable(force = false) {
        if (!force && !this.cachedDataPending) return;
        this.cachedDataPending = false;
        this.redrawDataTable();
      },

      isTransactionListActive() {
        if (!this.$el) return false;
        const transactionListTab = this.$el.querySelector('#tab-transaction-list');
        return !!transactionListTab && transactionListTab.classList.contains('active');
      },

      saveToCache(data) {
        try {
          sessionStorage.setItem('yaffa_transactions_cache', JSON.stringify({
            key: this.getCacheKey(),
            data: data,
          }));
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

      getTransactions(options = null) {
        const keepDrillDown = !!(options && options.keepDrillDown === true);
        this.busy = true;
        if (!keepDrillDown) {
          this.drillDownFilter = null;
        }

        window.axios
          .get('/api/transactions', {
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
          .then(() => {
            if (this.isTransactionListActive()) {
              this.redrawDataTable();
            } else {
              this.cachedDataPending = true;
            }
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

      /**
       * This helper function is intended to provide flexibility in getting URL parameters, in terms of various array formats.
       * E.g. if paramName is 'tags', it should return anything that is in the URL like: 'tags[]' or 'tags[0]'
       *
       * @param paramName
       * @returns {string[]} Array of URL parameters
       */
      sanitizeReturnTo(value) {
        if (!value) return null;
        // Only allow relative paths starting with /
        if (value.startsWith('/') && !value.startsWith('//')) return value;
        return null;
      },

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
          if (this.initialTab === 'monthly-breakdown' && this.hasBreakdownCache()) {
            this.skippedTransactionLoad = true;
            return;
          }
          if (this.initialTab && this.loadFromCache()) {
            return;
          }
          this.getTransactions();
        }
      },
      busy: function (newBusy) {
        if (!this.dataTable) {
          return;
        }
        this.dataTable.processing(newBusy);
      },
    },

    mounted() {
      this.dataTable = $(this.$refs.dataTable).DataTable({
        data: this.transactions,
        processing: true,
        columns: [
          dataTableHelpers.transactionColumnDefinition.dateFromCustomField(
            'date',
            __('Date'),
            window.YAFFA.locale,
          ),
          dataTableHelpers.transactionColumnDefinition.type,
          {
            title: __('From'),
            defaultContent: '',
            data: 'config.account_from.name',
          },
          {
            title: __('To'),
            defaultContent: '',
            data: 'config.account_to.name',
          },
          dataTableHelpers.transactionColumnDefinition.category,
          dataTableHelpers.transactionColumnDefinition.amount,
          dataTableHelpers.transactionColumnDefinition.extra,
          {
            data: 'id',
            defaultContent: '',
            title: __('Actions'),
            render: function (data, _type) {
              return (
                dataTableHelpers.dataTablesActionButton(data, 'quickView') +
                dataTableHelpers.dataTablesActionButton(data, 'show') +
                dataTableHelpers.dataTablesActionButton(data, 'edit') +
                dataTableHelpers.dataTablesActionButton(data, 'clone') +
                dataTableHelpers.dataTablesActionButton(data, 'delete')
              );
            },
            className: 'dt-nowrap',
            orderable: false,
            searchable: false,
          },
        ],
        order: [[0, 'asc']],
      });

      // Delete transaction icon functionality
      dataTableHelpers.initializeAjaxDeleteButton(this.$refs.dataTable);
      dataTableHelpers.initializeQuickViewButton(this.$refs.dataTable);

      // Handle tab switching for lazy loading data
      this._allTabs = Array.from(this.$el.querySelectorAll('[data-coreui-toggle="tab"]'));
      this._onTabShown = (event) => {
        const targetId = event.target.getAttribute('data-coreui-target');

        // Lazily populate DataTable when transaction list tab is shown
        if (targetId === '#tab-transaction-list') {
          this.populateDataTable(true);
        }

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
      this._allTabs.forEach((tab) => tab.addEventListener('shown.coreui.tab', this._onTabShown));

      // Auto-switch to requested tab (e.g. from monthly breakdown drill-down)
      if (this.initialTab) {
        this.$nextTick(() => {
          const tabButton = this.$el.querySelector('#nav-' + this.initialTab);
          if (tabButton) {
            tabButton.click();
          }
        });
      }

      this.ready = true;
    },

    beforeUnmount() {
      if (this._allTabs) {
        this._allTabs.forEach((tab) => tab.removeEventListener('shown.coreui.tab', this._onTabShown));
      }
    },
  };
</script>

<style scoped>
.transactions-sidebar-toggle {
  position: absolute;
  left: -16px;
  bottom: 16px;
  z-index: 5;
  width: 42px;
  height: 42px;
  border-radius: 999px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border: 1px solid var(--cui-border-color, #d8dbe0);
  background: var(--cui-card-cap-bg, #f8f9fa);
  color: var(--cui-secondary-color, #6c757d);
  box-shadow: 0 1px 2px rgba(15, 23, 42, 0.08);
}

.transactions-sidebar-toggle:hover {
  background: #fff;
  color: var(--cui-body-color, #212529);
}

@media (max-width: 576px) {
  .transactions-sidebar-toggle {
    left: 8px;
    bottom: 8px;
  }
}
</style>
