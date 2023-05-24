<template>
    <transaction-form-standard
            :action="action"
            :initial-callback="callback"
            :transaction="transactionData"
            @cancel="onCancel"
            @success="onSuccess"
    ></transaction-form-standard>
</template>

<script>
import TransactionFormStandard from './../TransactionFormStandard.vue'

export default {
    name: 'TransactionContainerStandard',
    components: {
        TransactionFormStandard,
    },

    props: {
        action: {
            type: String,
            default: 'create',
        },
        transaction: {
            type: Object,
            default: {
                transaction_type: {
                    name: 'withdrawal',
                },
                date: new Date(),
                schedule: false,
                budget: false,
                reconciled: false,
                comment: null,
                config: {
                    account_from_id: null,
                    account_to_id: null,
                    amount_from: null,
                    amount_to: null,
                },
            }
        },
    },

    computed: {},

    created() {
    },

    data() {
        const urlParams = new URLSearchParams(window.location.search);

        let data = {
            // Default callback is to create a new transaction
            callback: urlParams.get('callback') || 'create',
            transactionData: Object.assign({}, this.transaction)
        };

        // Check for various default values in URL for new transactions
        if (this.action === 'create') {
            if (urlParams.get('account_from')) {
                data.transactionData.config.account_from_id = urlParams.get('account_from');
            }

            if (urlParams.get('account_to')) {
                data.transactionData.config.account_from_id = urlParams.get('account_to');
            }

            if (urlParams.get('schedule')) {
                data.transactionData.schedule = !!urlParams.get('schedule');
                data.transactionData.date = undefined;
            }
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
                location.href = window.route('home');
                return;
            }

            if (this.callback === 'create') {
                location.href = window.route('transaction.create', {type: 'standard'});
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

            if (this.callback === 'returnToPrimaryAccount') {
                location.href = window.route('account-entity.show', {account_entity: this.getReturnAccount('primary', transaction)});
                return;
            }

            if (this.callback === 'returnToSecondaryAccount') {
                location.href = window.route('account-entity.show', {account_entity: this.getReturnAccount('secondary', transaction)});
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
