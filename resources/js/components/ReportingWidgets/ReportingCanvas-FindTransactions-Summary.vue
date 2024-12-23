<template>
    <div>
        <div class="row">
            <div class="col-12">
                <h2>{{ __('Summary of the filtered transactions') }}</h2>
                <p>{{ __('Your search returned :total transactions in total', {total: transactions.length}) }}</p>
                <ul>
                    <li>
                        {{ __('Withdrawals') }}: {{ countWithdrawals }}
                        ({{ sumWithdrawalsFormatted }})
                    </li>
                    <li>
                        {{ __('Deposits') }}: {{ countDeposits }}
                        ({{ sumDepositsFormatted }})
                    </li>
                    <li>{{ __('Transfers') }}: {{ countTransfers }}</li>
                </ul>
                <p>
                    {{ __('The earliest transaction is from :date', {date: minDateFormatted}) }}
                    {{ __('The latest transaction is from :date', {date: maxDateFormatted}) }}
                </p>
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
    },
    computed: {
        countWithdrawals() {
            return this.transactions.filter(transaction => transaction.transaction_type_id === 1).length;
        },
        countDeposits() {
            return this.transactions.filter(transaction => transaction.transaction_type_id === 2).length;
        },
        countTransfers() {
            return this.transactions.filter(transaction => transaction.transaction_type_id === 3).length;
        },
        sumWithdrawals() {
            return this.transactions.filter(transaction => transaction.transaction_type_id === 1)
                .reduce((acc, transaction) => {
                    return acc + transaction.transaction_items.reduce((acc, item) => {
                        return acc + item.amount_in_base;
                    }, 0);
                }, 0);
        },
        sumWithdrawalsFormatted() {
            return toFormattedCurrency(this.sumWithdrawals, window.YAFFA.language, window.YAFFA.baseCurrency);
        },
        sumDeposits() {
            return this.transactions.filter(transaction => transaction.transaction_type_id === 2)
                .reduce((acc, transaction) => {
                    return acc + transaction.transaction_items.reduce((acc, item) => {
                        return acc + item.amount_in_base;
                    }, 0);
                }, 0);
        },
        sumDepositsFormatted() {
            return toFormattedCurrency(this.sumDeposits, window.YAFFA.language, window.YAFFA.baseCurrency);
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
            return this.minDate.toLocaleDateString(window.YAFFA.language);
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
            return this.maxDate.toLocaleDateString(window.YAFFA.language);
        },
    },
    methods: {
        /**
         * Define the translation helper function locally.
         */
        __: function (string, replace) {
            return translator(string, replace);
        },
    },
    mounted() {}
}
</script>
