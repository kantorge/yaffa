<template>
    <div v-if="transaction.id">
        <div class="row">
            <!-- left column -->
            <div class="col-md-5">
                <div class="box box-primary">
                    <div class="box-header with-border">
                        <h3 class="box-title">
                            Properties
                        </h3>
                    </div>
                    <!-- /.box-header -->

                    <div class="box-body">
                        <dl class="row">
                            <dt class="col-xs-4">Type</dt>
                            <dd class="col-xs-8">
                                <span v-if="transaction.transaction_type.name == 'withdrawal'">
                                    Withdrawal
                                </span>
                                <span v-if="transaction.transaction_type.name == 'deposit'">
                                    Deposit
                                </span>
                                <span v-if="transaction.transaction_type.name == 'transfer'">
                                    Transfer
                                </span>
                            </dd>

                            <dt class="col-xs-4">
                                Date
                            </dt>
                            <dd class="col-xs-8">
                                {{ formattedDate }}
                            </dd>

                            <dt class="col-xs-4">
                                {{ accountFromFieldLabel }}
                            </dt>
                            <dd class="col-xs-8">
                                {{ transaction.config.account_from.name }}
                            </dd>

                            <dt class="col-xs-4">
                                {{ accountToFieldLabel }}
                            </dt>
                            <dd class="col-xs-8">
                                {{ transaction.config.account_to.name }}
                            </dd>

                            <dt class="col-xs-4">
                                Comment
                            </dt>
                            <dd class="col-xs-8" :class="(transaction.comment ? '' : 'text-muted')">
                                {{ transaction.comment || "Not set" }}
                            </dd>

                            <dt class="col-xs-4">
                                Scheduled
                            </dt>
                            <dd class="col-xs-8">
                                <span v-if="transaction.schedule"><i class="fa fa-check text-success" title="Yes"></i></span>
                                <span v-else><i class="fa fa-ban text-danger" title="No"></i></span>
                            </dd>

                            <dt class="col-xs-4">
                                Budget
                            </dt>
                            <dd class="col-xs-8">
                                <span v-if="transaction.budget"><i class="fa fa-check text-success" title="Yes"></i></span>
                                <span v-else><i class="fa fa-ban text-danger" title="No"></i></span>
                            </dd>

                            <dt class="col-xs-4">
                                Reconciled
                            </dt>
                            <dd class="col-xs-8">
                                <span v-if="transaction.reconciled"><i class="fa fa-check text-success" title="Yes"></i></span>
                                <span v-else><i class="fa fa-ban text-danger" title="No"></i></span>
                            </dd>
                        </dl>
                    </div>
                    <!-- /.box-body -->
                </div>
                <!-- /.box -->

                <div class="box box-primary">
                    <div class="box-header with-border">
                        <h3 class="box-title">Amounts</h3>
                    </div>
                    <!-- /.box-header -->
                    <div class="box-body">
                        <dl class="row">
                            <dt class="col-xs-4">
                                {{ ammountFromFieldLabel }}
                            </dt>
                            <dd class="col-xs-8">
                                {{ form.config.amount_from }}
                                <span v-if="ammountFromCurrencyLabel">({{ ammountFromCurrencyLabel }})</span>
                            </dd>

                            <dt class="col-xs-4" v-show="exchangeRatePresent">
                                Exchange rate
                            </dt>
                            <dd class="col-xs-8" v-show="exchangeRatePresent">
                                {{ exchangeRate }}
                            </dd>

                            <dt class="col-xs-4" v-show="exchangeRatePresent">
                                Amount to
                            </dt>
                            <dd class="col-xs-8" v-show="exchangeRatePresent">
                                {{ form.config.amount_to }}
                                <span v-if="to.account_currency">({{to.account_currency}})</span>
                            </dd>

                            <dt class="col-xs-4">Total allocated:</dt>
                            <dd class="col-xs-8">
                                {{ allocatedAmount }}
                                <span v-if="from.account_currency">{{from.account_currency}}</span>
                            </dd>

                            <dt class="col-xs-4">
                                Not allocated:
                            </dt>
                            <dd class="col-xs-8">
                                {{ remainingAmountNotAllocated }}
                                <span v-if="from.account_currency">{{from.account_currency}}</span>
                            </dd>
                        </dl>
                    </div>
                </div>
                <!-- /.box -->

                <transaction-schedule
                    :isVisible="transaction.schedule || transaction.budget"
                    :isSchedule="transaction.schedule"
                    :isBudget="transaction.budget"
                    :schedule="transaction.transaction_schedule || {}"
                ></transaction-schedule>

            </div>
            <!--/.col (left) -->

            <!-- right column -->
            <div class="col-md-7">
                <transaction-item-container
                    :transactionItems="transaction.transaction_items"
                    :currency="from.account_currency"
                ></transaction-item-container>
            </div>
            <!--/.col (right) -->

        </div>
        <!-- /.row -->
    </div>
