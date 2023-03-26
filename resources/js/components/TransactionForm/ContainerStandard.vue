<template>
      <transaction-form-standard
          :action = "action"
          :transaction = "transactionData"
          @cancel="onCancel"
          @success="onSuccess"
      ></transaction-form-standard>
</template>

<script>
    import TransactionFormStandard from './../TransactionFormStandard.vue'
    import {Button} from 'vform/src/components/bootstrap5'

    export default {
        name: 'TransactionContainerStandard',
        components: {
            TransactionFormStandard,
            Button
        },

        props: {
            action: String,
            transaction: Object,
        },

        computed: {},

        created() {},

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

                if (urlParams.get('account_from')) {
                    data.transactionData.config.account_from_id = urlParams.get('account_from');
                }

                data.transactionData.date = new Date();
                data.transactionData.transaction_type = {
                    name: 'withdrawal',
                };
            } else {
                // For all other cases (where we expect some data to be available), copy transaction from prop
                data.transactionData = this.transaction;
            }

            return data;
        },

        methods: {
            // Determine, which account to use as a callback, if user wants to return to selected account
            getReturnAccount(accountType, transaction) {
                if (accountType === 'primary' && transaction.transaction_type.name === 'deposit') {
                    return transaction.config.account_to_id;
                }

                if (accountType === 'secondary') {
                    return transaction.config.account_to_id;
                }

                // Withdrawal and transfer primary
                return transaction.config.account_from_id;
            },

            // Decide how to proceed on success
            loadCallbackUrl(transaction) {
                if (this.callback === 'returnToDashboard') {
                    location.href = route('home');
                    return;
                }

                if (this.callback === 'create') {
                    location.href = route('transactions.createStandard');
                    return;
                }

                if (this.callback === 'clone') {
                    location.href = route('transactions.open.standard', { transaction: transaction.id, action: 'clone' });
                    return;
                }

                if (this.callback === 'returnToPrimaryAccount') {
                    location.href = route('account.history', { account: this.getReturnAccount('primary', transaction) });
                    return;
                }

                if (this.callback === 'returnToSecondaryAccount') {
                    location.href = route('account.history', { account: this.getReturnAccount('secondary', transaction) });
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
