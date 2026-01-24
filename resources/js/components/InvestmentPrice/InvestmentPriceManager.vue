<template>
  <div>
    <div class="row">
      <div class="col-12 col-lg-3">
        <investment-price-overview
          :investment="investment"
          :investment-prices="allPrices"
        />

        <investment-price-actions
          :investment="investment"
          @add-price="openAddModal"
          @prices-loaded="reloadData"
        />

        <date-range-selector-with-presets
          ref="dateSelector"
          :initial-date-from="dateFrom"
          :initial-date-to="dateTo"
          @update="onDateChange"
        />
      </div>
      <div class="col-12 col-lg-3">
        <investment-price-table
          ref="priceTable"
          :investment-prices="allPrices"
          :filtered-prices="displayPrices"
          :investment="investment"
          @edit-price="openEditModal"
          @delete-price="onPriceDeleted"
        />
      </div>
      <div class="col-md-6">
        <price-history-card
          ref="priceChart"
          :investment="investment"
          :prices="allPrices"
          :hide-actions="true"
        />
      </div>
    </div>

    <investment-price-modal
      ref="priceModal"
      :investment="investment"
      :edit-price="editingPrice"
      @saved="onPriceSaved"
      @close="editingPrice = null"
    />
  </div>
</template>

<script>
  import InvestmentPriceOverview from './InvestmentPriceOverview.vue';
  import InvestmentPriceActions from './InvestmentPriceActions.vue';
  import InvestmentPriceTable from './InvestmentPriceTable.vue';
  import InvestmentPriceModal from './InvestmentPriceModal.vue';
  import PriceHistoryCard from '../InvestmentDisplay/PriceHistoryCard.vue';
  import DateRangeSelectorWithPresets from '../DateRangeSelectorWithPresets.vue';
  import { __ } from '../../helpers';
  import * as toastHelpers from '../../toast';

  export default {
    name: 'InvestmentPriceManager',
    components: {
      InvestmentPriceOverview,
      InvestmentPriceActions,
      InvestmentPriceTable,
      InvestmentPriceModal,
      PriceHistoryCard,
      DateRangeSelectorWithPresets,
    },
    props: {
      investment: {
        type: Object,
        required: true,
      },
      initialPrices: {
        type: Array,
        required: true,
      },
    },
    data() {
      const today = new Date();
      const thirtyDaysAgo = new Date(today);
      thirtyDaysAgo.setDate(today.getDate() - 30);

      // Parse initial prices to avoid mutating prop
      // * Convert date strings to Date objects for easier comparison
      // * Parse price to float
      // * Sort by date ascending so that components can assume sorted data
      let rawPrices = JSON.parse(JSON.stringify(this.initialPrices));
      let parsedPrices = rawPrices
        .map((price) => ({
          ...price,
          date: new Date(price.date),
          price: parseFloat(price.price),
        }))
        .sort((a, b) => a.date - b.date);

      return {
        allPrices: parsedPrices,
        displayPrices: null,
        dateFrom: thirtyDaysAgo.toISOString().split('T')[0],
        dateTo: today.toISOString().split('T')[0],
        editingPrice: null,
        isUpdatingFromChart: false,
      };
    },
    watch: {
      dateFrom() {
        this.updateDisplayPrices();
      },
      dateTo() {
        this.updateDisplayPrices();
      },
    },
    mounted() {
      // Set default date range on mount
      this.updateDisplayPrices();
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
        this.updateDisplayPrices();
      },
      updateDisplayPrices() {
        if (!this.dateFrom && !this.dateTo) {
          // Show all prices
          this.displayPrices = null;
          return;
        }

        // Filter prices by date range
        const filtered = this.allPrices.filter((price) => {
          const priceDate = new Date(price.date);

          if (this.dateFrom && this.dateTo) {
            const fromDate = new Date(this.dateFrom);
            const toDate = new Date(this.dateTo);
            return priceDate >= fromDate && priceDate <= toDate;
          } else if (this.dateFrom) {
            const fromDate = new Date(this.dateFrom);
            return priceDate >= fromDate;
          } else if (this.dateTo) {
            const toDate = new Date(this.dateTo);
            return priceDate <= toDate;
          }

          return true;
        });

        this.displayPrices = filtered;
      },
      openAddModal() {
        this.editingPrice = null;
        this.$nextTick(() => {
          this.$refs.priceModal.show();
        });
      },
      openEditModal(price) {
        this.editingPrice = price;
        this.$nextTick(() => {
          this.$refs.priceModal.show();
        });
      },
      onPriceSaved(price, message) {
        // Show success toast
        toastHelpers.showSuccessToast(message);

        // Create a new price object with the date converted to a Date object
        const newPrice = {
          ...price,
          date: new Date(price.date),
        };

        // Update or add the price in allPrices
        const existingIndex = this.allPrices.findIndex(
          (p) => p.id === price.id,
        );
        if (existingIndex !== -1) {
          // Update existing price
          this.allPrices.splice(existingIndex, 1, newPrice);
        } else {
          // Add new price
          this.allPrices.push(newPrice);
        }

        // Sort prices by date
        this.allPrices.sort((a, b) => new Date(a.date) - new Date(b.date));

        // Update display
        this.updateDisplayPrices();

        // Force update of child components
        this.$refs.priceTable.updateTableData(
          this.displayPrices || this.allPrices,
        );
      },
      onPriceDeleted(priceId) {
        // Remove the price from allPrices
        this.allPrices = this.allPrices.filter((p) => p.id !== priceId);

        // Update display
        this.updateDisplayPrices();

        // Force update of child components
        this.$refs.priceTable.updateTableData(
          this.displayPrices || this.allPrices,
        );
      },
      async reloadData() {
        try {
          // Fetch all prices from the API
          const response = await window.axios.get(
            window.route('api.investment-price.index', {
              investment: this.investment.id,
            }),
          );

          this.allPrices = response.data.prices;

          // Update display
          this.updateDisplayPrices();

          // Force update of child components
          this.$refs.priceTable.updateTableData(
            this.displayPrices || this.allPrices,
          );
        } catch (error) {
          console.error('Failed to reload prices:', error);
        }
      },
      __,
    },
  };
</script>
