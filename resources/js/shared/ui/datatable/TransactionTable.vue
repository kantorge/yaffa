<template>
  <table
    ref="dataTable"
    class="table table-bordered table-hover no-footer"
    width="100%"
  ></table>
</template>

<script>
  import { __, getDataTablesLanguageOptions } from '@/shared/lib/i18n';
  import { confirmDelete, confirmAction } from '@/shared/lib/confirm';
  import { toIsoDateString } from '@/shared/lib/helpers';
  import * as toastHelpers from '@/shared/lib/toast';
  import * as dataTableHelpers from '@/shared/lib/datatable';

  import 'datatables.net-bs5';

  const definitions = dataTableHelpers.transactionColumnDefinition;

  // Column keys that expand to one or more DataTables column definitions.
  const COLUMN_KEYS = {
    date: () =>
      definitions.dateFromCustomField(
        'date',
        __('Date'),
        window.YAFFA.userSettings.locale,
      ),
    type: () => definitions.type(true),
    fromTo: () => [
      {
        title: __('From'),
        defaultContent: '',
        data: 'config.account_from.name',
      },
      { title: __('To'), defaultContent: '', data: 'config.account_to.name' },
    ],
    category: () => definitions.category,
    amount: () => definitions.amount,
    extra: () => definitions.extra,
  };

  export default {
    name: 'TransactionTable',
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
      isActive: {
        type: Boolean,
        required: true,
      },
      // Keys of COLUMN_KEYS, or ready-made DataTables column objects
      columns: {
        type: Array,
        default: () => [
          'date',
          'type',
          'fromTo',
          'category',
          'amount',
          'extra',
        ],
      },
      // Keys of dataTablesActionButton(), plus 'setDateFrom' / 'setDateTo'.
      // A function receives the row and returns the keys for it.
      actions: {
        type: [Array, Function],
        default: () => ['quickView', 'show', 'edit', 'clone', 'delete'],
      },
      // Extra route parameters for the link actions, as a function of the row
      actionParams: {
        type: Function,
        default: () => ({}),
      },
    },
    emits: ['transaction-deleted', 'transaction-skipped', 'set-date-range'],
    data() {
      return {
        dataTable: null,
        ajaxBusy: false,
      };
    },
    watch: {
      transactions() {
        this.redrawDataTable();
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
    mounted() {
      this.initializeDataTable();
      dataTableHelpers.initializeQuickViewButton(this.$refs.dataTable);

      this._onClick = (event) => this.handleClick(event);
      this.$refs.dataTable.addEventListener('click', this._onClick);
    },
    beforeUnmount() {
      if (this.$refs.dataTable && this._onClick) {
        this.$refs.dataTable.removeEventListener('click', this._onClick);
      }

      if (this.dataTable) {
        this.dataTable.destroy();
        this.dataTable = null;
      }
    },
    methods: {
      columnDefinitions() {
        const columns = this.columns.flatMap((column) =>
          typeof column === 'string' ? COLUMN_KEYS[column]() : [column],
        );

        if (this.actions.length === 0) {
          return columns;
        }

        return [
          ...columns,
          {
            data: 'id',
            defaultContent: '',
            title: __('Actions'),
            render: (data, _type, row) => this.renderActions(data, row),
            className: 'dt-nowrap',
            orderable: false,
            searchable: false,
          },
        ];
      },

      renderActions(id, row) {
        const keys =
          typeof this.actions === 'function' ? this.actions(row) : this.actions;
        // Schedule instances are acted on through their parent transaction
        const targetId = row.schedule ? row.originalId || id : id;
        const params = this.actionParams(row);

        return keys
          .map((key) => {
            if (key === 'setDateFrom' || key === 'setDateTo') {
              const isFrom = key === 'setDateFrom';
              return `<button class="btn btn-xs btn-outline-dark set-date" data-type="${
                isFrom ? 'from' : 'to'
              }" data-date="${toIsoDateString(row.date)}" type="button" title="${
                isFrom
                  ? __('Make this the start date')
                  : __('Make this the end date')
              }"><i class="fa fa-fw fa-caret-${
                isFrom ? 'left' : 'right'
              }"></i></button> `;
            }

            return dataTableHelpers.dataTablesActionButton(
              targetId,
              key,
              params,
            );
          })
          .join('');
      },

      initializeDataTable() {
        this.dataTable = window.$(this.$refs.dataTable).DataTable({
          language: getDataTablesLanguageOptions() || undefined,
          data: this.transactions,
          processing: true,
          columns: this.columnDefinitions(),
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
        this.dataTable.rows.add(this.transactions);
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

      handleClick(event) {
        const setDate = event.target.closest('.set-date');
        if (setDate) {
          this.$emit('set-date-range', {
            type: setDate.dataset.type,
            date: setDate.dataset.date,
          });
          return;
        }

        const deleteButton = event.target.closest('[data-delete]');
        if (deleteButton) {
          this.runAction(
            deleteButton,
            confirmDelete(
              __('Are you sure you want to delete this transaction?'),
              { confirmButtonText: __('Delete') },
            ),
            (id) =>
              window.axios.delete(
                window.route('api.v1.transactions.destroy', {
                  transaction: id,
                }),
              ),
            'transaction-deleted',
            __('Transaction deleted (#:transactionId)'),
            __('Error deleting transaction (#:transactionId): :error'),
          );
          return;
        }

        const skipButton = event.target.closest('[data-skip]');
        if (skipButton) {
          this.runAction(
            skipButton,
            confirmAction(
              __('Are you sure you want to skip this scheduled instance?'),
              { icon: 'warning' },
            ),
            (id) =>
              window.axios.patch(
                window.route('api.v1.transactions.skip', { transaction: id }),
              ),
            'transaction-skipped',
            __('Scheduled instance skipped (#:transactionId)'),
            __('Error skipping scheduled instance (#:transactionId): :error'),
          );
        }
      },

      // Shared flow of the delete and skip buttons: confirm, call the API, report, emit
      runAction(
        button,
        confirmation,
        request,
        eventName,
        successText,
        errorText,
      ) {
        const transactionId = Number(button.dataset.id);
        if (
          this.ajaxBusy ||
          !Number.isFinite(transactionId) ||
          transactionId <= 0
        ) {
          return;
        }

        this.ajaxBusy = true;

        confirmation.then((result) => {
          if (!result.isConfirmed) {
            this.ajaxBusy = false;
            return;
          }

          button.classList.add('busy');

          request(transactionId)
            .then(() => {
              this.$emit(eventName, transactionId);
              toastHelpers.showSuccessToast(
                successText.replace(':transactionId', transactionId),
              );
            })
            .catch((error) => {
              toastHelpers.showErrorToast(
                errorText
                  .replace(':transactionId', transactionId)
                  .replace(
                    ':error',
                    error.response?.data?.message ||
                      error.message ||
                      __('Unknown error'),
                  ),
              );
            })
            .finally(() => {
              button.classList.remove('busy');
              this.ajaxBusy = false;
            });
        });
      },
    },
  };
</script>
