<template>
    <div id="transactionShowStandard" v-if="transaction.id">
        <div class="row">
            <div class="col-md-4">
                <div class="card mb-3">
                    <div class="card-header">
                        <div class="card-title">
                            {{ __('Properties') }}
                        </div>
                    </div>
                    <div class="card-body">
                        <dl class="row mb-0">
                            <dt class="col-6">
                                {{ __('Type') }}
                            </dt>
                            <dd class="col-6">
                                {{ __(capitalize(transaction.transaction_type.name)) }}
                            </dd>

                            <dt class="col-6">
                                {{ __('Date') }}
                            </dt>
                            <dd class="col-6">
                                <span v-if="transaction.date">{{ formattedDate(transaction.date) }}</span>
                                <span v-else class="text-muted text-italic">{{ __('Not set') }}</span>
                            </dd>

                            <dt class="col-6">
                                {{ accountFromFieldLabel }}
                            </dt>
                            <dd
                                    class="col-6"
                                    :class="(transaction.config.account_from?.name ? '' : 'text-muted')"
                                    dusk="label-account-from-name"
                            >
                                {{ transaction.config.account_from?.name || __('Not set') }}
                            </dd>

                            <dt class="col-6">
                                {{ accountToFieldLabel }}
                            </dt>
                            <dd
                                    class="col-6"
                                    :class="(transaction.config.account_to?.name ? '' : 'text-muted')"
                                    dusk="label-account-to-name"
                            >
                                {{ transaction.config.account_to?.name || __('Not set') }}
                            </dd>

                            <dt class="col-6">
                                {{ __('Comment') }}
                            </dt>
                            <dd class="col-6" :class="(transaction.comment ? '' : 'text-muted')">
                                {{ transaction.comment || __('Not set') }}
                            </dd>

                            <dt class="col-6">
                                {{ __('Scheduled') }}
                            </dt>
                            <dd class="col-6">
                                <i
                                        v-if="transaction.schedule"
                                        class="fa fa-check text-success"
                                        :title="__('Yes')"></i>
                                <i
                                        v-else
                                        class="fa fa-ban text-danger"
                                        :title="__('No')"></i>
                            </dd>

                            <dt class="col-6">
                                {{ __('Budget') }}
                            </dt>
                            <dd class="col-6" dusk="label-budget">
                                <i
                                        v-if="transaction.budget"
                                        class="fa fa-check text-success"
                                        :title="__('Yes')"></i>
                                <i
                                        v-else
                                        class="fa fa-ban text-danger"
                                        :title="__('No')"></i>
                            </dd>

                            <dt class="col-6">
                                {{ __('Reconciled') }}
                            </dt>
                            <dd class="col-6">
                                <i
                                        v-if="transaction.reconciled"
                                        class="fa fa-check text-success"
                                        :title="__('Yes')"></i>
                                <i
                                        v-else
                                        class="fa fa-ban text-danger"
                                        :title="__('No')"></i>
                            </dd>
                        </dl>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-header">
                        <div class="card-title">
                            {{ __('Amounts') }}
                        </div>
                    </div>
                    <div class="card-body">
                        <dl class="row mb-0">
                            <dt class="col-6">
                                {{ ammountFromFieldLabel }}
                            </dt>
                            <dd class="col-6">
                                {{ toFormattedCurrency(transaction.config.amount_from, locale, ammountFromCurrency) }}
                            </dd>

                            <dt class="col-6" v-if="exchangeRatePresent">
                                {{ __('Exchange rate') }}
                            </dt>
                            <dd class="col-6" v-if="exchangeRatePresent">
                                {{ exchangeRate }}
                            </dd>

                            <dt class="col-6" v-if="exchangeRatePresent">
                                {{ __('Amount to') }}
                            </dt>
                            <dd class="col-6" v-if="exchangeRatePresent">
                                {{
                                    toFormattedCurrency(transaction.config.amount_to, locale, transaction.config.account_to?.config.currency)
                                }}
                            </dd>

                            <dt class="col-6">
                                {{ __('Total allocated') }}
                            </dt>
                            <dd class="col-6">
                                {{ toFormattedCurrency(allocatedAmount, locale, ammountFromCurrency) }}
                            </dd>

                            <dt class="col-6">
                                {{ __('Not allocated') }}
                            </dt>
                            <dd class="col-6">
                                {{ toFormattedCurrency(remainingAmountNotAllocated, locale, ammountFromCurrency) }}
                            </dd>
                        </dl>
                    </div>
                </div>

                <transaction-schedule
                        :isVisible="transaction.schedule || transaction.budget"
                        :isSchedule="transaction.schedule"
                        :isBudget="transaction.budget"
                        :schedule="transaction.transaction_schedule || {}"
                ></transaction-schedule>

            </div>

            <div class="col-md-8">
                <transaction-item-container
                        :transactionItems="transaction.transaction_items"
                        :currency="ammountFromCurrency"
                        :enabled="!transactionTypeIsTransfer"
                ></transaction-item-container>
            </div>
        </div>
    </div>
