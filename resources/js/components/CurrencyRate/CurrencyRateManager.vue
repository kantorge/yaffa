<template>
  <div>
    <div class="row">
      <div class="col-12 col-lg-3">
        <currency-rate-overview
          :from="fromCurrency"
          :to="toCurrency"
          :currency-rates="allRates"
        />

        <currency-rate-actions
          :from-currency="fromCurrency"
          :date-from="dateFrom"
          :date-to="dateTo"
          @add-rate="openAddModal"
          @rates-loaded="reloadData"
        />

        <date-range-selector-with-presets
          ref="dateSelector"
          :initial-date-from="dateFrom"
          :initial-date-to="dateTo"
          @update="onDateChange"
        />
      </div>
      <div class="col-12 col-lg-3">
        <currency-rate-table
          ref="rateTable"
          :currency-rates="allRates"
          :filtered-rates="displayRates"
          :from-currency="fromCurrency"
          :to-currency="toCurrency"
          @edit-rate="openEditModal"
          @delete-rate="onRateDeleted"
        />
      </div>
      <div class="col-md-6">
        <currency-rate-chart
          ref="rateChart"
          :currency-rates="allRates"
          :to-currency="toCurrency"
          :date-from="dateFrom"
          :date-to="dateTo"
          @date-range-selected="onChartDateRangeSelected"
        />
      </div>
    </div>

    <currency-rate-modal
      ref="rateModal"
      :from-currency="fromCurrency"
      :to-currency="toCurrency"
      :edit-rate="editingRate"
      @saved="onRateSaved"
      @close="editingRate = null"
    />
  </div>
</template>

<script>
  import CurrencyRateOverview from './CurrencyRateOverview.vue';
  import CurrencyRateActions from './CurrencyRateActions.vue';
  import CurrencyRateTable from './CurrencyRateTable.vue';
  import CurrencyRateChart from './CurrencyRateChart.vue';
  import CurrencyRateModal from './CurrencyRateModal.vue';
  import DateRangeSelectorWithPresets from '../DateRangeSelectorWithPresets.vue';
  import { __ } from '../../helpers';

  export default {
    name: 'CurrencyRateManager',
    components: {
      CurrencyRateOverview,
      CurrencyRateActions,
      CurrencyRateTable,
      CurrencyRateChart,
      CurrencyRateModal,
      DateRangeSelectorWithPresets,
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
      initialRates: {
        type: Array,
        required: true,
      },
    },
    data() {
      return {
        fromCurrency: this.from,
        toCurrency: this.to,
        allRates: [...this.initialRates],
        displayRates: null,
        dateFrom: null,
        dateTo: null,
        editingRate: null,
        isUpdatingFromChart: false,
      };
    },
    watch: {
      dateFrom() {
        this.updateDisplayRates();
      },
      dateTo() {
        this.updateDisplayRates();
      },
    },
    methods: {
      onDateChange({ dateFrom, dateTo }) {
        // Prevent circular updates when chart is updating the date selector
        if (this.isUpdatingFromChart) {
          return;
        }
        
        this.dateFrom = dateFrom;
        this.dateTo = dateTo;
        // Force table update when dates are cleared
        this.updateDisplayRates();
      },
      onChartDateRangeSelected({ dateFrom, dateTo }) {
        // Set flag to prevent circular updates
        this.isUpdatingFromChart = true;
        
        // Update the date selector component when chart selection changes
        this.dateFrom = dateFrom;
        this.dateTo = dateTo;
        this.updateDisplayRates();

        // Update the DateRangeSelector component if we have a ref to it
        // Note: This creates a bidirectional sync between chart and date selector
        if (
          this.$refs.dateSelector &&
          this.$refs.dateSelector.dateRangePicker
        ) {
          this.$refs.dateSelector.dateRangePicker.setDates(
            new Date(dateFrom),
            new Date(dateTo),
          );
        }
        
        // Reset flag after a short delay to allow the date picker to update
        this.$nextTick(() => {
          setTimeout(() => {
            this.isUpdatingFromChart = false;
          }, 100);
        });
      },
      updateDisplayRates() {
        if (!this.dateFrom && !this.dateTo) {
          // Show all rates
          this.displayRates = null;
          return;
        }

        // Filter rates by date range
        const filtered = this.allRates.filter((rate) => {
          const rateDate = new Date(rate.date);

          if (this.dateFrom && this.dateTo) {
            const fromDate = new Date(this.dateFrom);
            const toDate = new Date(this.dateTo);
            return rateDate >= fromDate && rateDate <= toDate;
          } else if (this.dateFrom) {
            const fromDate = new Date(this.dateFrom);
            return rateDate >= fromDate;
          } else if (this.dateTo) {
            const toDate = new Date(this.dateTo);
            return rateDate <= toDate;
          }

          return true;
        });

        this.displayRates = filtered;
      },
      openAddModal() {
        this.editingRate = null;
        this.$nextTick(() => {
          this.$refs.rateModal.show();
        });
      },
      openEditModal(rate) {
        this.editingRate = rate;
        this.$nextTick(() => {
          this.$refs.rateModal.show();
        });
      },
      onRateSaved(rate, message) {
        // Show success toast
        const successEvent = new CustomEvent('toast', {
          detail: {
            header: this.__('Success'),
            body: message,
            toastClass: 'bg-success',
          },
        });
        window.dispatchEvent(successEvent);

        // Update or add the rate in allRates
        const existingIndex = this.allRates.findIndex((r) => r.id === rate.id);
        if (existingIndex !== -1) {
          // Update existing rate
          this.allRates.splice(existingIndex, 1, rate);
        } else {
          // Add new rate
          this.allRates.push(rate);
        }

        // Sort rates by date
        this.allRates.sort((a, b) => new Date(a.date) - new Date(b.date));

        // Update display
        this.updateDisplayRates();

        // Force update of child components
        this.$refs.rateTable.updateTableData(
          this.displayRates || this.allRates,
        );
        this.$refs.rateChart.updateChart(this.allRates);
      },
      onRateDeleted(rateId) {
        // Remove the rate from allRates
        this.allRates = this.allRates.filter((r) => r.id !== rateId);

        // Update display
        this.updateDisplayRates();

        // Force update of child components
        this.$refs.rateTable.updateTableData(
          this.displayRates || this.allRates,
        );
        this.$refs.rateChart.updateChart(this.allRates);
      },
      async reloadData() {
        try {
          // Fetch all rates from the API
          const response = await window.axios.get(
            window.route('api.currency-rate.index', {
              from: this.fromCurrency.id,
              to: this.toCurrency.id,
            }),
          );

          this.allRates = response.data.rates;

          // Update display
          this.updateDisplayRates();

          // Force update of child components
          this.$refs.rateTable.updateTableData(
            this.displayRates || this.allRates,
          );
          this.$refs.rateChart.updateChart(this.allRates);
        } catch (error) {
          console.error('Failed to reload rates:', error);
        }
      },
      __,
    },
  };
</script>
