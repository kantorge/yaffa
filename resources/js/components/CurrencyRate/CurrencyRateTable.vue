<template>
  <div class="card">
    <div class="card-header">
      <div class="card-title">{{ __('Currency rate values') }}</div>
    </div>
    <div class="card-body no-datatable-search">
      <table
        class="table table-bordered table-hover"
        role="grid"
        id="ratesTable"
      ></table>
    </div>
  </div>
</template>

<script>
  import 'datatables.net-bs5';
  import * as dataTableHelpers from '../dataTableHelper';
  import Swal from 'sweetalert2';
  import { __ } from '../../helpers';

  export default {
    name: 'CurrencyRateTable',
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
    data() {
      return {
        table: null,
      };
    },
    watch: {
      filteredRates: {
        handler(newRates) {
          if (this.table) {
            this.table.clear();
            if (newRates) {
              this.table.rows.add(newRates);
            } else {
              // If no filtered rates, show all rates
              this.table.rows.add(this.currencyRates);
            }
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

        this.table = $('#ratesTable').DataTable({
          data: this.currencyRates,
          columns: [
            dataTableHelpers.transactionColumnDefinition.dateFromCustomField(
              'date',
              this.__('Date'),
              window.YAFFA.locale,
            ),
            {
              data: 'rate',
              title: this.__('Rate'),
              render: function (data, type) {
                return dataTableHelpers.toFormattedCurrency(
                  type,
                  data,
                  window.YAFFA.locale,
                  Object.assign({}, self.toCurrency, { max_digits: 4 }),
                );
              },
            },
            {
              data: 'id',
              title: this.__('Actions'),
              render: function (data, _type, row, _meta) {
                return `
                                <button class="btn btn-xs btn-primary edit-rate" data-id="${data}" title="${self.__('Edit')}">
                                    <span class="fa fa-edit"></span>
                                </button>
                                <button class="btn btn-xs btn-danger delete-rate" data-id="${data}" title="${self.__('Delete')}">
                                    <span class="fa fa-trash"></span>
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
        $('#ratesTable').on('click', '.edit-rate', function () {
          const rateId = $(this).data('id');
          const rate = self.currencyRates.find((r) => r.id === rateId);
          if (rate) {
            self.$emit('edit-rate', rate);
          }
        });

        // Delete button click handler
        $('#ratesTable').on('click', '.delete-rate', function () {
          const rateId = $(this).data('id');
          const rate = self.currencyRates.find((r) => r.id === rateId);
          if (rate) {
            self.confirmDelete(rate);
          }
        });
      },
      confirmDelete(rate) {
        Swal.fire({
          animation: false,
          text: this.__('Are you sure to want to delete this item?'),
          icon: 'warning',
          showCancelButton: true,
          cancelButtonText: this.__('Cancel'),
          confirmButtonText: this.__('Confirm'),
          buttonsStyling: false,
          customClass: {
            confirmButton: 'btn btn-danger',
            cancelButton: 'btn btn-outline-secondary ms-3',
          },
        }).then((result) => {
          if (result.isConfirmed) {
            this.deleteRate(rate);
          }
        });
      },
      async deleteRate(rate) {
        // Show loading toast
        const loadingEvent = new CustomEvent('toast', {
          detail: {
            body: this.__('Deleting rate...'),
            toastClass: `bg-info toast-rate-${rate.id}`,
            delay: Infinity,
          },
        });
        window.dispatchEvent(loadingEvent);

        try {
          await window.axios.delete(
            window.route('api.currency-rate.destroy', {
              currency_rate: rate.id,
            }),
          );

          // Show success toast
          const successEvent = new CustomEvent('toast', {
            detail: {
              header: this.__('Success'),
              body: this.__('Currency rate deleted'),
              toastClass: 'bg-success',
            },
          });
          window.dispatchEvent(successEvent);

          // Emit delete event
          this.$emit('delete-rate', rate.id);
        } catch (error) {
          // Show error toast
          const errorEvent = new CustomEvent('toast', {
            detail: {
              header: this.__('Error'),
              body:
                error.response?.data?.message ||
                this.__('Failed to delete rate'),
              toastClass: 'bg-danger',
            },
          });
          window.dispatchEvent(errorEvent);
        } finally {
          // Close loading toast
          setTimeout(() => {
            const toastElement = document.querySelector(
              `.toast-rate-${rate.id}`,
            );
            if (toastElement) {
              const toastInstance = new window.bootstrap.Toast(toastElement);
              toastInstance.hide();
            }
          }, 250);
        }
      },
      updateTableData(rates) {
        if (this.table) {
          this.table.clear();
          this.table.rows.add(rates);
          this.table.draw();
        }
      },
      __,
    },
  };
</script>
