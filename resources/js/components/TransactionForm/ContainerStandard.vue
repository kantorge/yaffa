<template>
    <div>
        <transaction-form-standard
            :action = "action"
            :transaction = "transactionData"
            @cancel="onCancel"
            @success="onSuccess"
            @changeTransactionType="onTransactionTypeChange"
        ></transaction-form-standard>

        <div class="card mb-3">
            <div class="card-body">
                <div class="row">
                    <div class="d-none d-md-block col-md-10">
                        <label class="control-label block-label">
                            {{ __('After saving') }}
                        </label>
                        <div class="btn-group">
                            <button
                                v-for="item in activeCallbackOptions"
                                :key="item.id"
                                class="btn btn-outline-dark"
                                :class="{ 'active': callback === item.value }"
                                type="button"
                                :value="item.value"
                                @click="callback = $event.currentTarget.getAttribute('value')"
                            >
                                {{ item.label }}
                            </button>
                        </div>
                    </div>
                    <div class="col-12 d-block d-md-none">
                        <label class="control-label block-label">
                            {{ __('After saving') }}
                        </label>
                        <select
                            class="form-control"
                            v-model="callback"
                        >
                            <option
                                v-for="item in activeCallbackOptions"
                                :key="item.id"
                                :value="item.value"
                            >
                                {{ item.label }}
                            </option>
                        </select>
                    </div>
                </div>
            </div>
        </div>
    </div>
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

        computed: {
            activeCallbackOptions() {
                return this.callbackOptions.filter(option => option.enabled);
            },
        },

        created() {},

        data() {
            var data = {
                // TODO: adjust initial callback based on action
                callback: 'create',

                // Various callback options
                callbackOptions: [
                    {
                        value: 'create',
                        label: __('Add an other transaction'),
                        enabled: true,
                    },
                    {
                        value: 'clone',
                        label: __('Clone this transaction'),
                        enabled: true,
                    },
                    {
                        value: 'returnToPrimaryAccount',
                        label: __('Return to selected account'),
                        enabled: true,
                    },
                    {
                        value: 'returnToSecondaryAccount',
                        label: __('Return to target account'),
                        enabled: false,
                    },
                    {
                        value: 'returnToDashboard',
                        label: __('Return to dashboard'),
                        enabled: true,
                    },
                    {
                        value: 'back',
                        label: __('Return to previous page'),
                        enabled: true,
                    },
                ],
            };

            // For new transactions set some default values
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
            onSuccess(transaction) {
                this.loadCallbackUrl(transaction);
            },

            onTransactionTypeChange(newState) {
                // Update callback options
                var foundCallbackIndex = this.callbackOptions.findIndex(x => x.value === 'returnToSecondaryAccount');
                this.callbackOptions[foundCallbackIndex]['enabled'] = (newState === 'transfer')

                // Ensure, that selected item is enabled. Otherwise, set to first enabled option
                var selectedCallbackIndex = this.callbackOptions.findIndex(x => x.value === this.callback);
                if (! this.callbackOptions[selectedCallbackIndex].enabled) {
                    this.callback = this.callbackOptions.find(option => option.enabled)['value'];
                }
            },
        },
    }
</script>

<style scoped>
    @media (min-width: 576px) {
        .block-label {
            display: block;
        }
    }
</style>
