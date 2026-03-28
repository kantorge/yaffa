<template>
  <div>
    <div
      v-if="drillDownFilter"
      class="alert alert-warning d-flex flex-wrap align-items-center justify-content-between gap-2"
      role="alert"
    >
      <span>
        {{
          __(
            'Showing a filtered subset of transactions from monthly breakdown drill-down.',
          )
        }}
      </span>
      <div class="d-flex gap-2">
        <button
          type="button"
          class="btn btn-sm btn-outline-secondary"
          @click="$emit('return-to-monthly-breakdown')"
        >
          {{ __('Return to monthly breakdown') }}
        </button>
        <button
          type="button"
          class="btn btn-sm btn-warning"
          @click="$emit('clear-drill-down-filter')"
        >
          {{ __('Clear additional filtering') }}
        </button>
      </div>
    </div>

    <table
      class="table table-bordered table-hover no-footer"
      ref="dataTable"
    ></table>
  </div>
</template>

<script>
  import Swal from 'sweetalert2';
  import { __, getDataTablesLanguageOptions } from '@/shared/lib/i18n';
  import * as toastHelpers from '@/shared/lib/toast';
  import * as dataTableHelpers from '@/shared/lib/datatable';

  import 'datatables.net-bs5';

  export default {
    name: 'ReportingCanvasFindTransactionsTransactionList',
    emits: [
      'return-to-monthly-breakdown',
      'clear-drill-down-filter',
      'transaction-deleted',
    ],
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
      drillDownFilter: {
        type: Object,
        required: false,
        default: null,
      },
      isActive: {
        type: Boolean,
        required: true,
      },
    },
    data() {
      return {
        dataTable: null,
        ajaxDeleteBusy: false,
      };
    },
    computed: {
      listTransactions() {
        if (!this.drillDownFilter) {
          return this.transactions;
        }

        const month = this.drillDownFilter.month;
        const categorySet = new Set(this.drillDownFilter.categories);

        return this.transactions.filter((transaction) => {
          if (!(transaction.date instanceof Date)) {
            return false;
          }

          if (transaction.year_month !== month) {
            return false;
          }

          const transactionCategories = transaction.categories || [];
          return transactionCategories.some(
            (category) => category && categorySet.has(String(category.id)),
          );
        });
      },
    },
    watch: {
      listTransactions: {
        handler() {
          this.redrawDataTable();
        },
        deep: true,
      },
      busy(newBusy) {
        if (this.dataTable) {
          this.dataTable.processing(newBusy);
        }
      },
      isActive(newIsActive) {
        if (newIsActive) {
          this.refreshLayout();
        }
      },
    },
    methods: {
      initializeDataTable() {
        this.dataTable = window.$(this.$refs.dataTable).DataTable({
          language: getDataTablesLanguageOptions() || undefined,
          data: this.listTransactions,
          processing: true,
          columns: [
            dataTableHelpers.transactionColumnDefinition.dateFromCustomField(
              'date',
              __('Date'),
              window.YAFFA.userSettings.locale,
            ),
            dataTableHelpers.transactionColumnDefinition.type(true),
            {
              title: __('From'),
              defaultContent: '',
              data: 'config.account_from.name',
            },
            {
              title: __('To'),
              defaultContent: '',
              data: 'config.account_to.name',
            },
            dataTableHelpers.transactionColumnDefinition.category,
            dataTableHelpers.transactionColumnDefinition.amount,
            dataTableHelpers.transactionColumnDefinition.extra,
            {
              data: 'id',
              defaultContent: '',
              title: __('Actions'),
              render: function (data) {
                return (
                  dataTableHelpers.dataTablesActionButton(data, 'quickView') +
                  dataTableHelpers.dataTablesActionButton(data, 'show') +
                  dataTableHelpers.dataTablesActionButton(data, 'edit') +
                  dataTableHelpers.dataTablesActionButton(data, 'clone') +
                  dataTableHelpers.dataTablesActionButton(data, 'delete')
                );
              },
              className: 'dt-nowrap',
              orderable: false,
              searchable: false,
            },
          ],
          order: [[0, 'asc']],
        });

        if (this.busy) {
          this.dataTable.processing(true);
        }
      },

      redrawDataTable() {
        if (!this.dataTable) {
          return;
        }

        this.dataTable.clear();
        this.dataTable.rows.add(this.listTransactions);
        this.dataTable.draw(false);
      },

      refreshLayout() {
        if (!this.dataTable) {
          return;
        }

        this.dataTable.columns.adjust();

        if (this.dataTable.responsive && this.dataTable.responsive.recalc) {
          this.dataTable.responsive.recalc();
        }

        this.dataTable.draw(false);
      },

      handleDeleteClick(event) {
        const button = event.target.closest('[data-delete]');
        if (!button || this.ajaxDeleteBusy) {
          return;
        }

        const transactionId = Number(button.dataset.id);
        if (!Number.isFinite(transactionId) || transactionId <= 0) {
          return;
        }

        this.ajaxDeleteBusy = true;

        Swal.fire({
          text: __('Are you sure you want to delete this transaction?'),
          icon: 'warning',
          showCancelButton: true,
          cancelButtonText: __('Cancel'),
          confirmButtonText: __('Delete'),
          buttonsStyling: false,
          customClass: {
            confirmButton: 'btn btn-danger',
            cancelButton: 'btn btn-outline-secondary ms-3',
          },
        }).then((result) => {
          if (!result.isConfirmed) {
            this.ajaxDeleteBusy = false;
            return;
          }

          button.classList.add('busy');

          window.axios
            .delete(
              window.route('api.v1.transactions.destroy', {
                transaction: transactionId,
              }),
            )
            .then(() => {
              this.$emit('transaction-deleted', transactionId);
              toastHelpers.showSuccessToast(
                __('Transaction deleted (#:transactionId)', {
                  transactionId,
                }),
              );
            })
            .catch((error) => {
              toastHelpers.showErrorToast(
                __('Error deleting transaction (#:transactionId): :error', {
                  transactionId,
                  error:
                    error.response?.data?.message ||
                    error.message ||
                    __('Unknown error'),
                }),
              );
            })
            .finally(() => {
              button.classList.remove('busy');
              this.ajaxDeleteBusy = false;
            });
        });
      },

      __,
    },
    mounted() {
      this.initializeDataTable();
      dataTableHelpers.initializeQuickViewButton(this.$refs.dataTable);

      this._onDeleteClick = (event) => this.handleDeleteClick(event);
      this.$refs.dataTable.addEventListener('click', this._onDeleteClick);
    },
    beforeUnmount() {
      if (this.$refs.dataTable && this._onDeleteClick) {
        this.$refs.dataTable.removeEventListener('click', this._onDeleteClick);
      }

      if (this.dataTable) {
        this.dataTable.destroy();
        this.dataTable = null;
      }
    },
  };
</script>
