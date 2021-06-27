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
                                <div class="form-group row" id="transaction_type_container">
                                    <label class="control-label col-sm-3">
                                        Type
                                    </label>
                                    <div class="col-sm-3">
                                        <input
                                            id="transaction_type_withdrawal"
                                            name="transaction_type"
                                            type="radio"
                                            value="withdrawal"
                                            v-model="form.transaction_type"
                                            @change="changeTransactionType"
                                            :checked="form.transaction_type == 'withdrawal'"
                                        />
                                        <label
                                            for="transaction_type_withdrawal"
                                            id="transaction_type_withdrawal_label"
                                        >
                                            Withdrawal
                                        </label>
                                    </div>
                                    <div class="col-sm-3">
                                        <input
                                            id="transaction_type_deposit"
                                            name="transaction_type"
                                            type="radio"
                                            value="deposit"
                                            v-model="form.transaction_type"
                                            @change="changeTransactionType"
                                            :checked="form.transaction_type == 'deposit'"
                                        >
                                        <label
                                            for="transaction_type_deposit"
                                            id="transaction_type_deposit_label"
                                        >
                                            Deposit
                                        </label>
                                    </div>
                                    <div class="col-sm-3">
                                        <input
                                            id="transaction_type_transfer"
                                            name="transaction_type"
                                            type="radio"
                                            value="transfer"
                                            :disabled="form.budget"
                                            v-model="form.transaction_type"
                                            @change="changeTransactionType"
                                            :checked="form.transaction_type == 'transfer'"
                                        >
                                        <label
                                            for="transaction_type_transfer"
                                            id="transaction_type_transfer_label"
                                            :disabled="form.budget"
                                        >
                                            Transfer
                                        </label>
                                    </div>
                                </div>
                                <div
                                    class="form-group row"
                                    :class="form.errors.has('date') ? 'has-error' : ''"
                                >
                                    <label for="transaction_date" class="control-label col-sm-3">
                                        Date
                                    </label>
                                    <div class="col-sm-6">
                                        <date-picker
                                            id="transaction_date"
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
                                    <label for="transaction_date" class="control-label col-sm-3">
                                        Comment
                                    </label>
                                    <div class="col-sm-9">
                                        <input
                                            class="form-control"
                                            id="transaction_comment"
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
                                            <input
                                                class="form-control"
                                                id="transaction_amount_from"
                                                maxlength="50"
                                                type="text"
                                                @change="updateAmount"
                                                v-model.number="form.config.amount_from"
                                            >
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
                                            <input
                                                class="form-control"
                                                id="transaction_amount_to"
                                                maxlength="50"
                                                type="text"
                                                @change="updateAmount"
                                                v-model="form.config.amount_to"
                                            >
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="table">
                                        <table class="table">
                                            <tbody>
                                                <tr>
                                                    <th scope="row" style="border-top:none;">Total allocated:</th>
                                                    <td style="border-top:none;" class="text-right">
                                                        {{ allocatedAmount }}
                                                        <span v-if="from.account_currency">{{from.account_currency}}</span>
                                                    </td>
                                                </tr>
                                                <tr v-show="payeeCategory.id">
                                                    <th scope="row">
                                                        Remaining amount to payee default:
                                                        <span class="notbold">{{ payeeCategory.text }}</span>
                                                    </th>
                                                    <td class="text-right">
                                                        <span>{{ remainingAmountToPayeeDefault }}</span>
                                                        <span v-if="from.account_currency">{{from.account_currency}}</span>
                                                    </td>
                                                </tr>
                                                <tr v-show="!payeeCategory.id">
                                                    <th scope="row">Remaining amount not allocated:</th>
                                                    <td class="text-right">
                                                        <span>{{ remainingAmountNotAllocated }}</span>
                                                        <span v-if="from.account_currency">{{from.account_currency}}</span>
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
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

            <input
                name="id"
                type="hidden"
                v-model="form.id"
            >
            <input
                name="action"
                type="hidden"
                v-model="action"
            >
            <input
                name="config_type"
                type="hidden"
                value="transaction_detail_standard"
            >

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
                            :class="callback == 'newStandard' ? 'active' : ''"
                            type="button"
                            value="newStandard"
                            @click="callback = $event.currentTarget.getAttribute('value')"
                        >
                            Add an other transaction
                        </button>
                        <button
                            class="btn btn-default"
                            :class="callback == 'cloneStandard' ? 'active' : ''"
                            type="button"
                            value="cloneStandard"
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
                        <Button class="btn btn-primary" :form="form">Save</Button>
                    </div>
                </div>
            </div>
        </div>
    </footer>

        </form>
    </div>
</template>

