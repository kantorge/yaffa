<template>
  <div class="card">
    <div class="card-header">
      <div class="card-title">{{ __('Investment price values') }}</div>
    </div>
    <div class="card-body no-datatable-search">
      <table
        class="table table-bordered table-hover"
        role="grid"
        id="table-investment-prices"
      ></table>
    </div>
  </div>
</template>

<script>
  import 'datatables.net-bs5';
  import * as dataTableHelpers from '../dataTableHelper';
  import Swal from 'sweetalert2';
  import { __ } from '../../helpers';
  import * as toastHelpers from '../../toast';

  export default {
    name: 'InvestmentPriceTable',
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
    data() {
      return {
        table: null,
      };
    },
    watch: {
      filteredPrices: {
        handler(newPrices) {
          if (this.table) {
            this.table.clear();
            if (newPrices) {
              this.table.rows.add(newPrices);
            } else {
              // If no filtered prices, show all prices
              this.table.rows.add(this.investmentPrices);
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

        this.table = $('#table-investment-prices').DataTable({
          data: this.investmentPrices,
          columns: [
            dataTableHelpers.transactionColumnDefinition.dateFromCustomField(
              'date',
              this.__('Date'),
              window.YAFFA.locale,
            ),
            {
              data: 'price',
              title: this.__('Price'),
              render: function (data, type) {
                return dataTableHelpers.toFormattedCurrency(
                  type,
                  data,
                  window.YAFFA.locale,
                  {
                    iso_code: self.investment.currency.iso_code,
                  },
                );
              },
            },
            {
              data: 'id',
              title: this.__('Actions'),
              render: function (data, _type, row, _meta) {
                return `
                                <button class="btn btn-xs btn-primary edit-price" data-id="${data}" title="${self.__('Edit')}">
                                    <span class="fa fa-fw fa-edit"></span>
                                </button>
                                <button class="btn btn-xs btn-danger delete-price" data-id="${data}" title="${self.__('Delete')}">
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
        $('#table-investment-prices').on('click', '.edit-price', function () {
          const priceId = $(this).data('id');
          const price = self.investmentPrices.find((p) => p.id === priceId);
          if (price) {
            self.$emit('edit-price', price);
          }
        });

        // Delete button click handler
        $('#table-investment-prices').on('click', '.delete-price', function () {
          const priceId = $(this).data('id');
          const price = self.investmentPrices.find((p) => p.id === priceId);
          if (price) {
            self.confirmDelete(price);
          }
        });
      },
      confirmDelete(price) {
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
            this.deletePrice(price);
          }
        });
      },
      async deletePrice(price) {
        toastHelpers.showLoaderToast(
          this.__('Deleting price...'),
          `toast-price-${price.id}`,
        );

        try {
          await window.axios.delete(
            window.route('api.investment-price.destroy', {
              investment_price: price.id,
            }),
          );

          toastHelpers.showSuccessToast(this.__('Investment price deleted'));

          // Emit delete event
          this.$emit('delete-price', price.id);
        } catch (error) {
          toastHelpers.showErrorToast(
            error.response?.data?.message || this.__('Failed to delete price'),
          );
        } finally {
          toastHelpers.hideToast(`.toast-price-${price.id}`);
        }
      },
      updateTableData(prices) {
        if (this.table) {
          this.table.clear();
          this.table.rows.add(prices);
          this.table.draw();
        }
      },
      __,
    },
  };
</script>
