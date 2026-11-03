<template>
  <div>
    <h2>{{ __('Category waterfall') }}</h2>

    <ul v-if="busy" class="list-group list-group-flush">
      <li
        v-for="i in 5"
        :key="i"
        aria-hidden="true"
        class="list-group-item placeholder-glow"
      >
        <span class="placeholder col-12"></span>
      </li>
    </ul>

    <div v-else-if="transactions.length === 0" class="text-muted">
      {{ __('No transactions to display') }}
    </div>

    <template v-else>
      <waterfall-chart
        :raw-data="rawData"
        :result-label="__('widget.categoryWaterfall.result')"
        :base-currency="baseCurrency"
        :locale="locale"
        :language="language"
        :no-data-message="__('widget.categoryWaterfall.noData')"
        category-axis-visible
      ></waterfall-chart>

      <div class="text-end mt-2">
        <waterfall-group-toggles v-model="include"></waterfall-group-toggles>
      </div>
    </template>
  </div>
</template>

<script>
  import { __ } from '@/shared/lib/i18n';
  import WaterfallChart from '@/shared/ui/charts/WaterfallChart.vue';
  import WaterfallGroupToggles from '@/shared/ui/charts/WaterfallGroupToggles.vue';
  import { aggregateTransactionsForWaterfall } from '@/shared/lib/reports';

  export default {
    name: 'TransactionWaterfall',
    components: {
      'waterfall-chart': WaterfallChart,
      'waterfall-group-toggles': WaterfallGroupToggles,
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
        include: ['standard', 'income', 'trades'],
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
          include: this.include,
        });
      },
    },
    methods: {
      __,
    },
  };
</script>
