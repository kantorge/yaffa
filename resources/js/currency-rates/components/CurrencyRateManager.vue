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

        <date-range-filter-card
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
          :is-loading="isLoadingRates"
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
  import DateRangeFilterCard from '@/shared/ui/date/DateRangeFilterCard.vue';
  import { filterByDateRange } from '@/shared/lib/date/filterByRange';
  import { __ } from '@/shared/lib/i18n';
  import * as toastHelpers from '@/shared/lib/toast';

  export default {
    name: 'CurrencyRateManager',
    components: {
      CurrencyRateOverview,
      CurrencyRateActions,
      CurrencyRateTable,
      CurrencyRateChart,
      CurrencyRateModal,
      DateRangeFilterCard,
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
        default: () => [],
      },
    },
    data() {
      return {
        fromCurrency: this.from,
        toCurrency: this.to,
        // Shallow-copy each item so later edits (splice/push in onRateSaved) never mutate the prop.
        allRates: this.initialRates.map((rate) => ({ ...rate })),
        displayRates: null,
        dateFrom: null,
        dateTo: null,
        editingRate: null,
        isLoadingRates: false,
        isUpdatingFromChart: false,
      };
    },
    async mounted() {
      if (this.initialRates.length === 0) {
        await this.reloadData();
      }
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
      updateDisplayRates() {
        // No range selected: show all rates (null is the Table's "show everything" sentinel).
        this.displayRates = (!this.dateFrom && !this.dateTo)
          ? null
          : filterByDateRange(this.allRates, 'date', this.dateFrom, this.dateTo);
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
        toastHelpers.showSuccessToast(message);

        // Update or add the rate in allRates
        const existingIndex = this.allRates.findIndex(
          (r) => r.id === rate.id,
        );
        if (existingIndex !== -1) {
          // Update existing rate
          this.allRates.splice(existingIndex, 1, rate);
        } else {
          // Add new rate
          this.allRates.push(rate);
        }

        // Sort rates by date
        this.allRates.sort((a, b) => new Date(a.date) - new Date(b.date));
        this.allRates = [...this.allRates];

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
        this.isLoadingRates = true;

        try {
          // Fetch all rates from the API
          const response = await window.axios.get(
            this.route('api.v1.currency-rates.index', {
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
        } finally {
          this.isLoadingRates = false;
        }
      },
      __,
    },
  };
</script>
