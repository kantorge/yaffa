<template>
  <div>
    <show-standard
      v-if="isStandardTransaction"
      :transaction="transaction"
    ></show-standard>
    <show-investment
      v-else-if="isInvestmentTransaction"
      :transaction="transaction"
    ></show-investment>

    <div class="row">
      <div class="col-12">
        <div class="card mb-3">
          <div class="card-body">
            <action-button-bar
              :transaction="transaction"
              :is-modal="false"
              @transactionUpdated="transactionUpdated"
            ></action-button-bar>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
  import ShowStandard from './ShowStandard.vue';
  import ShowInvestment from './ShowInvestment.vue';
  import ActionButtonBar from './ActionButtonBar.vue';
  import { processTransaction } from '@/shared/lib/helpers';

  export default {
    name: 'TransactionDisplayContainer',
    components: {
      ShowStandard,
      ShowInvestment,
      ActionButtonBar,
    },

    data() {
      // window.transaction is raw JSON (Laracasts\Utilities\JavaScript::put()) - its
      // Money/BigDecimal fields are decimal strings, never normalized the way an axios
      // response is. Route it through the same processTransaction() every other consumer uses.
      return {
        transaction: processTransaction(JSON.parse(JSON.stringify(window.transaction))),
      };
    },

    computed: {
      isStandardTransaction() {
        return this.transaction.config_type === 'standard';
      },

      isInvestmentTransaction() {
        return this.transaction.config_type === 'investment';
      },
    },

    methods: {
      transactionUpdated: function (transaction) {
        this.transaction = Object.assign({}, transaction);
      },
    },
  };
</script>
