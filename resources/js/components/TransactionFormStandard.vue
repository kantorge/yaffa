<template>
    <div>
        <AlertErrors :form="form" message="There were some problems with your input." />

        <!-- form start -->
        <form
            accept-charset="UTF-8"
            @submit.prevent="onSubmit"
            autocomplete="off"
        >
            <div class="row">
                <!-- left column -->
                <div class="col-md-4">
                    <!-- general form elements -->
                    <div class="box box-primary">
                        <div class="box-header with-border">
                            <h3 class="box-title">
                                Transaction properties
                            </h3>
                        </div>
                        <!-- /.box-header -->

                        <div class="box-body">
                            <div class="form-horizontal">
                                <div class="form-group row">
                                    <label class="control-label col-sm-3">
                                        Type
                                    </label>
                                    <div class="col-sm-9">
                                        <div class="btn-group">
                                            <button
                                                class="btn btn-primary"
                                                :class="form.transaction_type == 'withdrawal' ? 'active' : ''"
                                                type="button"
                                                value="withdrawal"
                                                @click="changeTransactionType"
                                            >
                                                Withdrawal
                                            </button>
                                            <button
                                                class="btn btn-primary"
                                                :class="form.transaction_type == 'deposit' ? 'active' : ''"
                                                type="button"
                                                value="deposit"
                                                @click="changeTransactionType"
                                            >
                                                Deposit
                                            </button>
                                            <button
                                                class="btn btn-primary"
                                                :class="form.transaction_type == 'transfer' ? 'active' : ''"
                                                type="button"
                                                value="transfer"
                                                @click="changeTransactionType"
                                            >
                                                Transfer
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <div
                                    class="form-group row"
                                    :class="form.errors.has('date') ? 'has-error' : ''"
                                >
                                    <label for="date" class="control-label col-sm-3">
                                        Date
                                    </label>
                                    <div class="col-sm-6">
                                        <date-picker
                                            id="date"
                                            v-model="form.date"
                                            value-type="format"
                                            format="YYYY-MM-DD"
                                            type="date"
                                            :disabled="form.schedule || form.budget"
                                        ></date-picker>
                                    </div>
                                    <div class="col-sm-3">
                                    </div>
                                </div>
                                <div
                                    class="form-group row"
                                    :class="form.errors.has('config.account_from_id') ? 'has-error' : ''"
                                >
                                    <label class="control-label col-sm-3" id="account_from_label">
                                        {{ accountFromFieldLabel }}
                                    </label>
                                    <div class="col-sm-9">
                                        <select
                                            class="form-control"
                                            id="account_from"
                                            v-model="form.config.account_from_id">
                                        </select>
                                    </div>
                                </div>

                                <div
                                    class="form-group row"
                                    :class="form.errors.has('config.account_to_id') ? 'has-error' : ''"
                                >
                                    <label class="control-label col-sm-3" id="account_to_label">
                                        {{ accountToFieldLabel }}
                                    </label>
                                    <div class="col-sm-9">
                                        <select
                                            class="form-control"
                                            id="account_to"
                                            v-model="form.config.account_to_id">
                                        </select>
                                    </div>
                                </div>

                                <div
                                    class="form-group row"
                                    :class="form.errors.has('comment') ? 'has-error' : ''"
                                >
                                    <label for="comment" class="control-label col-sm-3">
                                        Comment
                                    </label>
                                    <div class="col-sm-9">
                                        <input
                                            class="form-control"
                                            id="comment"
                                            maxlength="255"
                                            type="text"
                                            v-model="form.comment"
                                        />
                                    </div>
                                </div>

                                <div class="form-group row" id="entry_type_container">
                                    <div class="col-sm-4">
                                        <input
                                            id="entry_type_schedule"
                                            class="checkbox-inline"
                                            :disabled="form.reconciled"
                                            type="checkbox"
                                            value="1"
                                            v-model="form.schedule"
                                        >
                                        <label for="entry_type_schedule" class="control-label">
                                            Scheduled
                                        </label>
                                    </div>
                                    <div class="col-sm-4">
                                        <input
                                            id="entry_type_budget"
                                            class="checkbox-inline"
                                            :disabled="form.reconciled || form.transaction_type == 'transfer'"
                                            type="checkbox"
                                            value="1"
                                            v-model="form.budget"
                                        >
                                        <label for="entry_type_budget" class="control-label">
                                            Budget
                                        </label>
                                    </div>
                                    <div class="col-sm-4">
                                        <input
                                            id="transaction_reconciled"
                                            class="checkbox-inline"
                                            :disabled="form.schedule || form.budget"
                                            type="checkbox"
                                            value="1"
                                            v-model="form.reconciled"
                                        >
                                        <label for="transaction_reconciled" class="control-label">
                                            Reconciled
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- /.box-body -->

                    </div>
                    <!-- /.box -->

                </div>
                <!--/.col (left) -->

                <!-- right column -->
                <div class="col-md-8">
                    <transaction-item-container
                        @addTransactionItem="addTransactionItem"
                        :transactionItems="form.items"
                        :currency="from.account_currency"
                        :payee="payeeId"
                        :remainingAmount="remainingAmountNotAllocated || remainingAmountToPayeeDefault || 0"
                    ></transaction-item-container>

                    <div class="box box-primary">
                        <div class="box-header with-border">
                            <h3 class="box-title">Amounts</h3>
                        </div>
                        <!-- /.box-header -->
                        <div class="box-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="row">
                                        <div
                                            class="form-group col-sm-4"
                                            :class="form.errors.has('config.amount_from') ? 'has-error' : ''"
                                        >
                                            <label for="transaction_amount_from" class="control-label">
                                                {{ ammountFromFieldLabel }}
                                                <span v-if="from.account_currency">({{from.account_currency}})</span>
                                            </label>
                                            <MathInput
                                                class="form-control"
                                                id="transaction_amount_from"
                                                v-model="form.config.amount_from"
                                            ></MathInput>
                                        </div>
                                        <div
                                            v-show="from.account_currency && to.account_currency && from.account_currency != to.account_currency"
                                            class="col-sm-4">
                                            <span>Exchange rate</span>
                                            {{ exchangeRate }}
                                        </div>
                                        <div
                                            v-show="from.account_currency && to.account_currency && from.account_currency != to.account_currency"
                                            class="form-group col-sm-4 pull-right"
                                            :class="form.errors.has('config.amount_to') ? 'has-error' : ''"
                                        >
                                            <label for="transaction_amount_slave" class="control-label">
                                                Amount to
                                                <span v-if="to.account_currency">({{to.account_currency}})</span>
                                            </label>
                                            <MathInput
                                                class="form-control"
                                                id="transaction_amount_to"
                                                v-model="form.config.amount_to"
                                            ></MathInput>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <dl class="dl-horizontal">
                                        <dt>Total allocated:</dt>
                                        <dd>
                                            {{ allocatedAmount }}
                                            <span v-if="from.account_currency">{{from.account_currency}}</span>
                                        </dd>
                                        <dt v-show="payeeCategory.id">
                                            Remaining amount to
                                            <span class="notbold"><br>{{ payeeCategory.text }}</span>:
                                        </dt>
                                        <dd v-show="payeeCategory.id">
                                            {{ remainingAmountToPayeeDefault }}
                                            <span v-if="from.account_currency">{{from.account_currency}}</span>
                                        </dd>
                                        <dt v-show="!payeeCategory.id">
                                            Not allocated:
                                        </dt>
                                        <dd v-show="!payeeCategory.id">
                                            {{ remainingAmountNotAllocated }}
                                            <span v-if="from.account_currency">{{from.account_currency}}</span>
                                        </dd>
                                    </dl>
                                </div>
                            </div>
                        </div>

                    </div>
                    <!-- /.box -->

                    <transaction-schedule
                        :isVisible="form.schedule || form.budget"
                        :schedule="form.schedule_config"
                        :form="form"
                    ></transaction-schedule>

                </div>
                <!--/.col (right) -->

            </div>
            <!-- /.row -->

    <footer class="main-footer navbar-fixed-bottom hidden">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-2">
                    <label class="control-label">After saving</label>
                </div>
                <div class="col-sm-8">
                    <div class="btn-group">
                        <button
                            class="btn btn-default"
                            :class="callback == 'new' ? 'active' : ''"
                            type="button"
                            value="new"
                            @click="callback = $event.currentTarget.getAttribute('value')"
                        >
                            Add an other transaction
                        </button>
                        <button
                            class="btn btn-default"
                            :class="callback == 'clone' ? 'active' : ''"
                            type="button"
                            value="clone"
                            @click="callback = $event.currentTarget.getAttribute('value')"
                        >
                            Clone this transaction
                        </button>
                        <button
                            class="btn btn-default"
                            :class="callback == 'returnToAccount' ? 'active' : ''"
                            type="button"
                            value="returnToAccount"
                            @click="callback = $event.currentTarget.getAttribute('value')"
                        >
                            Return to selected account
                        </button>
                        <button
                            class="btn btn-default"
                            :class="callback == 'returnToDashboard' ? 'active' : ''"
                            type="button"
                            value="returnToDashboard"
                            @click="callback = $event.currentTarget.getAttribute('value')"
                        >
                            Return to dashboard
                        </button>
                    </div>
                </div>
                <div class="box-tools col-sm-2">
                    <div class="pull-right">
                        <button class="btn btn-sm btn-default" type="button" @click="onCancel">
                            Cancel
                        </button>
                        <Button class="btn btn-primary" :disabled="form.busy" :form="form">Save</Button>
                    </div>
                </div>
            </div>
        </div>
    </footer>

        </form>
    </div>
