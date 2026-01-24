<template>
  <transaction-form-investment
    :action="action"
    :initial-callback="callback"
    :transaction="transactionData"
    :simplified="isSimplified"
    @cancel="onCancel"
    @success="onSuccess"
  ></transaction-form-investment>
</template>

<script>
  import { storeNotification } from '../../handle_notifications';
  import TransactionFormInvestment from './TransactionFormInvestment.vue';

  export default {
    name: 'TransactionContainerInvestment',
    components: {
      TransactionFormInvestment,
    },

    props: {
      action: String,
      transaction: {
        type: Object,
        default: {
          transaction_type: {
            name: 'Buy',
          },
          date: new Date(),
          schedule: false,
          budget: false,
          reconciled: false,
          comment: null,
          config: {
            account_id: null,
            investment_id: null,
            price: null,
            quantity: null,
            dividend: null,
            commission: null,
            tax: null,
          },
        },
      },
    },

    computed: {
      isSimplified() {
        return this.action === 'enter';
      },
    },

    created() {},

    data() {
      const urlParams = new URLSearchParams(window.location.search);

      let data = {
        // Default callback is to create a new transaction
        callback: urlParams.get('callback') || 'create',
        transactionData: Object.assign({}, this.transaction),
      };

      // Check for various default values in URL for new transactions
      if (this.action === 'create') {
        if (urlParams.get('account')) {
          data.transactionData.config.account_id = urlParams.get('account');
        }

        if (urlParams.get('investment')) {
          data.transactionData.config.investment_id =
            urlParams.get('investment');
        }

        if (urlParams.get('schedule')) {
          data.transactionData.schedule = !!urlParams.get('schedule');
          data.transactionData.date = undefined;
        }
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
          location.href = window.route('transaction.create', {
            type: 'investment',
          });
          return;
        }

        if (this.callback === 'clone') {
          location.href = window.route('transaction.open', {
            transaction: transaction.id,
            action: 'clone',
          });
          return;
        }

        if (this.callback === 'show') {
          location.href = window.route('transaction.open', {
            transaction: transaction.id,
            action: 'show',
          });
          return;
        }

        if (this.callback === 'returnToPrimaryAccount') {
          location.href = window.route('account-entity.show', {
            account_entity: transaction.config.account_id,
          });
          return;
        }

        if (this.callback === 'returnToInvestment') {
          location.href = window.route('investment.show', {
            investment: transaction.config.investment_id,
          });
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

      // Actual form was submitted. We need to proceed to the screen selected by the user.
      onSuccess(transaction, options) {
        if (['create', 'clone', 'enter', 'finalize'].includes(this.action)) {
          storeNotification(
            'success',
            __('Transaction added (#:id)', { id: transaction.id }),
            {
              dismissible: true,
            },
          );
        } else {
          storeNotification(
            'success',
            __('Transaction updated (#:id)', { id: transaction.id }),
            {
              dismissible: true,
            },
          );
        }

        this.callback = options.callback;
        this.loadCallbackUrl(transaction);
      },
    },
  };
</script>
