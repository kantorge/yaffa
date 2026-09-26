<template>
  <editable-dated-value-table
    ref="table"
    :title="__('Investment price values')"
    table-id="table-investment-prices"
    action-prefix="price"
    :items="investmentPrices"
    :filtered-items="filteredPrices"
    value-field="price"
    :value-label="__('Price')"
    :currency="investment.currency"
    delete-route="api.v1.investment-prices.destroy"
    delete-route-param="investmentPrice"
    :deleting-message="__('Deleting price...')"
    :deleted-message="__('Investment price deleted')"
    :delete-failed-message="__('Failed to delete price')"
    @edit-item="$emit('edit-price', $event)"
    @delete-item="$emit('delete-price', $event)"
  />
</template>

<script>
  import EditableDatedValueTable from '@/shared/ui/datatable/EditableDatedValueTable.vue';
  import { __ } from '@/shared/lib/i18n';

  export default {
    name: 'InvestmentPriceTable',
    components: {
      EditableDatedValueTable,
    },
    props: {
      investmentPrices: {
        type: Array,
        required: true,
      },
      investment: {
        type: Object,
        required: true,
      },
      filteredPrices: {
        type: Array,
        default: null,
      },
    },
    emits: ['edit-price', 'delete-price', 'data-updated'],
    methods: {
      // Delegated to by InvestmentPriceManager.vue's $refs.priceTable.updateTableData(...) calls.
      updateTableData(prices) {
        this.$refs.table.updateTableData(prices);
      },
      __,
    },
  };
</script>
