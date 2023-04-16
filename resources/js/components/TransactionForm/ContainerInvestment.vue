<template>
  <transaction-form-investment
      :action="action"
      :transaction="transactionData"
      @cancel="onCancel"
      @success="onSuccess"
  ></transaction-form-investment>
</template>

<script>
import TransactionFormInvestment from "./../TransactionFormInvestment.vue";

export default {
  name: 'TransactionContainerInvestment',
  components: {
    TransactionFormInvestment,
  },

  props: {
    action: String,
    transaction: Object,
  },

  computed: {},

  created() {
  },

  data() {
    let data = {
      // Default callback is to create a new transaction
      callback: 'create',
    };

    // Set some default values for new transactions
    if (this.action === 'create') {
      if (!data.transactionData) {
        data.transactionData = {};
      }
      if (!data.transactionData.config) {
        data.transactionData.config = {};
      }

      // Check for various default values in URL
      const urlParams = new URLSearchParams(window.location.search);

      if (urlParams.get('account')) {
        data.transactionData.config.account_id = urlParams.get('account');
      }

      data.transactionData.date = new Date();
      data.transactionData.transaction_type = {
        name: 'Buy',
      };
    } else {
      // For all other cases (where we expect some data to be available), copy transaction from prop
      data.transactionData = this.transaction;
    }

    return data;
  },

  methods: {
    // Decide how to proceed on success
    loadCallbackUrl(transaction) {
      if (this.callback === 'returnToDashboard') {
        location.href = window.route('home');
        return;
      }

      if (this.callback === 'create') {
        location.href = window.route('transaction.create', {type: 'investment'});
        return;
      }

      if (this.callback === 'clone') {
        location.href = window.route('transaction.open', {transaction: transaction.id, action: 'clone'});
        return;
      }

      if (this.callback === 'show') {
        location.href = window.route('transaction.open', {transaction: transaction.id, action: 'show'});
        return;
      }

      if (this.callback === 'returnToAccount') {
        location.href = window.route('account.history', {account: this.transaction.config.account_id('primary', transaction)});
        return;
      }

      // Default, return back
      if (document.referrer) {
        location.href = document.referrer;
      } else {
        history.back();
      }
    },

    // Actual form was cancelled. We need to return to the previous page.
    onCancel() {
      window.history.back();
    },

    // Actual form was submitted. We need to return to proceed as selected by user.
    onSuccess(transaction, options) {
      this.callback = options.callback;
      this.loadCallbackUrl(transaction);
    },
  },
}
</script>
