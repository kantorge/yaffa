<template>
  <div>
    <div
      v-if="filterConflictWarning"
      class="alert alert-warning d-flex align-items-center"
      role="alert"
    >
      <i class="fa fa-triangle-exclamation me-2"></i>
      <span>{{ filterConflictWarning }}</span>
    </div>

    <div class="row">
    <div :class="leftControlPanelCollapsed ? 'd-none' : 'col-sm-3'">
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
          <li
            class="list-group-item d-flex justify-content-between align-items-center"
            v-if="selectedCategories.length > 0 || selectedTags.length > 0"
          >
            <span>
              {{ __('Only count matching items in breakdowns') }}
              <i
                class="fa fa-info-circle text-info ms-1"
                data-bs-toggle="tooltip"
                :title="
                  __(
                    'When on, the monthly breakdown, category waterfall, and category charts only count the transaction items that match the active category/tag filters, instead of every item of a matching transaction.',
                  )
                "
              ></i>
            </span>
            <div class="form-check form-switch mb-0">
              <input
                class="form-check-input"
                type="checkbox"
                role="switch"
                id="matchingItemsOnlySwitch"
                v-model="matchingItemsOnly"
              />
            </div>
          </li>
        </ul>
      </div>

      <date-range-filter-card
        :initial-date-from="dateFrom"
        :initial-date-to="dateTo"
        :initial-preset="selectedPreset"
        @update="onUpdateDateRange"
      ></date-range-filter-card>

      <transaction-type-filter-card
        :preset-type-values="selectedTypes"
        @update="onUpdateType($event)"
        @preset-ready="setReadyFlag($event)"
      ></transaction-type-filter-card>

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

      <find-transaction-select-card
        search-api-path="/api/v1/investments"
        property="investment"
        title="Investment"
        placeholder="Select investment"
        details-api-path="/api/v1/investments/#id#"
        :preset-item-ids="selectedInvestments"
        @update="onUpdateInvestment($event)"
        @preset-ready="setReadyFlag($event)"
      ></find-transaction-select-card>
    </div>
    <div :class="leftControlPanelCollapsed ? 'col-sm-12' : 'col-sm-9'">
      <div class="left-control-panel-toggle-shell">
        <div class="card left-control-panel-toggle-card">
          <div
            class="card-header d-flex align-items-center gap-2 left-control-panel-toggle-header"
          >
            <button
              type="button"
              class="btn btn-sm btn-outline-secondary me-2"
              @click="toggleLeftControlPanel"
              :title="leftControlPanelToggleState.title"
              :aria-label="leftControlPanelToggleState.title"
              :aria-expanded="leftControlPanelToggleState.ariaExpanded"
            >
              <i
                :class="`fas ${leftControlPanelToggleState.iconClass}`"
                data-left-control-panel-toggle-icon
              ></i>
            </button>
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
              <li class="nav-item">
                <button
                  class="nav-link"
                  id="nav-waterfall"
                  data-coreui-toggle="tab"
                  data-coreui-target="#tab-waterfall"
                  type="button"
                  role="tab"
                  aria-controls="tab-waterfall"
                  aria-selected="false"
                >
                  {{ __('Category waterfall') }}
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
                  :matching-items-only="matchingItemsOnly"
                  :category-ids="selectedCategories"
                  :tag-ids="selectedTags"
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
                  :matching-items-only="matchingItemsOnly"
                  :category-ids="selectedCategories"
                  :tag-ids="selectedTags"
                  @drill-down="onMonthlyBreakdownDrillDown"
                ></reporting-canvas-monthly-breakdown>
              </div>
              <div
                class="tab-pane fade"
                id="tab-waterfall"
                role="tabpanel"
                aria-labelledby="nav-waterfall"
                tabindex="6"
              >
                <reporting-canvas-waterfall
                  :transactions="transactions"
                  :busy="busy"
                  :matching-items-only="matchingItemsOnly"
                  :category-ids="selectedCategories"
                  :tag-ids="selectedTags"
                ></reporting-canvas-waterfall>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <transaction-show-modal></transaction-show-modal>
  </div>
  </div>
</template>