<script>
    let math = require("mathjs");
    require('select2');

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
            Button, AlertErrors
        },

        props: {
            action: String,
            formUrl: String,
            transaction: Object,
            callback: String,
        },

        data() {
            let data = {};

            // Storing all data and references about source account or payee
            // Set as withdrawal by default
            data.from = {
                type: 'account',
                account_id : null,
                account_currency : null,
            };

            // Storing all data and references about target account or payee
            // Set as withdrawal by default
            data.to = {
                type: 'payee',
                account_id : null,
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
                schdedule: false,
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

            // Adjust initial callback
            data.callback = this.callback;

            return data;
        },

        computed: {
            formErrors() {
                return this.form.errors;
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
            if (this.transaction) {

                this.form.config.account_from_id = this.transaction.config.account_from_id;
                this.form.config.account_to_id = this.transaction.config.account_to_id;

                // Populate form data
                this.form.id = this.transaction.id
                this.form.transaction_type = this.transaction.transaction_type.name;
                this.form.date = this.transaction.date;
                this.form.comment = this.transaction.comment;
                this.form.schdedule = this.transaction.schdedule;
                this.form.budget = this.transaction.budget;
                this.form.reconciled = this.transaction.reconciled;

                // Copy configuration
                this.form.config.amount_from = this.transaction.config.amount_from;
                this.form.config.amount_to = this.transaction.config.amount_to;

                this.form.config.acount_from_id = this.transaction.config.account_from_id;
                this.form.config.acount_to_id = this.transaction.config.account_to_id;

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

            }
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
                .on('select2:unselect', function (e) {
                    $vm.resetAccount('from');
                });

            // Load default value for account FROM
            if (this.form.config.acount_from_id) {
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
                .on('select2:unselect', function (e) {
                    $vm.resetAccount('to');
                });

            // Load default value for account TO
            if (this.form.config.acount_to_id) {
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
                // TODO: get user confirmation before actual change

                // Reassign account FROM functionality
                // TODO: do this if actual change is needed (e.g. not from withdrawal -> transfer change)
                $("#account_from").select2('destroy');
                $("#account_from").select2(this.getAccountSelectConfig('from'));

                // Reassign account FROM functionality
                // TODO: do this if actual change is needed (e.g. not from withdrawal -> transfer change)
                $("#account_to").select2('destroy');
                $("#account_to").select2(this.getAccountSelectConfig('to'));

            },

            // Add a new empty item to list of transaction items
            addTransactionItem() {
                this.form.items.push({});
            },

            // Update TO or FROM amount with math calculation
            updateAmount: function (event) {
                let amount = math.evaluate(event.target.value.replace(/\s/g,""));
                if(amount <= 0) throw Error("Positive number expected");

                // Update field with calculated value
                event.target.value = amount || '';

                // Emit event to update v-model
                event.target.dispatchEvent(new Event('input'));

                // If FROM value is updated, check if TO needs to by synced
                // TODO: compute if exchange rate is present
                // TODO: update on other relevant changes (e.g. currency needed)
                if (event.target.id === 'transaction_amount_from'
                    && !(this.from.account_currency && this.to.account_currency && this.from.account_currency != this.to.account_currency)) {
                    this.form.config.amount_to = amount || '';
                }
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
                const accountUrl = '/api/assets/account';
                const payeeUrl = '/api/assets/payee';

                return this.getAccountType(type) == 'account' ? accountUrl : payeeUrl;
            },

            // Account has been removed, its properties need to be removed
            resetAccount(type) {
                this[type].account_id = null;
                this[type].account_currency = null;
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
                    return '/';
                }

                if (this.callback == 'newStandard') {
                    return window.location.href;
                }

                if (this.callback == 'cloneStandard') {
                    //TODO: should this come from route function
                    return '/transaction/clone/' + transactionId;
                }

                if(this.callback == 'returnToAccount') {
                    //TODO: should this come from route function
                    return '/account/history/' + this.form.config.account_from_id;
                }
            },

            onCancel() {
                if(confirm('Are you sure you want to discard any changes?')) {
                    window.history.back();
                }
                return false;
            },

            onSubmit() {
                if (!this.transaction) {
                    this.form.post(this.formUrl, this.form)
                        .then(( response ) => {
                            location.href = this.getCallbackUrl(response.data.transaction_id);
                    })

                    return;
                }

                this.form.post(this.formUrl, this.form)
                        .then(( response ) => {
                            location.href = this.getCallbackUrl(response.data.transaction_id);
                    })
            },
        },

        watch: {
            remainingAmountToPayeeDefault (newAmount) {
                this.form.remaining_payee_default_amount = newAmount;
            },

            payeeDefaultCategory (newId) {
                this.form.remaining_payee_default_category_id = newId;
            }
        }
    }
</script>
