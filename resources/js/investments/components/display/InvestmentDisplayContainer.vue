<template>
  <div v-if="accounts.length > 1" class="card mb-3">
    <div
      class="card-body py-2 d-flex flex-wrap align-items-center column-gap-3 row-gap-1"
    >
      <label
        for="investment-account-filter"
        class="form-label fw-semibold mb-0 text-nowrap"
      >
        <i class="fa fa-fw fa-wallet text-muted"></i> {{ __('Account') }}
      </label>
      <select
        id="investment-account-filter"
        v-model="selectedAccountId"
        class="form-select w-auto"
        :disabled="isRefreshing"
        @change="onAccountChange"
      >
        <option :value="null">{{ __('All accounts') }}</option>
        <optgroup :label="__('Select a single account')">
          <option
            v-for="account in accounts"
            :key="account.id"
            :value="account.id"
          >
            {{ account.name }}
          </option>
        </optgroup>
      </select>
      <span
        v-if="isRefreshing"
        class="spinner-border spinner-border-sm text-secondary"
        role="status"
      ></span>
      <span v-if="loadError" class="text-danger small">
        {{ __('Could not load the investment data. Please try again.') }}
      </span>
    </div>
  </div>
  <div class="row">
    <div class="col-md-8">
      <div class="row">
        <div class="col-md-5">
          <investment-details-card :investment="localInvestment" />
          <current-assets-card :investment="localInvestment" />
          <account-distribution-card
            :accounts="localAccounts"
            :investment="localInvestment"
            :selected-account-id="selectedAccountId"
          />
        </div>

        <div class="col-md-7">
          <results-card
            :investment="localInvestment"
            :transactions="processedTransactions"
            :prices="processedPrices"
            :date-from="dateFrom"
            :date-to="dateTo"
            @update:date-from="(val) => (dateFrom = val)"
            @update:date-to="(val) => (dateTo = val)"
          />
        </div>
      </div>
      <transaction-history-card
        :transactions="processedTransactions"
        :investment="localInvestment"
        @set-date-range="onSetDateRange"
        @delete-transaction="onDeleteTransaction"
      />
    </div>
    <div class="col-md-4">
      <price-history-card
        :prices="processedPrices"
        :investment="localInvestment"
        :date-from="dateFrom"
        :date-to="dateTo"
        @update:date-from="(val) => (dateFrom = val)"
        @update:date-to="(val) => (dateTo = val)"
      />
      <quantity-history-card
        :quantities="processedQuantities"
        :investment="localInvestment"
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
  import AccountDistributionCard from './AccountDistributionCard.vue';
  import ResultsCard from './ResultsCard.vue';
  import TransactionHistoryCard from './TransactionHistoryCard.vue';
  import PriceHistoryCard from './PriceHistoryCard.vue';
  import QuantityHistoryCard from './QuantityHistoryCard.vue';

  export default {
    components: {
      InvestmentDetailsCard,
      CurrentAssetsCard,
      AccountDistributionCard,
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
      accounts: { type: Array, default: () => [] },
      initialAccount: { type: Number, default: null },
    },
    data() {
      return {
        // Local copy: the post-delete refresh must not mutate the prop
        localInvestment: { ...this.investment },
        dateFrom: null,
        dateTo: null,
        processedTransactions: [],
        processedQuantities: [],
        processedPrices: [],
        isRefreshing: false,
        selectedAccountId: this.initialAccount,
        loadError: false,
        // Refreshed with each fetch so the distribution follows deletions
        localAccounts: this.accounts,
      };
    },
    watch: {
      processedTransactions() {
        // Recalculate quantities when transactions change (e.g., when a transaction is deleted)
        this.recalculateQuantities();
      },
    },
    created() {
      // Convert date strings to Date objects once
      this.processedTransactions = this.transactions.map((tx) => ({
        ...tx,
        date: tx.date ? new Date(tx.date) : null,
      }));

      // Initialize processed prices from prop
      this.processedPrices = this.prices || [];

      this.processedQuantities = this.localInvestment.quantities.map((qty) => ({
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
              60 * 24 * 60 * 60 * 1000,
          ),
        });

        // Add a dummy value to quantities to draw before the first value. Set the date to two months before. Values are set to 0, assuming no historical quantity existed.
        this.processedQuantities.unshift({
          quantity: 0,
          schedule: 0,
          date: new Date(
            this.processedQuantities[0].date.getTime() -
              60 * 24 * 60 * 60 * 1000,
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
      onAccountChange() {
        const url = new URL(window.location.href);
        if (this.selectedAccountId) {
          url.searchParams.set('account', this.selectedAccountId);
        } else {
          url.searchParams.delete('account');
        }
        window.history.replaceState(null, '', url);

        // The date range belongs to the previous transaction set
        this.dateFrom = null;
        this.dateTo = null;
        this.refreshData();
      },
      async onDeleteTransaction(id) {
        // Remove the transaction locally first for immediate feedback
        this.processedTransactions = this.processedTransactions.filter(
          (tx) => String(tx.id) !== String(id),
        );
        await this.refreshData(true);
      },
      async refreshData(reloadOnError = false) {
        // Prevent multiple refresh attempts
        if (this.isRefreshing) return;
        this.isRefreshing = true;
        this.loadError = false;

        try {
          const response = await window.axios.get(
            `/api/v1/investments/${this.localInvestment.id}/display-data`,
            {
              params: this.selectedAccountId
                ? { account: this.selectedAccountId }
                : {},
            },
          );
          const data = response.data;

          Object.assign(this.localInvestment, data.investment);
          this.processedTransactions = (data.transactions || []).map((tx) => ({
            ...tx,
            date: tx.date ? new Date(tx.date) : null,
          }));
          if (data.accounts) {
            this.localAccounts = data.accounts;
          }
          if (data.prices) {
            this.processedPrices = data.prices;
          }
          // The processedTransactions watcher recalculates processedQuantities
        } catch (error) {
          console.error('Error refreshing investment data:', error);
          if (reloadOnError) {
            // Keep the data consistent with the server
            window.location.reload();
          } else {
            this.loadError = true;
          }
        } finally {
          this.isRefreshing = false;
        }
      },
      recalculateQuantities() {
        // Recalculate processedQuantities from investment data
        // This ensures quantity history updates when transactions change
        if (
          !this.localInvestment.quantities ||
          this.localInvestment.quantities.length === 0
        ) {
          this.processedQuantities = [];
          return;
        }

        this.processedQuantities = this.localInvestment.quantities.map(
          (qty) => ({
            ...qty,
            date: qty.date ? new Date(qty.date) : null,
          }),
        );

        if (this.processedQuantities.length > 0) {
          // Add a dummy value to quantities to draw beyond the last value. Set the date to two months ahead. Values are copied from last value.
          this.processedQuantities.push({
            ...this.processedQuantities[this.processedQuantities.length - 1],
            date: new Date(
              this.processedQuantities[
                this.processedQuantities.length - 1
              ].date.getTime() +
                60 * 24 * 60 * 60 * 1000,
            ),
          });

          // Add a dummy value to quantities to draw before the first value. Set the date to two months before. Values are set to 0, assuming no historical quantity existed.
          this.processedQuantities.unshift({
            quantity: 0,
            schedule: 0,
            date: new Date(
              this.processedQuantities[0].date.getTime() -
                60 * 24 * 60 * 60 * 1000,
            ),
          });
        }
      },
    },
  };
</script>
