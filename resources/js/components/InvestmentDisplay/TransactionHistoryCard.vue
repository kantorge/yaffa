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
      <DataTable
        :data="transactions"
        :columns="tableColumns"
        :options="tableOptions"
        class="table table-bordered table-hover"
        width="100%"
      />
    </div>
  </div>
</template>

<script>
  import DataTable from 'datatables.net-vue3';
  import DataTablesCore from 'datatables.net';
  import DataTablesBootstrap5 from 'datatables.net-bs5';

  DataTable.use(DataTablesCore);
  DataTable.use(DataTablesBootstrap5);
  import 'datatables.net-bs5/css/dataTables.bootstrap5.min.css';

  import * as dataTableHelpers from '../../components/dataTableHelper';
  import { toIsoDateString } from '../../helpers';

  export default {
    name: 'TransactionHistoryCard',
    components: {
      DataTable,
    },
    props: {
      transactions: { type: Array, required: true },
      investment: { type: Object, required: true },
      locale: {
        type: String,
        default: () =>
          window.YAFFA ? window.YAFFA.locale : navigator.language,
      },
    },
    emits: ['set-date-range', 'delete-transaction'],
    computed: {
      newTransactionUrl() {
        if (!window.route) {
          return '#';
        }
        return window.route('transaction.create', {
          type: 'investment',
          callback: 'back',
        });
      },
      tableOptions() {
        return {
          createdRow: (row, data) => {
            // Set date range buttons
            row.querySelectorAll('.set-date').forEach((btn) => {
              btn.onclick = (event) => {
                const type = btn.getAttribute('data-type');
                const date = btn.getAttribute('data-date');
                if (type && date) {
                  this.$emit('set-date-range', { type, date });
                }
                event.stopPropagation();
              };
            });
            // Delete button
            row.querySelectorAll('.data-delete').forEach((btn) => {
              btn.onclick = (event) => {
                const id = btn.getAttribute('data-id');
                if (
                  id &&
                  confirm(this.__('Are you sure to want to delete this item?'))
                ) {
                  this.$emit('delete-transaction', id);
                }
                event.stopPropagation();
              };
            });
          },
        };
      },
      tableColumns() {
        const vm = this;
        return [
          dataTableHelpers.transactionColumnDefinition.dateFromCustomField(
            'date',
            __('Date'),
            this.locale
          ),
          { data: 'transaction_type.name', title: __('Transaction') },
          {
            data: 'config.quantity',
            title: __('Quantity'),
            render: function (data) {
              return data !== null && data !== ''
                ? Number(data).toLocaleString(vm.locale)
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
                vm.investment.currency
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
                vm.investment.currency
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
                vm.investment.currency
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
                vm.investment.currency
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
                    vm.investment.currency
                  );
            },
          },
          {
            data: 'id',
            defaultContent: '',
            title: __('Actions'),
            render: function (_data, _type, row) {
              let actions =
                `<button class="btn btn-xs btn-outline-dark set-date" data-type="from" data-date="${toIsoDateString(
                  row.date
                )}" title="${vm.__(
                  'Make this the start date'
                )}"><i class="fa fa-fw fa-caret-left"></i></button> ` +
                `<button class="btn btn-xs btn-outline-dark set-date" data-type="to" data-date="${toIsoDateString(
                  row.date
                )}" title="${vm.__(
                  'Make this the end date'
                )}"><i class="fa fa-fw fa-caret-right"></i></button> `;
              if (!row.schedule) {
                const id = row.id;
                actions +=
                  `<a href="${window.route('transaction.open', {
                    transaction: id,
                    action: 'edit',
                  })}" class="btn btn-xs btn-primary" title="${vm.__(
                    'Edit'
                  )}"><i class="fa fa-fw fa-edit"></i></a> ` +
                  `<a href="${window.route('transaction.open', {
                    transaction: id,
                    action: 'clone',
                  })}" class="btn btn-xs btn-primary" title="${vm.__(
                    'Clone'
                  )}"><i class="fa fa-fw fa-clone"></i></a> ` +
                  `<button class="btn btn-xs btn-danger data-delete" data-id="${id}" type="button" title="${vm.__(
                    'Delete'
                  )}"><i class="fa fa-fw fa-trash"></i></button> `;
              }
              return actions;
            },
            className: 'dt-nowrap',
            orderable: false,
            searchable: false,
          },
        ];
      },
    },
    methods: {
      toIsoDateString,
      __(str) {
        return (window.__ && window.__(str)) || str;
      },
    },
  };
</script>
