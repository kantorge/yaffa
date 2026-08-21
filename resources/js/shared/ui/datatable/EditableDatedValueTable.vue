<template>
  <data-table-card :title="title">
    <table
      :id="tableId"
      ref="table"
      class="table table-bordered table-hover"
      role="grid"
    ></table>
  </data-table-card>
</template>

<script>
  import 'datatables.net-bs5';
  import * as dataTableHelpers from '@/shared/lib/datatable';
  import { confirmDelete } from '@/shared/lib/confirm';
  import { __, getDataTablesLanguageOptions } from '@/shared/lib/i18n';
  import * as toastHelpers from '@/shared/lib/toast';
  import DataTableCard from './DataTableCard.vue';

  // Shared skeleton behind CurrencyRateTable.vue and InvestmentPriceTable.vue (see T-14 in
  // .ai/docs/specifications/frontend-review/tasks.md): a date + single-value DataTable with
  // edit/delete row actions. Field names, the value's currency, and delete-route/messages are
  // parameterized by the caller; everything else (DataTables config shape, the edit/delete
  // button markup, the confirm+delete flow via the shared confirm helper, updateTableData) is
  // identical between the two original components and lives here once.
  export default {
    name: 'EditableDatedValueTable',
    components: {
      DataTableCard,
    },
    props: {
      title: {
        type: String,
        required: true,
      },
      // DOM id of the underlying <table> - kept a required prop (rather than an
      // auto-generated id) because Dusk browser tests select on the exact original ids
      // (#ratesTable, #table-investment-prices).
      tableId: {
        type: String,
        required: true,
      },
      // Suffix for the edit/delete row-action button classes, e.g. 'rate' -> 'edit-rate' /
      // 'delete-rate'. Same reasoning as tableId: Dusk tests click these exact classes.
      actionPrefix: {
        type: String,
        required: true,
      },
      items: {
        type: Array,
        required: true,
      },
      filteredItems: {
        type: Array,
        default: null,
      },
      valueField: {
        type: String,
        required: true,
      },
      valueLabel: {
        type: String,
        required: true,
      },
      currency: {
        type: Object,
        required: true,
      },
      deleteRoute: {
        type: String,
        required: true,
      },
      deleteRouteParam: {
        type: String,
        required: true,
      },
      deletingMessage: {
        type: String,
        required: true,
      },
      deletedMessage: {
        type: String,
        required: true,
      },
      deleteFailedMessage: {
        type: String,
        required: true,
      },
    },
    emits: ['edit-item', 'delete-item'],
    data() {
      return {
        table: null,
      };
    },
    watch: {
      filteredItems: {
        handler(newItems) {
          if (this.table) {
            this.table.clear();
            this.table.rows.add(newItems || this.items);
            this.table.draw();
          }
        },
        deep: true,
      },
    },
    mounted() {
      this.initializeTable();
    },
    beforeUnmount() {
      if (this.table) {
        this.table.destroy();
      }
    },
    methods: {
      initializeTable() {
        const self = this;
        const $table = $(this.$refs.table);
        const editClass = `edit-${this.actionPrefix}`;
        const deleteClass = `delete-${this.actionPrefix}`;

        this.table = $table.DataTable({
          language: getDataTablesLanguageOptions() || undefined,
          data: this.items,
          columns: [
            dataTableHelpers.transactionColumnDefinition.dateFromCustomField(
              'date',
              this.__('Date'),
              window.YAFFA.userSettings.locale,
            ),
            {
              data: this.valueField,
              title: this.valueLabel,
              render: function (data, type) {
                return dataTableHelpers.toFormattedCurrency(
                  type,
                  data,
                  window.YAFFA.userSettings.locale,
                  self.currency,
                  'detailed',
                );
              },
            },
            {
              data: 'id',
              title: this.__('Actions'),
              render: function (data, _type, _row, _meta) {
                return `
                                <button class="btn btn-xs btn-primary ${editClass}" data-id="${data}" title="${self.__('Edit')}">
                                    <span class="fa fa-fw fa-edit"></span>
                                </button>
                                <button class="btn btn-xs btn-danger ${deleteClass}" data-id="${data}" title="${self.__('Delete')}">
                                    <span class="fa fa-fw fa-trash"></span>
                                </button>
                            `;
              },
              className: 'dt-nowrap',
              orderable: false,
              searchable: false,
            },
          ],
          order: [[0, 'desc']],
          deferRender: true,
          scrollY: '500px',
          scrollCollapse: true,
          stateSave: false,
          processing: true,
          paging: false,
          info: false,
        });

        // Edit button click handler
        $table.on('click', `.${editClass}`, function () {
          const id = $(this).data('id');
          const item = self.items.find((i) => i.id === id);
          if (item) {
            self.$emit('edit-item', item);
          }
        });

        // Delete button click handler
        $table.on('click', `.${deleteClass}`, function () {
          const id = $(this).data('id');
          const item = self.items.find((i) => i.id === id);
          if (item) {
            self.confirmDeleteItem(item);
          }
        });
      },
      confirmDeleteItem(item) {
        confirmDelete(
          this.__('Are you sure to want to delete this item?'),
        ).then((result) => {
          if (result.isConfirmed) {
            this.deleteItem(item);
          }
        });
      },
      async deleteItem(item) {
        const toastClass = `toast-item-${item.id}`;

        toastHelpers.showLoaderToast(this.deletingMessage, toastClass);

        try {
          await window.axios.delete(
            this.route(this.deleteRoute, {
              [this.deleteRouteParam]: item.id,
            }),
          );

          toastHelpers.showSuccessToast(this.deletedMessage);

          this.$emit('delete-item', item.id);
        } catch (error) {
          toastHelpers.showErrorToast(
            error.response?.data?.message || this.deleteFailedMessage,
          );
        } finally {
          toastHelpers.hideToast(`.${toastClass}`);
        }
      },
      updateTableData(items) {
        if (!this.table) {
          return;
        }
        this.table.clear();
        this.table.rows.add(items);
        this.table.draw();
      },
      __,
    },
  };
</script>
