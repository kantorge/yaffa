<template>
  <record-overview-card
    :header-rows="headerRows"
    :records="currencyRates"
    :last-value-label="__('Last known rate')"
  >
    <template #last-value="{ record }">
      {{ toFormattedCurrency(1, locale, from, 'detailed') }}
      =
      {{ toFormattedCurrency(record.rate, locale, to, 'detailed') }}
    </template>
  </record-overview-card>
</template>

<script>
  import RecordOverviewCard from '@/shared/ui/RecordOverviewCard.vue';
  import { __, toFormattedCurrency } from '@/shared/lib/i18n';

  export default {
    name: 'CurrencyRateOverview',
    components: {
      RecordOverviewCard,
    },
    props: {
      from: {
        type: Object,
        required: true,
      },
      to: {
        type: Object,
        required: true,
      },
      currencyRates: {
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
        return [
          { label: this.__('From'), value: this.from.name },
          { label: this.__('To'), value: this.to.name },
        ];
      },
    },
    methods: {
      toFormattedCurrency,
      __,
    },
  };
</script>
