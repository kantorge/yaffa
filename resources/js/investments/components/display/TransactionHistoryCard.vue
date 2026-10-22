<template>
  <div class="card mb-3">
    <div class="card-header d-flex justify-content-between">
      <div class="card-title">
        {{ __('Transaction history') }}
      </div>
      <div>
        <a
          :href="newTransactionUrl"
          class="btn btn-success btn-sm"
          :title="__('New investment transaction')"
        >
          <i class="fa fa-plus"></i>
        </a>
      </div>
    </div>
    <div class="card-body">
      <transaction-table
        :transactions="transactions"
        :busy="false"
        :is-active="true"
        :columns="tableColumns"
        :actions="rowActions"
        :action-params="rowActionParams"
        @transaction-deleted="$emit('delete-transaction', $event)"
        @transaction-skipped="$emit('delete-transaction', $event)"
        @set-date-range="$emit('set-date-range', $event)"
      />
    </div>
  </div>
</template>

<script>
  import 'datatables.net-bs5/css/dataTables.bootstrap5.min.css';

  import * as dataTableHelpers from '@/shared/lib/datatable';
  import { __, toFormattedNumber } from '@/shared/lib/i18n';
  import TransactionTable from '@/shared/ui/datatable/TransactionTable.vue';

  export default {
    name: 'TransactionHistoryCard',
    components: {
      TransactionTable,
    },
    props: {
      transactions: { type: Array, required: true },
      investment: { type: Object, required: true },
      locale: {
        type: String,
        default: () =>
          window.YAFFA ? window.YAFFA.userSettings.locale : navigator.language,
      },
    },
    emits: ['set-date-range', 'delete-transaction'],
    computed: {
      newTransactionUrl() {
        if (!this.route) {
          return '#';
        }
        return this.route('transaction.create', {
          type: 'investment',
          callback: 'back',
        });
      },
      tableColumns() {
        const vm = this;
        return [
          dataTableHelpers.transactionColumnDefinition.dateFromCustomField(
            'date',
            __('Date'),
            this.locale,
          ),
          dataTableHelpers.transactionColumnDefinition.type(false),
          {
            data: 'config.quantity',
            title: __('Quantity'),
            render: function (data) {
              return data !== null && data !== ''
                ? toFormattedNumber(data, vm.locale)
                : '';
            },
          },
          {
            data: 'config.price',
            title: __('Price'),
            render: function (data, type) {
              return dataTableHelpers.toFormattedCurrency(
                type,
                data,
                vm.locale,
                vm.investment.currency,
                'detailed',
              );
            },
          },
          {
            data: 'config.dividend',
            title: __('Dividend'),
            render: function (data, type) {
              return dataTableHelpers.toFormattedCurrency(
                type,
                data,
                vm.locale,
                vm.investment.currency,
              );
            },
          },
          {
            data: 'config.commission',
            title: __('Commission'),
            render: function (data, type) {
              return dataTableHelpers.toFormattedCurrency(
                type,
                data,
                vm.locale,
                vm.investment.currency,
              );
            },
          },
          {
            data: 'config.tax',
            title: __('Tax'),
            render: function (data, type) {
              return dataTableHelpers.toFormattedCurrency(
                type,
                data,
                vm.locale,
                vm.investment.currency,
              );
            },
          },
          {
            data: 'cashflow_value',
            title: __('Cash flow value'),
            render: function (data, type) {
              return isNaN(data)
                ? 0
                : dataTableHelpers.toFormattedCurrency(
                    type,
                    data,
                    vm.locale,
                    vm.investment.currency,
                  );
            },
          },
        ];
      },
    },
    methods: {
      rowActions(row) {
        if (!row.schedule) {
          return ['setDateFrom', 'setDateTo', 'edit', 'clone', 'delete'];
        }

        return row.schedule_first_instance
          ? ['setDateFrom', 'setDateTo', 'edit', 'replace', 'enter', 'skip']
          : ['setDateFrom', 'setDateTo'];
      },
      rowActionParams(row) {
        return row.schedule ? { callback: 'back' } : {};
      },
    },
  };
</script>
