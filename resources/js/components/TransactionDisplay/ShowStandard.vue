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
                                <span v-if="transaction.date">{{ formattedDate(transaction.date) }}</span>
                                <span v-else class="text-muted text-italic">Not set</span>
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
                                {{ transaction.config.amount_from.toLocalCurrency(ammountFromCurrency, false) }}
                            </dd>

                            <dt class="col-xs-4" v-if="exchangeRatePresent">
                                Exchange rate
                            </dt>
                            <dd class="col-xs-8" v-if="exchangeRatePresent">
                                {{ exchangeRate }}
                            </dd>

                            <dt class="col-xs-4" v-if="exchangeRatePresent">
                                Amount to
                            </dt>
                            <dd class="col-xs-8" v-if="exchangeRatePresent">
                                {{ transaction.config.amount_to.toLocalCurrency(transaction.config.account_to.config.currency, false) }}
                            </dd>

                            <dt class="col-xs-4">
                                Total allocated
                            </dt>
                            <dd class="col-xs-8">
                                {{ allocatedAmount.toLocalCurrency(ammountFromCurrency, false) }}
                            </dd>

                            <dt class="col-xs-4">
                                Not allocated
                            </dt>
                            <dd class="col-xs-8">
                                {{ remainingAmountNotAllocated.toLocalCurrency(ammountFromCurrency, false) }}
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
                    :currency="ammountFromCurrency"
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

        computed: {
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

            // Amound from currency is dependent on transaction type
            ammountFromCurrency() {
                if (this.transaction.transaction_type.name === 'withdrawal' || this.transaction.transaction_type.name === 'transfer') {
                    return this.transaction.config.account_from.config.currency;
                }

                return this.transaction.config.account_to.config.currency;
            },

            // Calculate the summary of all existing items and their values
            allocatedAmount() {
                return this.transaction.transaction_items
                    .map(item => Number(item.amount) || 0)
                    .reduce((amount, currentValue) => amount + currentValue, 0 );
            },

            remainingAmountNotAllocated() {
                return this.transaction.config.amount_from - this.allocatedAmount;
            },

            // Indicates if transaction type is transfer, and currencies of accounts are different
            exchangeRatePresent() {
                return this.transaction.config.account_from.config.currency && this.transaction.config.account_to.config.currency && this.transaction.config.account_from.config.currency.id != this.transaction.config.account_to.config.currency.id;
            },

            exchangeRate() {
                const from = this.transaction.config.amount_from;
                const to = this.transaction.config.amount_to;

                if (from && to) {
                    return (Number(to) / Number(from)).toFixed(4);
                }

                return 0;
            },
        },
        methods: {
            formattedDate(date) {
                if (typeof date === 'undefined') {
                    return;
                }

                const newDate = new Date(date);

                return newDate.toLocaleDateString('Hu-hu');
            },
        }
    }
</script>

<style scoped>
    dl.row {
        display: flex;
        flex-wrap: wrap;
    }
</style>