</template>

<script>
    import TransactionItemContainer from './ItemContainer.vue'
    import TransactionSchedule from './Schedule.vue'

    export default {
        components: {
            'transaction-item-container': TransactionItemContainer,
            'transaction-schedule': TransactionSchedule,
        },

        props: {
            transaction: {
                type: Object,
                default: {}
            }
        },

        data() {
            let data = {};

            // Storing all data and references about source account or payee
            // Set as withdrawal by default
            data.from = {
                type: 'account',
                account_currency : null,
            };

            // Storing all data and references about target account or payee
            // Set as withdrawal by default
            data.to = {
                type: 'payee',
                account_currency : null,
            };

            // Main form data
            data.form = {
                transaction_type: 'withdrawal',
                config_type: 'transaction_detail_standard',
                date: '',
                comment: null,
                schedule: false,
                budget: false,
                reconciled: false,
                config: {},
                items: [],
                remaining_payee_default_amount: 0,
                remaining_payee_default_category_id: null,
            };

            return data;
        },

        computed: {
            formattedDate() {
                if (typeof this.transaction.id === 'undefined') {
                    return;
                }

                const date = new Date(this.transaction.date); // Can prop be updated like this?

                return date.toLocaleDateString('Hu-hu');
            },

            // Account TO and FROM labels based on transaction type
            accountFromFieldLabel() {
                return (this.transaction.transaction_type.name == 'withdrawal' || this.transaction.transaction_type.name == 'transfer' ? 'Account from' : 'Payee')
            },

            accountToFieldLabel() {
                return (this.transaction.transaction_type.name == 'deposit' || this.transaction.transaction_type.name == 'transfer' ? 'Account to' : 'Payee')
            },

            // Amount from label is different for transfer
            ammountFromFieldLabel() {
                return (this.exchangeRatePresent ? 'Amount from' : 'Amount')
            },

            // Amound from currency is dependent on many other data
            ammountFromCurrencyLabel() {
                if (this.transaction.transaction_type.name === 'withdrawal' || this.transaction.transaction_type.name === 'transfer') {
                    return this.from.account_currency;
                }

                if (this.transaction.transaction_type.name === 'deposit') {
                    return this.to.account_currency;
                }

                return '';
            },

            // Calculate the summary of all existing items and their values
            allocatedAmount() {
                return this.transaction.transaction_items
                    .map(item => Number(item.amount) || 0)
                    .reduce((amount, currentValue) => amount + currentValue, 0 );
            },

            remainingAmountNotAllocated() {
                return this.form.config.amount_from - this.allocatedAmount;
            },

            // Return ID of account, if present any of fields (using account from in transfer)
            accountId() {
                if (this.form.transaction_type === 'deposit') {
                    return this.form.config.account_to_id;
                }

                return this.form.config.account_from_id;
            },

            // Indicates if transaction type is transfer, and currencies of accounts are different
            exchangeRatePresent() {
                return this.from.account_currency && this.to.account_currency && this.from.account_currency != this.to.account_currency;
            },

            exchangeRate() {
                const from = this.form.config.amount_from;
                const to = this.form.config.amount_to;

                if (from && to) {
                    return (Number(to) / Number(from)).toFixed(4);
                }

                return 0;
            },
        },

        created() {},

        mounted() {},

        methods: {},

        watch: {
            // Update TO amount with FROM value, if needed
            "form.config.amount_from": {
                immediate: true,
                handler(value) {
                    if (!(this.from.account_currency && this.to.account_currency && this.from.account_currency != this.to.account_currency)) {
                        this.form.config.amount_to = value;
                    }
                },
            },
        }
    }
</script>

<style scoped>
    dl.row {
        display: flex;
        flex-wrap: wrap;
    }

    @media (min-width: 576px) {
        .block-label {
            display: block;
        }

        .d-sm-none {
            display: none;
        }
    }
    @media (max-width: 575.98px) {
        .block-label {
            margin-right: 10px;
        }

        .dl-horizontal dt {
            float: left;
            width: 100px;
            overflow: hidden;
            clear: left;
            text-align: right;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .dl-horizontal dd {
            margin-left: 110px;
        }
    }
</style>
