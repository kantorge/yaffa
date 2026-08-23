<template>
  <editable-dated-value-table
    ref="table"
    :title="__('Currency rate values')"
    table-id="ratesTable"
    action-prefix="rate"
    :items="currencyRates"
    :filtered-items="filteredRates"
    value-field="rate"
    :value-label="__('Rate')"
    :currency="toCurrency"
    delete-route="api.v1.currency-rates.destroy"
    delete-route-param="currencyRate"
    :deleting-message="__('Deleting rate...')"
    :deleted-message="__('Currency rate deleted')"
    :delete-failed-message="__('Failed to delete rate')"
    @edit-item="$emit('edit-rate', $event)"
    @delete-item="$emit('delete-rate', $event)"
  />
</template>

<script>
  import EditableDatedValueTable from '@/shared/ui/datatable/EditableDatedValueTable.vue';
  import { __ } from '@/shared/lib/i18n';

  export default {
    name: 'CurrencyRateTable',
    components: {
      EditableDatedValueTable,
    },
    props: {
      currencyRates: {
        type: Array,
        required: true,
      },
      fromCurrency: {
        type: Object,
        required: true,
      },
      toCurrency: {
        type: Object,
        required: true,
      },
      filteredRates: {
        type: Array,
        default: null,
      },
    },
    emits: ['edit-rate', 'delete-rate', 'data-updated'],
    methods: {
      // Delegated to by CurrencyRateManager.vue's $refs.rateTable.updateTableData(...) calls.
      updateTableData(rates) {
        this.$refs.table.updateTableData(rates);
      },
      __,
    },
  };
</script>
