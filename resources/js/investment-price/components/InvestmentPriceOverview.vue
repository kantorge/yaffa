<template>
  <record-overview-card
    :header-rows="headerRows"
    :records="investmentPrices"
    :last-value-label="__('Last known price')"
  >
    <template #last-value="{ record }">
      {{
        toFormattedCurrency(record.price, locale, investment.currency, 'detailed')
      }}
    </template>
  </record-overview-card>
</template>

<script>
  import RecordOverviewCard from '@/shared/ui/RecordOverviewCard.vue';
  import { __, toFormattedCurrency } from '@/shared/lib/i18n';

  export default {
    name: 'InvestmentPriceOverview',
    components: {
      RecordOverviewCard,
    },
    props: {
      investment: {
        type: Object,
        required: true,
      },
      // Note, that we assume that investmentPrices are sorted by date ascending
      investmentPrices: {
        type: Array,
        required: true,
      },
    },
    data() {
      return {
        locale: window.YAFFA.userSettings.locale,
      };
    },
    computed: {
      headerRows() {
        return [{ label: this.__('Investment'), value: this.investment.name }];
      },
    },
    methods: {
      toFormattedCurrency,
      __,
    },
  };
</script>
