<template>
    <div>
        <h2>{{ __('Summary of the filtered transactions') }}</h2>
        <ul class="list-group list-group-flush" v-if="busy">
            <li
                    aria-hidden="true"
                    class="list-group-item placeholder-glow"
                    v-for="i in 5"
                    v-bind:key="i"
            >
                <span class="placeholder col-12"></span>
            </li>
        </ul>
        <div class="row" v-else>
            <div class="col-12">
                <p v-html="__('Your search returned <strong>:total</strong> transactions in total', {total: transactions.length})"></p>
                <p>
                    {{ __('The earliest transaction is from :date', {date: minDateFormatted}) }}
                    {{ __('The latest transaction is from :date', {date: maxDateFormatted}) }}
                </p>

                <table class="table table-borderless table-striped">
                    <thead>
                        <tr>
                            <th colspan="2">{{ __('Type') }}</th>
                            <th>{{ __('Transaction count') }}</th>
                            <th>{{ __('Transaction value') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                                v-for="summary in withdrawalSummary"
                                :key="summary.currency_id"
                                :class="{'table-info': summary.currency_id === 0}"
                        >
                            <td v-if="summary.currency_id === 0" colspan="2">{{ summary.currency_name }}</td>
                            <td v-if="summary.currency_id !== 0"></td>
                            <td v-if="summary.currency_id !== 0">{{ summary.currency_name }}</td>
                            <td>{{ summary.count }}</td>
                            <td>
                                {{ toFormattedCurrency(summary.sum, this.locale, summary.currency) }}
                                <span v-if="summary.currency_id !== 0 && summary.currency_id !== this.baseCurrency.id">
                                    ({{ toFormattedCurrency(summary.sum_base, this.locale, this.baseCurrency) }})
                                </span>
                            </td>
                        </tr>

                        <tr
                                v-for="summary in depositSummary"
                                :key="summary.currency_id"
                                :class="{'table-info': summary.currency_id === 0}"
                        >
                            <td v-if="summary.currency_id === 0" colspan="2">{{ summary.currency_name }}</td>
                            <td v-if="summary.currency_id !== 0"></td>
                            <td v-if="summary.currency_id !== 0">{{ summary.currency_name }}</td>
                            <td>{{ summary.count }}</td>
                            <td>
                                {{ toFormattedCurrency(summary.sum, this.locale, summary.currency) }}
                                <span v-if="summary.currency_id !== 0 && summary.currency_id !== this.baseCurrency.id">
                                    ({{ toFormattedCurrency(summary.sum_base, this.locale, this.baseCurrency) }})
                                </span>
                            </td>
                        </tr>

                        <tr class="table-info">
                            <td colspan="2">{{ __('Transfers') }}</td>
                            <td>{{ countTransfers }}</td>
                            <td></td>
                        </tr>
                        <tr class="table-info">
                            <td colspan="2">{{ __("Investment transactions") }}</td>
                            <td>{{ countInvestments }}</td>
                            <td></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>

<script>

import { __ as translator } from "../../helpers";
import { toFormattedCurrency } from "../../helpers";

export default {
    name: 'ReportingCanvasFindTransactionsSummary',
    props: {
        transactions: {
            type: Array,
            required: false,
            default: () => []
        },
        busy: {
            type: Boolean,
            required: true
        },
    },
    data() {
        return {
            baseCurrency: window.YAFFA.baseCurrency,
            locale: window.YAFFA.locale
        }
    },
    computed: {
        /**
         * This computed property returns a summary of the withdrawal transactions.
         * The result is an array of objects, each object representing a currency (id, name), the count of transactions, and the sum of the transactions.
         * The sum is also calculated in the base currency.
         * Additionally, the first object in the array is the total sum of all transactions.
         *
         * @returns {Array}
         */
        withdrawalSummary() {
            const summary = [];
            const currencies = [];
            let total = 0;
            this.transactions
                .filter(transaction => transaction.transaction_type_id === 1)
                .forEach(transaction => {
                    if (!currencies.includes(transaction.currency_id)) {
                        currencies.push(transaction.currency_id);
                        summary.push({
                            currency_id: transaction.currency_id,
                            currency_name: transaction.transaction_currency.name,
                            currency: transaction.transaction_currency,
                            count: 1,
                            sum: transaction.cashflow_value,
                            sum_base: transaction.cashflow_value * transaction.currencyRateToBase,
                        });
                    } else {
                        const index = summary.findIndex(s => s.currency_id === transaction.currency_id);
                        summary[index].count++;
                        summary[index].sum += transaction.cashflow_value;
                        summary[index].sum_base += transaction.cashflow_value * transaction.currencyRateToBase;
                    }
                    total += transaction.cashflow_value * transaction.currencyRateToBase;
                });

            summary.unshift({
                currency_id: 0,
                currency_name: 'All withdrawals in ' + this.baseCurrency.name,
                currency: this.baseCurrency,
                count: this.countWithdrawals,
                sum: total,
                sum_base: total,
            });
            return summary;
        },
        countWithdrawals() {
            return this.transactions.filter(transaction => transaction.transaction_type_id === 1).length;
        },
        depositSummary() {
            const summary = [];
            const currencies = [];
            let total = 0;
            this.transactions
                .filter(transaction => transaction.transaction_type_id === 2)
                .forEach(transaction => {
                    if (!currencies.includes(transaction.currency_id)) {
                        currencies.push(transaction.currency_id);
                        summary.push({
                            currency_id: transaction.currency_id,
                            currency_name: transaction.transaction_currency.name,
                            currency: transaction.transaction_currency,
                            count: 1,
                            sum: transaction.cashflow_value,
                            sum_base: transaction.cashflow_value * transaction.currencyRateToBase,
                        });
                    } else {
                        const index = summary.findIndex(s => s.currency_id === transaction.currency_id);
                        summary[index].count++;
                        summary[index].sum += transaction.cashflow_value;
                        summary[index].sum_base += transaction.cashflow_value * transaction.currencyRateToBase;
                    }
                    total += transaction.cashflow_value * transaction.currencyRateToBase;
                });

            summary.unshift({
                currency_id: 0,
                currency_name: 'All deposits in ' + this.baseCurrency.name,
                currency: this.baseCurrency,
                count: this.countDeposits,
                sum: total,
                sum_base: total,
            });
            return summary;
        },
        countDeposits() {
            return this.transactions.filter(transaction => transaction.transaction_type_id === 2).length;
        },
        countTransfers() {
            return this.transactions.filter(transaction => transaction.transaction_type_id === 3).length;
        },
        countInvestments() {
            return this.transactions.filter(transaction => transaction.transaction_type.type === 'investment').length;
        },
        minDate() {
            return this.transactions.length ? this.transactions.reduce((acc, transaction) => {
                return acc < transaction.date ? acc : transaction.date;
            }, this.transactions[0].date) : null;
        },
        minDateFormatted() {
            if (!this.minDate || !this.minDate.toLocaleDateString) {
                return null;
            }
            return this.minDate.toLocaleDateString(window.YAFFA.locale);
        },
        maxDate() {
            return this.transactions.length ? this.transactions.reduce((acc, transaction) => {
                return acc > transaction.date ? acc : transaction.date;
            }, this.transactions[0].date) : null;
        },
        maxDateFormatted() {
            if (!this.maxDate || !this.maxDate.toLocaleDateString) {
                return null;
            }
            return this.maxDate.toLocaleDateString(window.YAFFA.locale);
        },
    },
    methods: {
        /**
         * Define the translation helper function locally.
         */
        __: function (string, replace) {
            return translator(string, replace);
        },
        toFormattedCurrency,
    },
    mounted() {}
}
</script>