</template>

<script>
    require('select2');

    import MathInput from './MathInput.vue'

    import Form from 'vform'
    import {Button, AlertErrors} from 'vform/src/components/bootstrap5'

    import DatePicker from 'vue2-datepicker';
    import 'vue2-datepicker/index.css';

    import TransactionItemContainer from './TransactionItemContainer.vue'
    import TransactionSchedule from './TransactionSchedule.vue'

    export default {
        components: {
            'transaction-item-container': TransactionItemContainer,
            'transaction-schedule': TransactionSchedule,
            DatePicker,
            Button, AlertErrors,
            MathInput,
        },

        props: {
            action: String,
            transaction: Object,
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

            // Additional data about payee, if present
            data.payeeCategory = {
                id: null,
                text: null,
            };

            // Main form data
            data.form = new Form({
                transaction_type: 'withdrawal',
                config_type: 'transaction_detail_standard',
                date: '',
                comment: null,
                schedule: false,
                budget: false,
                reconciled: false,
                config: {},
                items: [],
                schedule_config: {
                    frequency: 'DAILY',
                    interval: 1,
                },
                remaining_payee_default_amount: 0,
                remaining_payee_default_category_id: null,
            });

            // TODO: adjust initial callback based on action
            data.callback = 'new';

            return data;
        },

        computed: {
            formUrl() {
                if (this.action === 'edit') {
                    return route('transactions.updateStandard', {transaction: this.form.id});
                }

                return route('transactions.storeStandard');
            },

            // Account TO and FROM labels based on transaction type
            accountFromFieldLabel() {
                return (this.form.transaction_type == 'withdrawal' || this.form.transaction_type == 'transfer' ? 'Account from' : 'Payee')
            },

            accountToFieldLabel() {
                return (this.form.transaction_type == 'deposit' || this.form.transaction_type == 'transfer' ? 'Account to' : 'Payee')
            },

            // Amount from label is different for transfer
            ammountFromFieldLabel() {
                return (this.form.transaction_type == 'transfer' ? 'Amount from' : 'Amount')
            },

            // Calculate the summary of all existing items and their values
            allocatedAmount() {
                return this.form.items
                    .map(item => Number(item.amount) || 0)
                    .reduce((amount, currentValue) => amount + currentValue, 0 );
            },

            remainingAmountToPayeeDefault() {
                if (this.payeeCategory.id && !isNaN(this.form.config.amount_from)) {
                    return this.form.config.amount_from - this.allocatedAmount;
                }
                return 0;
            },

            remainingAmountNotAllocated() {
                if (!this.payeeCategory.id && !isNaN(this.form.config.amount_from)) {
                    return this.form.config.amount_from - this.allocatedAmount;
                }

                return 0;
            },

            payeeDefaultCategory() {
                return this.payeeCategory.id;
            },

            // Return ID of payee, if present in any of fields
            payeeId() {
                if (this.form.transaction_type === 'withdrawal') {
                    return this.form.config.account_to_id;
                }

                if (this.form.transaction_type === 'deposit') {
                    return this.form.config.account_from_id;
                }

                return undefined;
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

        created() {
            // Copy values of existing transaction into component form data
            if (Object.keys(this.transaction).length > 0) {
                // Populate form data with already known values
                this.form.id = this.transaction.id
                this.form.transaction_type = this.transaction.transaction_type.name;
                this.form.date = this.transaction.date;
                this.form.comment = this.transaction.comment;
                this.form.schedule = this.transaction.schedule;
                this.form.budget = this.transaction.budget;
                this.form.reconciled = this.transaction.reconciled;

                // Copy configuration
                this.form.config.amount_from = this.transaction.config.amount_from;
                this.form.config.amount_to = this.transaction.config.amount_to;

                this.form.config.account_from_id = this.transaction.config.account_from_id;
                this.form.config.account_to_id = this.transaction.config.account_to_id;

                // Copy items, and ensure that amount is number
                if (this.transaction.transaction_items.length > 0) {
                    this.transaction.transaction_items
                        .map((item) => {
                            item.amount = Number(item.amount);
                            return item;
                        })
                        .forEach(item => this.form.items.push(item));
                }

                // Copy schedule config
                this.form.schedule_config.frequency = this.transaction.transaction_schedule.frequency;
                this.form.schedule_config.count = this.transaction.transaction_schedule.count;
                this.form.schedule_config.interval = this.transaction.transaction_schedule.interval;
                this.form.schedule_config.start_date = this.transaction.transaction_schedule.start_date;
                this.form.schedule_config.next_date = this.transaction.transaction_schedule.next_date;
                this.form.schedule_config.end_date = this.transaction.transaction_schedule.end_date;
            }

            // Set form action
            this.form.action = this.action;
        },

        mounted() {
            let $vm = this;

            // Account FROM dropdown functionality
            $("#account_from")
                .select2(this.getAccountSelectConfig('from'))
                .on('select2:select', function (e) {
                    const event = new Event("change", { bubbles: true, cancelable: true });
                    e.target.dispatchEvent(event);

                    if ($vm.getAccountType('from') == 'account') {
                        $.ajax({
                            url:  '/api/assets/account/currency/' + e.params.data.id,
                        })
                        .done(data => {
                            $vm.from.account_currency = data;
                        });
                    } else {
                        $.ajax({
                            url:  '/api/assets/get_default_category_for_payee',
                            data: {payee_id: e.params.data.id}
                        })
                        .done(function( data ) {
                            $vm.payeeCategory.id = data.id;
                            $vm.payeeCategory.text = data.full_name;
                        });
                    }
                })
                .on('select2:unselect', function () {
                    $vm.resetAccount('from');
                    if ($vm.getAccountType('from') === 'payee') {
                        $vm.resetPayee();
                    }
                });

            // Load default value for account FROM
            if (this.form.config.account_from_id) {
                const data = this.transaction.config.account_from;

                // Create the option and append to Select2
                $("#account_from")
                    .append(new Option(data.name, data.id, true, true))
                    .trigger('change');

                // Manually trigger the `select2:select` event
                $("#account_from").trigger({
                    type: 'select2:select',
                    params: {
                        data: data
                    }
                });
            }

            // Account TO dropdown functionality
            $("#account_to")
                .select2(this.getAccountSelectConfig('to'))
                .on('select2:select', function (e) {
                    const event = new Event("change", { bubbles: true, cancelable: true });
                    e.target.dispatchEvent(event);

                    if ($vm.getAccountType('to') == 'account') {
                        $.ajax({
                            url:  '/api/assets/account/currency/' + e.params.data.id,
                        })
                        .done(data => {
                            $vm.to.account_currency = data;
                        });
                    } else {
                        $.ajax({
                            url:  '/api/assets/get_default_category_for_payee',
                            data: {payee_id: e.params.data.id}
                        })
                        .done(function( data ) {
                            $vm.payeeCategory.id = data.id;
                            $vm.payeeCategory.text = data.full_name;
                        });
                    }
                })
                .on('select2:unselect', function () {
                    $vm.resetAccount('to');
                    if ($vm.getAccountType('to') === 'payee') {
                        $vm.resetPayee();
                    }
                });

            // Load default value for account TO
            if (this.form.config.account_to_id) {
                const data = this.transaction.config.account_to;

                // Create the option and append to Select2
                $("#account_to")
                    .append(new Option(data.name, data.id, true, true))
                    .trigger('change');

                // Manually trigger the `select2:select` event
                $("#account_to").trigger({
                    type: 'select2:select',
                    params: {
                        data: data
                    }
                });
            }

            //Display fixed footer
            setTimeout(function() {
                $("footer").removeClass("hidden");
            }, 1000);
        },

        methods: {
            changeTransactionType: function (event) {
                const newState = event.currentTarget.getAttribute('value');
                const oldState = this.form.transaction_type;

                if (newState === oldState) {
                    return false;
                }

                if (!confirm("Are you sure, you want to change the transaction type? Some data might get lost.")) {
                    event.currentTarget.blur();
                    return false;
                }

                const oldTypeFrom = this.getAccountType('from');
                const oldTypeTo = this.getAccountType('to');

                this.form.transaction_type = newState;

                // Reassign account FROM functionality, if changed
                if (oldTypeFrom !== this.getAccountType('from')) {
                    if (this.getAccountType('from') === 'account') {
                        this.resetAccount('from');
                    } else {
                        this.resetPayee();
                    }

                    $("#account_from")
                        .val(null).trigger('change')
                        .select2('destroy')
                        .select2(this.getAccountSelectConfig('from'));
                }

                // Reassign account FROM functionality, if changed
                if (oldTypeTo !== this.getAccountType('to')) {
                    if (this.getAccountType('to') === 'account') {
                        this.resetAccount('to');
                    } else {
                        this.resetPayee();
                    }

                    $("#account_to")
                        .val(null).trigger('change')
                        .select2('destroy')
                        .select2(this.getAccountSelectConfig('to'));
                }
            },

            // Add a new empty item to list of transaction items
            addTransactionItem() {
                this.form.items.push({});
            },

            // Check if TO or FROM is account or payee
            getAccountType(type) {
                if (this.form.transaction_type == 'withdrawal') {
                    return type == 'from' ? 'account' : 'payee';
                }
                if (this.form.transaction_type == 'deposit') {
                    return type == 'from' ? 'payee' : 'account';
                }

                // transfer
                return 'account';
            },

            // Get url to payee or account list, based on source or target type
            getAccountApiUrl(type) {
                const accountUrl = '/api/assets/account/standard';
                const payeeUrl = '/api/assets/payee';

                return this.getAccountType(type) == 'account' ? accountUrl : payeeUrl;
            },

            // Account has been removed, its properties need to be removed
            resetAccount(type) {
                this.form.config['account_' + type + '_id'] = null;
                this[type].account_currency = null;
            },

            // Payee has been removed, its properties need to be removed
            resetPayee() {
                this.payeeCategory.id = null;
                this.payeeCategory.text = null;
            },

            getAccountSelectConfig (type) {
                let $vm = this;

                return {
                    ajax: {
                        url: $vm.getAccountApiUrl(type),
                        dataType: 'json',
                        delay: 150,
                        data: function (params) {
                            return {
                                q: params.term,
                                transaction_type: $vm.form.transaction_type,
                                account_type: type
                            };
                        },
                        processResults: function (data) {
                            //TODO: exclude current selection from results
                            //var other = toAccountInput.get(0);
                            //var other_id = (other.selectedIndex === -1 ? -1 : other.options[other.selectedIndex].value);
                            var other_id = null;
                            return {
                                results: data.filter(obj => obj.id !== other_id)
                            };
                        },
                        cache: true
                    },
                    selectOnClose: true,
                    //TODO: make placeholder dynamic to transaction type
                    placeholder: "Select account to debit",
                    allowClear: true
                };
            },

            getCallbackUrl(transactionId) {
                if (this.callback == 'returnToDashboard') {
                    return route('home');
                }

                if (this.callback == 'new') {
                    return window.location.href;
                }

                if (this.callback == 'clone') {
                    return route('transactions.openStandard', { transaction: transactionId, action: 'clone' });
                }

                if (this.callback == 'returnToAccount') {
                    return route('account.history', { account: this.form.config.account_from_id });
                }
            },

            onCancel() {
                if(confirm('Are you sure you want to discard any changes?')) {
                    window.history.back();
                }
                return false;
            },

            onSubmit() {
                if (this.action !== 'edit') {
                    this.form.post(this.formUrl, this.form)
                        .then(( response ) => {
                            location.href = this.getCallbackUrl(response.data.transaction_id);
                        });
                } else {
                    this.form.patch(this.formUrl, this.form)
                        .then(( response ) => {
                            location.href = this.getCallbackUrl(response.data.transaction_id);
                        });
                }
            },
        },

        watch: {
            remainingAmountToPayeeDefault (newAmount) {
                this.form.remaining_payee_default_amount = newAmount;
            },

            payeeDefaultCategory (newId) {
                this.form.remaining_payee_default_category_id = newId;
            },

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