</template>

<script>
import TransactionItemContainer from './ItemContainer.vue'
import TransactionSchedule from './Schedule.vue'
import * as helpers from '../../helpers';

export default {
    components: {
        'transaction-item-container': TransactionItemContainer,
        'transaction-schedule': TransactionSchedule,
        helpers
    },

    props: {
        transaction: {
            type: Object,
            default: {}
        },
        locale: {
            type: String,
            default: window.YAFFA.locale,
        }
    },

    computed: {
        // Account TO and FROM labels based on transaction type
        accountFromFieldLabel() {
            if (this.transaction.transaction_type.name === 'withdrawal' || this.transaction.transaction_type.name === 'transfer') {
                return __('Account from');
            }

            return __('Payee');
        },

        accountToFieldLabel() {
            if (this.transaction.transaction_type.name === 'deposit' || this.transaction.transaction_type.name === 'transfer') {
                return __('Account to');
            }

            return __('Payee');
        },

        // Amount from label is different for transfer
        ammountFromFieldLabel() {
            return (this.exchangeRatePresent ? __('Amount from') : __('Amount'))
        },

        // Amound from currency is dependent on transaction type
        ammountFromCurrency() {
            if (this.transaction.transaction_type.name === 'withdrawal'
                || this.transaction.transaction_type.name === 'transfer') {
                return this.transaction.config.account_from?.config.currency;
            }

            return this.transaction.config.account_to?.config.currency;
        },

        // Calculate the summary of all existing items and their values
        allocatedAmount() {
            return this.transaction.transaction_items
                .map(item => Number(item.amount) || 0)
                .reduce((amount, currentValue) => amount + currentValue, 0);
        },

        remainingAmountNotAllocated() {
            return this.transaction.config.amount_from - this.allocatedAmount;
        },

        // Indicates if transaction type is transfer, and currencies of accounts are different
        exchangeRatePresent() {
            return this.transaction.config.account_from?.config.currency
                && this.transaction.config.account_to?.config.currency
                && this.transaction.config.account_from.config.currency.id !== this.transaction.config.account_to.config.currency.id;
        },

        exchangeRate() {
            const from = this.transaction.config.amount_from;
            const to = this.transaction.config.amount_to;

            if (from && to) {
                return (Number(to) / Number(from)).toFixed(4);
            }

            return 0;
        },
        transactionTypeIsTransfer() {
            return this.transaction.transaction_type.name === 'transfer';
        }
    },
    methods: {
        formattedDate(date) {
            if (typeof date === 'undefined') {
                return;
            }

            const newDate = new Date(date);

            return newDate.toLocaleDateString(this.locale);
        },
        toFormattedCurrency(input, locale, currencySettings) {
            return helpers.toFormattedCurrency(input, locale, currencySettings);
        },
        capitalize(string) {
            return string[0].toUpperCase() + string.slice(1);
        },
        /**
         * Import the translation helper function.
         */
        __: function (string, replace) {
            return helpers.__(string, replace);
        },
    }
}
</script>
