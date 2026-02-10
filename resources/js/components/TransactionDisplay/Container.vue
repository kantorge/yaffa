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

  export default {
    name: 'TransactionDisplayContainer',
    components: {
      ShowStandard,
      ShowInvestment,
      ActionButtonBar,
    },

    data() {
      return {
        transaction: Object.assign({}, window.transaction),
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
