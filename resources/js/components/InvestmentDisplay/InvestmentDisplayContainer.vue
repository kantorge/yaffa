<template>
  <div class="row">
    <div class="col-md-8">
      <div class="row">
        <div class="col-md-5">
          <investment-details-card :investment="investment" />
          <current-assets-card :investment="investment" />
        </div>

        <div class="col-md-7">
          <results-card
            :investment="investment"
            :transactions="processedTransactions"
            :prices="prices"
            :date-from="dateFrom"
            :date-to="dateTo"
            @update:date-from="(val) => (dateFrom = val)"
            @update:date-to="(val) => (dateTo = val)"
          />
        </div>
      </div>
      <transaction-history-card
        :transactions="processedTransactions"
        :investment="investment"
        @set-date-range="onSetDateRange"
        @delete-transaction="onDeleteTransaction"
      />
    </div>
    <div class="col-md-4">
      <price-history-card
        :prices="prices"
        :investment="investment"
        :date-from="dateFrom"
        :date-to="dateTo"
        @update:date-from="(val) => (dateFrom = val)"
        @update:date-to="(val) => (dateTo = val)"
      />
      <quantity-history-card
        :quantities="processedQuantities"
        :investment="investment"
        :date-from="dateFrom"
        :date-to="dateTo"
        @update:date-from="(val) => (dateFrom = val)"
        @update:date-to="(val) => (dateTo = val)"
      />
    </div>
  </div>
</template>

<script>
  import InvestmentDetailsCard from './InvestmentDetailsCard.vue';
  import CurrentAssetsCard from './CurrentAssetsCard.vue';
  import ResultsCard from './ResultsCard.vue';
  import TransactionHistoryCard from './TransactionHistoryCard.vue';
  import PriceHistoryCard from './PriceHistoryCard.vue';
  import QuantityHistoryCard from './QuantityHistoryCard.vue';

  export default {
    components: {
      InvestmentDetailsCard,
      CurrentAssetsCard,
      ResultsCard,
      TransactionHistoryCard,
      PriceHistoryCard,
      QuantityHistoryCard,
    },
    props: {
      investment: Object,
      transactions: Array,
      prices: Array,
      quantities: Array,
    },
    data() {
      return {
        dateFrom: null,
        dateTo: null,
        processedTransactions: [],
        processedQuantities: [],
      };
    },
    created() {
      // Convert date strings to Date objects once
      this.processedTransactions = this.transactions.map((tx) => ({
        ...tx,
        date: tx.date ? new Date(tx.date) : null,
      }));

      this.processedQuantities = this.investment.quantities.map((qty) => ({
        ...qty,
        date: qty.date ? new Date(qty.date) : null,
      }));

      if (this.processedQuantities.length > 0) {
        // Add a dummy value to quantities to draw beyond the last value. Set the date to two months ahead. Values are copied from last value.
        this.processedQuantities.push({
          ...this.processedQuantities[this.processedQuantities.length - 1],
          date: new Date(
            this.processedQuantities[
              this.processedQuantities.length - 1
            ].date.getTime() +
              60 * 24 * 60 * 60 * 1000
          ),
        });

        // Add a dummy value to quantities to draw before the first value. Set the date to two months before. Values are set to 0, assuming no historical quantity existed.
        this.processedQuantities.unshift({
          quantity: 0,
          schedule: 0,
          date: new Date(
            this.processedQuantities[0].date.getTime() -
              60 * 24 * 60 * 60 * 1000
          ),
        });
      }
    },
    methods: {
      onSetDateRange({ type, date }) {
        if (type === 'from') {
          this.dateFrom = new Date(date);
        } else if (type === 'to') {
          this.dateTo = new Date(date);
        }
      },
      onDeleteTransaction(id) {
        // Remove the transaction from processedTransactions
        this.processedTransactions = this.processedTransactions.filter(
          (tx) => String(tx.id) !== String(id)
        );
      },
    },
  };
</script>
