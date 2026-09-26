<template>
  <div class="card mb-4" v-if="available">
    <div class="card-header d-flex justify-content-between">
      <div class="card-title">
        {{ __('widget.categoryWaterfall.cardTitle') }}
        <i
          v-if="missingRateCurrencies.length > 0"
          class="fa fa-triangle-exclamation text-warning ms-2"
          :title="missingRatesTooltip"
          data-bs-toggle="tooltip"
          role="img"
          :aria-label="missingRatesTooltip"
        ></i>
      </div>
      <div v-show="ready">
        <button
          class="btn btn-sm btn-outline-info me-2"
          type="button"
          @click="previousMonth"
          :title="__('widget.categoryWaterfall.previousMonthTitle')"
        >
          <span class="fa fa-fw fa-caret-left"></span>
        </button>
        {{ dateLabel }}
        <button
          class="btn btn-sm btn-outline-info ms-2"
          type="button"
          @click="nextMonth"
          :title="__('widget.categoryWaterfall.nextMonthTitle')"
        >
          <span class="fa fa-fw fa-caret-right"></span>
        </button>
      </div>
    </div>
    <div class="card-body">
      <p aria-hidden="true" v-if="!ready" class="placeholder-glow">
        <span class="placeholder col-12"></span>
      </p>
      <waterfall-chart
        v-show="ready"
        :raw-data="rawData"
        :result-label="__('widget.categoryWaterfall.result')"
        :base-currency="baseCurrency"
        :locale="locale"
        :language="language"
        :no-data-message="__('widget.categoryWaterfall.noData')"
        clickable
        @column-click="handleColumnClick"
      ></waterfall-chart>
    </div>
    <div class="card-footer text-end">
      <div
        class="btn-group"
        role="group"
        aria-label="Transaction type selector for category waterfall chart"
      >
        <input
          type="radio"
          class="btn-check"
          name="waterfallTransactionCategory"
          id="waterfallTransactionCategory_All"
          value="all"
          autocomplete="off"
          v-model="transactionTypeData"
          @change="refreshData"
          :disabled="busy"
        />
        <label
          class="btn btn-sm btn-outline-primary"
          for="waterfallTransactionCategory_All"
        >
          {{ __('widget.categoryWaterfall.allTransactions') }}</label
        >

        <input
          type="radio"
          class="btn-check"
          name="waterfallTransactionCategory"
          id="waterfallTransactionCategory_Standard"
          value="standard"
          autocomplete="off"
          v-model="transactionTypeData"
          @change="refreshData"
          :disabled="busy"
        />
        <label
          class="btn btn-sm btn-outline-primary"
          for="waterfallTransactionCategory_Standard"
        >
          {{ __('widget.categoryWaterfall.onlyStandard') }}
        </label>

        <input
          type="radio"
          class="btn-check"
          name="waterfallTransactionCategory"
          id="waterfallTransactionCategory_Investment"
          value="investment"
          autocomplete="off"
          v-model="transactionTypeData"
          @change="refreshData"
          :disabled="busy"
        />
        <label
          class="btn btn-sm btn-outline-primary"
          for="waterfallTransactionCategory_Investment"
        >
          {{ __('widget.categoryWaterfall.onlyInvestment') }}
        </label>
      </div>
    </div>
  </div>
</template>

<script>
  import { __, toFormattedDate } from '@/shared/lib/i18n';
  import { initializeBootstrapTooltips } from '@/shared/lib/helpers';
  import * as toastHelpers from '@/shared/lib/toast';
  import WaterfallChart from '@/shared/ui/charts/WaterfallChart.vue';

  function formatDate(date) {
    const y = date.getFullYear();
    const m = String(date.getMonth() + 1).padStart(2, '0');
    const d = String(date.getDate()).padStart(2, '0');
    return `${y}-${m}-${d}`;
  }

  export default {
    components: {
      'waterfall-chart': WaterfallChart,
    },
    props: {
      categoryAxisVisible: {
        type: Boolean,
        default: false,
      },
      transactionType: {
        type: String,
        default: 'all',
      },
    },
    data() {
      return {
        available: false,
        busy: false,
        baseCurrency: window.YAFFA.userSettings.baseCurrency,
        locale: window.YAFFA.userSettings.locale,
        language: window.YAFFA.userSettings.language,
        rawData: [],
        missingRateCurrencies: [],
        year: new Date().getFullYear(),
        month: new Date().getMonth() + 1,
        transactionTypeData: this.transactionType,
        ready: false,
      };
    },
    created() {
      // Verify if base currency is set. Without this, the widget cannot be displayed.
      if (!this.baseCurrency) {
        return;
      }

      this.available = true;
      this.refreshData();
    },
    methods: {
      previousMonth: function () {
        this.month--;
        if (this.month < 1) {
          this.year--;
          this.month = 12;
        }

        this.refreshData();
      },

      nextMonth: function () {
        this.month++;
        if (this.month > 12) {
          this.year++;
          this.month = 1;
        }

        this.refreshData();
      },

      refreshData() {
        if (this.busy) {
          return;
        }

        this.busy = true;
        this.ready = false;

        let url =
          '/api/v1/reports/waterfall/' +
          this.transactionTypeData +
          '/result/' +
          this.year +
          '/' +
          this.month;
        let options = {
          method: 'GET',
          headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json;charset=UTF-8',
          },
        };

        fetch(url, options)
          .then((response) => response.json())
          .then((data) => {
            this.rawData = data.chartData;
            this.missingRateCurrencies =
              data.warnings?.currenciesWithoutRates || [];

            this.ready = true;
          })
          .finally(() => (this.busy = false))
          .catch((error) => {
            toastHelpers.showErrorToast(error.message);
          });
      },

      /**
       * Open the find transactions report, filtered to the month currently shown by
       * the chart, the transaction types that contributed to the clicked bucket, and
       * (for standard categories) the underlying top-level category. The find
       * transactions report already expands a top-level category into itself plus its
       * children (same as the budget chart and scheduled items filters), which matches
       * how this bucket was aggregated in the first place.
       */
      handleColumnClick(bucket) {
        const query = {
          date_from: formatDate(new Date(this.year, this.month - 1, 1)),
          date_to: formatDate(new Date(this.year, this.month, 0)),
          types: bucket.transaction_types || [],
        };

        if (bucket.category_id) {
          query.categories = [bucket.category_id];
        }

        window.location.href = this.route('reports.transactions', query);
      },

      __,
    },
    computed: {
      missingRatesTooltip() {
        const currencyList = this.missingRateCurrencies
          .map((currency) => `${currency.name} (${currency.iso_code})`)
          .join(', ');

        return (
          __('widget.categoryWaterfall.missingRatesTooltipPrefix') +
          currencyList +
          '. ' +
          __('widget.categoryWaterfall.missingRatesTooltipSuffix')
        );
      },

      dateLabel() {
        const date = new Date(this.year, this.month - 1, 1);
        return toFormattedDate(date, window.YAFFA.userSettings.locale, '', false, {
          year: 'numeric',
          month: 'long',
        });
      },
    },
    updated() {
      initializeBootstrapTooltips(this.$el);
    },
  };
</script>