<script>
  import { __ } from '@/shared/lib/i18n';
  import {
    processTransaction,
    initializeBootstrapTooltips,
    getArrayParamFromUrl,
  } from '@/shared/lib/helpers';
  import { buildFilterCacheKey, buildBreakdownCacheKey } from './helpers';
  import * as toastHelpers from '@/shared/lib/toast';
  import FindTransactionSelectCard from './FindTransactionSelectCard.vue';
  import TransactionTypeFilterCard from './TransactionTypeFilterCard.vue';
  import DateRangeFilterCard from '@/shared/ui/date/DateRangeFilterCard.vue';
  import ReportingCanvasFindTransactionsCategoryDetails from '../widgets/ReportingCanvas-FindTransactions-CategoryDetails.vue';
  import ReportingCanvasFindTransactionsSummary from '../widgets/ReportingCanvas-FindTransactions-Summary.vue';
  import ReportingCanvasFindTransactionsTimeline from '../widgets/ReportingCanvas-FindTransactions-Timeline.vue';
  import ReportingCanvasFindTransactionsMonthlyBreakdown from '../widgets/ReportingCanvas-FindTransactions-MonthlyBreakdown.vue';
  import ReportingCanvasFindTransactionsTransactionList from '../widgets/ReportingCanvas-FindTransactions-TransactionList.vue';
  import ReportingCanvasFindTransactionsWaterfall from '../widgets/ReportingCanvas-FindTransactions-Waterfall.vue';
  import TransactionShowModal from '@/transactions/components/display/Modal.vue';
  import { getLeftControlPanelToggleState } from '@/shared/lib/ui/leftControlPanelToggle';
  import presetCalculators from '@/shared/lib/date/presetDates';

  function formatDate(date) {
    if (!date) return null;
    const d = date instanceof Date ? date : new Date(date);
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${y}-${m}-${day}`;
  }

  const TRANSACTIONS_CACHE_KEY = 'yaffa_transactions_cache';
  const BREAKDOWN_CACHE_KEY = 'yaffa_breakdown_cache';

  export default {
    name: 'FindTransactions',
    components: {
      'find-transaction-select-card': FindTransactionSelectCard,
      'transaction-type-filter-card': TransactionTypeFilterCard,
      'transaction-show-modal': TransactionShowModal,
      'date-range-filter-card': DateRangeFilterCard,
      'reporting-canvas-categories':
        ReportingCanvasFindTransactionsCategoryDetails,
      'reporting-canvas-summary': ReportingCanvasFindTransactionsSummary,
      'reporting-canvas-timeline': ReportingCanvasFindTransactionsTimeline,
      'reporting-canvas-monthly-breakdown':
        ReportingCanvasFindTransactionsMonthlyBreakdown,
      'reporting-canvas-transaction-list':
        ReportingCanvasFindTransactionsTransactionList,
      'reporting-canvas-waterfall': ReportingCanvasFindTransactionsWaterfall,
    },
    data() {
      const urlParams = new URLSearchParams(window.location.search);
      const datePreset = urlParams.get('date_preset') || null;
      let dateFrom = urlParams.get('date_from') || null;
      let dateTo = urlParams.get('date_to') || null;

      if (datePreset && !dateFrom && !dateTo) {
        const calculator = presetCalculators[datePreset];
        if (calculator) {
          const dates = calculator(new Date());
          dateFrom = formatDate(dates.start);
          dateTo = formatDate(dates.end);
        }
      }

      return {
        busy: false,
        ready: false,
        leftControlPanelCollapsed: false,
        activeTab: 'summary',
        dateFrom,
        dateTo,
        selectedPreset:
          urlParams.get('date_from') || urlParams.get('date_to')
            ? null
            : datePreset,
        allTypeValues: Object.keys(window.YAFFA.config.transactionTypes || {}),
        standardTypeValues: Object.values(
          window.YAFFA.config.transactionTypes || {},
        )
          .filter((type) => type.category === 'standard')
          .map((type) => type.value),
        investmentTypeValues: Object.values(
          window.YAFFA.config.transactionTypes || {},
        )
          .filter((type) => type.category === 'investment')
          .map((type) => type.value),
        selectedTypes: this.getUrlParams('types'),
        selectedAccounts: this.getUrlParams('accounts'),
        selectedCategories: this.getUrlParams('categories'),
        selectedPayees: this.getUrlParams('payees'),
        selectedTags: this.getUrlParams('tags'),
        selectedInvestments: this.getUrlParams('investments'),
        // Only relevant while a category/tag filter narrows the result set; scopes the
        // category-based aggregate views (breakdown, waterfall, category charts) to the
        // items that actually matched, instead of every item of a matching transaction.
        matchingItemsOnly: false,
        returnTo: this.sanitizeReturnTo(urlParams.get('return_to')),
        initialTab: urlParams.get('tab') || null,
        skippedTransactionLoad: false,
        drillDownFilter: null,
        presetsReady: {
          category: false,
          payee: false,
          account: false,
          tag: false,
          investment: false,
          types: false,
        },
        transactions: [],
      };
    },
    computed: {
      leftControlPanelToggleState() {
        return getLeftControlPanelToggleState(this.leftControlPanelCollapsed);
      },
      // Only treated as an active filter when it's a real subset; the full set is
      // equivalent to "no restriction" and is therefore omitted from requests/URLs.
      typesFilterParam() {
        return this.selectedTypes.length !== this.allTypeValues.length
          ? this.selectedTypes
          : [];
      },
      // Warns about type/other-filter combinations that are guaranteed to return
      // nothing: category/payee/tag only ever apply to standard transactions, and the
      // investment filter only ever applies to investment transactions.
      filterConflictWarning() {
        const hasStandardOnlyFilter =
          this.selectedCategories.length > 0 ||
          this.selectedPayees.length > 0 ||
          this.selectedTags.length > 0;
        const hasInvestmentFilter = this.selectedInvestments.length > 0;

        // An empty selection means "all types" (see TransactionTypeFilterCard)
        const effectiveTypes =
          this.selectedTypes.length === 0
            ? this.allTypeValues
            : this.selectedTypes;
        const hasStandardTypeSelected = effectiveTypes.some((type) =>
          this.standardTypeValues.includes(type),
        );
        const hasInvestmentTypeSelected = effectiveTypes.some((type) =>
          this.investmentTypeValues.includes(type),
        );

        if (hasStandardOnlyFilter && !hasStandardTypeSelected) {
          return __(
            'Category, payee, and tag filters only apply to standard transactions, but no standard transaction type is selected. This combination will not return any results.',
          );
        }

        if (hasInvestmentFilter && !hasInvestmentTypeSelected) {
          return __(
            'The investment filter only applies to investment transactions, but no investment transaction type is selected. This combination will not return any results.',
          );
        }

        return null;
      },
    },
    methods: {
      toggleLeftControlPanel() {
        this.leftControlPanelCollapsed = !this.leftControlPanelCollapsed;
      },
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
        this.selectedPreset = event.preset || null;
        this.rebuildUrl();
      },
      onUpdateType(event) {
        this.drillDownFilter = null;
        this.selectedTypes = event;
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
      onUpdateInvestment(event) {
        this.drillDownFilter = null;
        this.selectedInvestments = event;
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

        // Date filter: use preset key when active, explicit dates otherwise
        if (this.selectedPreset) {
          params.push('date_preset=' + encodeURIComponent(this.selectedPreset));
        } else {
          if (this.dateFrom) {
            params.push('date_from=' + this.dateFrom);
          }
          if (this.dateTo) {
            params.push('date_to=' + this.dateTo);
          }
        }

        // Transaction types: only encode when a real subset is selected, since the
        // default (no URL parameter) already means "search all types"
        const types = this.typesFilterParam.map(
          (item) => 'types[]=' + encodeURIComponent(item),
        );
        params.push(...types);

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

        // Investments
        const investments = this.selectedInvestments.map(
          (item) => 'investments[]=' + item,
        );
        params.push(...investments);

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
          types: this.typesFilterParam,
          investments: this.selectedInvestments,
        });
      },

      loadFromCache() {
        try {
          const cached = sessionStorage.getItem(TRANSACTIONS_CACHE_KEY);
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
            TRANSACTIONS_CACHE_KEY,
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
          const cached = sessionStorage.getItem(BREAKDOWN_CACHE_KEY);
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
          const cached = sessionStorage.getItem(TRANSACTIONS_CACHE_KEY);
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
            TRANSACTIONS_CACHE_KEY,
            JSON.stringify(payload),
          );
        } catch (e) {
          console.warn('Failed to update transactions cache:', e);
        }
      },

      invalidateBreakdownCache() {
        try {
          sessionStorage.removeItem(BREAKDOWN_CACHE_KEY);
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
              types: this.typesFilterParam,
              investments: this.selectedInvestments,
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
        return getArrayParamFromUrl(new URLSearchParams(window.location.search), paramName);
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

      initializeBootstrapTooltips(this.$el);
    },

    updated() {
      initializeBootstrapTooltips(this.$el);
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
