<template>
  <div>
    <h2>{{ __('Category waterfall') }}</h2>

    <ul class="list-group list-group-flush" v-if="busy">
      <li
        aria-hidden="true"
        class="list-group-item placeholder-glow"
        v-for="i in 5"
        :key="i"
      >
        <span class="placeholder col-12"></span>
      </li>
    </ul>

    <div v-else-if="transactions.length === 0" class="text-muted">
      {{ __('No transactions to display') }}
    </div>

    <waterfall-chart
      v-else
      :raw-data="rawData"
      :result-label="__('widget.categoryWaterfall.result')"
      :base-currency="baseCurrency"
      :locale="locale"
      :language="language"
      :no-data-message="__('widget.categoryWaterfall.noData')"
      category-axis-visible
    ></waterfall-chart>
  </div>
</template>

<script>
  import { __ } from '@/shared/lib/i18n';
  import WaterfallChart from '@/shared/ui/charts/WaterfallChart.vue';
  import { aggregateTransactionsForWaterfall } from '../find-transactions/helpers';

  export default {
    name: 'ReportingCanvasFindTransactionsWaterfall',
    components: {
      'waterfall-chart': WaterfallChart,
    },
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
      matchingItemsOnly: {
        type: Boolean,
        default: false,
      },
      categoryIds: {
        type: Array,
        default: () => [],
      },
      tagIds: {
        type: Array,
        default: () => [],
      },
    },
    data() {
      return {
        baseCurrency: window.YAFFA.userSettings.baseCurrency,
        locale: window.YAFFA.userSettings.locale,
        language: window.YAFFA.userSettings.language,
      };
    },
    computed: {
      // Grouped by top-level category (and investment income/payment), summed across
      // the whole currently filtered transaction set — mirrors the dashboard's
      // per-month category waterfall widget, but over the report's own date range.
      rawData() {
        return aggregateTransactionsForWaterfall(this.transactions, __, {
          matchingItemsOnly: this.matchingItemsOnly,
          categoryIds: this.categoryIds,
          tagIds: this.tagIds,
        });
      },
    },
    methods: {
      __,
    },
  };
</script>
