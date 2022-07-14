<template>
    <div>
        <AlertErrors :form="form" message="There were some problems with your input." />

        <payee-form
            action = "new"
            :payee = "{}"
            ref="payeeModal"
            @payeeSelected="setPayee"
        ></payee-form>

        <!-- form start -->
        <form
            accept-charset="UTF-8"
            @submit.prevent="onSubmit"
            autocomplete="off"
        >
            <div>
                <div class="row">
                    <!-- left column -->
                    <div class="col-md-4">
                        <div class="box box-primary">
                            <div class="box-header with-border">
                                <h3 class="box-title">
                                    Properties
                                </h3>
                            </div>
                            <!-- /.box-header -->

                            <div class="box-body">
                                <div class="row">
                                    <div class="form-group col-xs-12 col-sm-6">
                                        <label class="block-label">
                                            Type
                                        </label>
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
                                    <div
                                        class="form-group col-xs-12 col-sm-6"
                                        :class="form.errors.has('date') ? 'has-error' : ''"
                                    >
                                        <label class="block-label" for="date">
                                            Date
                                        </label>
                                        <date-picker
                                            id="date"
                                            :lang="dataPickerLanguage"
                                            v-model="form.date"
                                            value-type="format"
                                            format="YYYY-MM-DD"
                                            type="date"
                                            :disabled="form.schedule || form.budget"
                                        ></date-picker>
                                    </div>
                                </div>
                                <div class="row">
                                    <div
                                        class="form-group col-xs-12 col-sm-6"
                                        :class="form.errors.has('config.account_from_id') ? 'has-error' : ''"
                                    >
                                        <label class="control-label block-label">
                                            {{ accountFromFieldLabel }}
                                        </label>
                                        <select
                                            class="form-control"
                                            id="account_from"
                                            style="width: 85%;"
                                            v-model="form.config.account_from_id">
                                        </select>
                                        <button
                                            class="btn btn-xs btn-success"
                                            @click="this.$refs.payeeModal.show()"
                                            style="margin-left: 10px;"
                                            title="Add a new payee"
                                            type="button"
                                            v-show="form.transaction_type == 'deposit'"
                                        ><span class="fa fa-plus"></span></button>
                                    </div>

                                    <div
                                        class="form-group col-xs-12 col-sm-6"
                                        :class="form.errors.has('config.account_to_id') ? 'has-error' : ''"
                                    >
                                        <label class="control-label block-label">
                                            {{ accountToFieldLabel }}
                                        </label>
                                        <select
                                            class="form-control"
                                            id="account_to"
                                            style="width: 85%;"
                                            v-model="form.config.account_to_id">
                                        </select>
                                        <button
                                            class="btn btn-xs btn-success"
                                            @click="this.$refs.payeeModal.show()"
                                            style="margin-left: 10px;"
                                            title="Add a new payee"
                                            type="button"
                                            v-show="form.transaction_type == 'withdrawal'"
                                        ><span class="fa fa-plus"></span></button>
                                    </div>
                                </div>
                                <div class="row">
                                    <div
                                        class="form-group col-xs-12"
                                        :class="form.errors.has('comment') ? 'has-error' : ''"
                                    >
                                        <label for="comment" class="control-label block-label">
                                            Comment
                                        </label>
                                        <input
                                            class="form-control"
                                            id="comment"
                                            maxlength="255"
                                            type="text"
                                            v-model="form.comment"
                                        />
                                    </div>
                                </div>

                                    <div class="form-group form-horizontal row">
                                        <div class="col-xs-4 checkbox">
                                            <label
                                                :title="(action === 'replace' ? 'You cannot change schedule settings for this type of action' : '')"
                                                :data-toggle="(action === 'replace' ? 'tooltip' : '')"
                                            >
                                                <input
                                                    :disabled="form.reconciled || action === 'replace'"
                                                    type="checkbox"
                                                    value="1"
                                                    v-model="form.schedule"
                                                >
                                                Scheduled
                                            </label>
                                        </div>
                                        <div class="col-xs-4 checkbox">
                                            <label
                                                :title="(action === 'replace' ? 'You cannot change schedule settings for this type of action' : '')"
                                                :data-toggle="(action === 'replace' ? 'tooltip' : '')"
                                            >
                                                <input
                                                    :disabled="form.reconciled || form.transaction_type == 'transfer' || action === 'replace'"
                                                    type="checkbox"
                                                    value="1"
                                                    v-model="form.budget"
                                                >
                                                Budget
                                            </label>
                                        </div>
                                        <div class="col-xs-4 checkbox">
                                            <label>
                                                <input
                                                    :disabled="form.schedule || form.budget"
                                                    type="checkbox"
                                                    value="1"
                                                    v-model="form.reconciled"
                                                >
                                                Reconciled
                                            </label>
                                        </div>
                                    </div>

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
                                <div class="row">
                                    <div
                                        class="form-group col-xs-4"
                                        :class="form.errors.has('config.amount_from') ? 'has-error' : ''"
                                    >
                                        <label for="transaction_amount_from" class="control-label">
                                            {{ ammountFromFieldLabel }}
                                            <span v-if="ammountFromCurrencyLabel">({{ ammountFromCurrencyLabel }})</span>
                                        </label>
                                        <MathInput
                                            class="form-control"
                                            id="transaction_amount_from"
                                            v-model="form.config.amount_from"
                                        ></MathInput>
                                    </div>
                                    <div
                                        v-show="exchangeRatePresent"
                                        class="col-xs-4">
                                        <span class="block-label">Exchange rate</span>
                                        {{ exchangeRate }}
                                    </div>
                                    <div
                                        v-show="exchangeRatePresent"
                                        class="form-group col-xs-4"
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
                        </div>
                        <!-- /.box -->

                        <transaction-schedule
                            v-if="form.schedule || form.budget"
                            :isSchedule="form.schedule"
                            :isBudget="form.budget"
                            :schedule="form.schedule_config"
                            :form="form"
                        ></transaction-schedule>

                        <transaction-schedule
                            v-if="(form.schedule || form.budget) && action === 'replace'"
                            :withCheckbox = "true"
                            title = "Update base schedule"
                            :allowCustomization = "false"

                            :isSchedule = "form.schedule"
                            :isBudget = "form.budget"
                            :schedule = "form.original_schedule_config"
                            :form = "form"
                            ref = "scheduleOriginal"
                        ></transaction-schedule>

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
                    </div>
                    <!--/.col (right) -->

                </div>
                <!-- /.row -->

                <div class="box box-primary">
                    <div class="box-body">
                        <div class="row">
                            <div class="col-xs-8 col-sm-3">
                                <dl class="dl-horizontal">
                                    <dt>Total amount:</dt>
                                    <dd>
                                        {{ form.config.amount_from || 0 }}
                                        <span v-if="from.account_currency">{{from.account_currency}}</span>
                                    </dd>
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
                            <div class="hidden-xs col-sm-7">
                                <label class="control-label block-label">After saving</label>
                                <div class="btn-group">
                                    <button
                                        v-for="item in activeCallbackOptions"
                                        :key="item.id"
                                        class="btn btn-default"
                                        :class="callback == item.value ? 'active' : ''"
                                        type="button"
                                        :value="item.value"
                                        @click="callback = $event.currentTarget.getAttribute('value')"
                                    >
                                        {{ item.label }}
                                    </button>
                                </div>
                            </div>
                            <div class="col-xs-4 col-sm-2">
                                <div class="pull-right">
                                    <button
                                        class="btn btn-sm btn-default"
                                        @click="onCancel"
                                        style="margin-left: 10px; margin-bottom: 5px;"
                                        type="button"
                                    >
                                        Cancel
                                    </button>
                                    <Button
                                        class="btn btn-primary"
                                        :disabled="form.busy"
                                        :form="form"
                                        style="margin-left: 10px; margin-bottom: 5px;"
                                    >
                                        Save
                                    </Button>
                                </div>
                            </div>
                            <div class="col-xs-12 d-sm-none">
                                <label class="control-label block-label">After saving</label>
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
        </form>
    </div>
</template>

<script>
    require('select2');

    import MathInput from './MathInput.vue'

    import Form from 'vform'
    import {Button, AlertErrors} from 'vform/src/components/bootstrap4'

    import DatePicker from 'vue2-datepicker';
    import 'vue2-datepicker/index.css';

    import TransactionItemContainer from './TransactionItemContainer.vue'
    import TransactionSchedule from './TransactionSchedule.vue'

    import PayeeForm from './../components/PayeeForm.vue'

    export default {
        components: {
            'transaction-item-container': TransactionItemContainer,
            'transaction-schedule': TransactionSchedule,
            'payee-form': PayeeForm,
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
                date: new Date().toISOString().slice(0, 10),
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

            // Id counter for items
            data.itemCounter = 0;

            // TODO: adjust initial callback based on action
            data.callback = 'new';

            // Date picker settings
            data.dataPickerLanguage = {
                formatLocale: {
                    firstDayOfWeek: 1,
                },
                monthBeforeYear: false,
            };

            // Possible callback options
            data.callbackOptions = [
                {
                    value: 'new',
                    label: 'Add an other transaction',
                    enabled: true,
                },
                {
                    value: 'clone',
                    label: 'Clone this transaction',
                    enabled: true,
                },
                {
                    value: 'returnToPrimaryAccount',
                    label: 'Return to selected account',
                    enabled: true,
                },
                {
                    value: 'returnToSecondaryAccount',
                    label: 'Return to target account',
                    enabled: false,
                },
                {
                    value: 'returnToDashboard',
                    label: 'Return to dashboard',
                    enabled: true,
                },
                {
                    value: 'back',
                    label: 'Return to previous page',
                    enabled: true,
                },
            ]

            return data;
        },

        computed: {
            formUrl() {
                if (this.action === 'edit') {
                    return route('transactions.updateStandard', {transaction: this.form.id});
                }

                return route('transactions.storeStandard');
            },

            activeCallbackOptions() {
                return this.callbackOptions.filter(option => option.enabled);
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
                return (this.exchangeRatePresent ? 'Amount from' : 'Amount')
            },

            // Amound from currency is dependent on many other data
            ammountFromCurrencyLabel() {
                if (this.form.transaction_type === 'withdrawal' || this.form.transaction_type === 'transfer') {
                    return this.from.account_currency;
                }

                if (this.form.transaction_type === 'deposit') {
                    return this.to.account_currency;
                }

                return '';
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

        created() {
            var $vm = this;

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
                            item.id = $vm.itemCounter++;
                            item.amount = Number(item.amount);
                            return item;
                        })
                        .forEach(item => this.form.items.push(item));
                }

                // Copy schedule config
                if (this.transaction.transaction_schedule) {
                    this.form.schedule_config.frequency = this.transaction.transaction_schedule.frequency;
                    this.form.schedule_config.count = this.transaction.transaction_schedule.count;
                    this.form.schedule_config.interval = this.transaction.transaction_schedule.interval;
                    this.form.schedule_config.start_date = this.transaction.transaction_schedule.start_date;
                    this.form.schedule_config.next_date = this.transaction.transaction_schedule.next_date;
                    this.form.schedule_config.end_date = this.transaction.transaction_schedule.end_date;
                    this.form.schedule_config.inflation = this.transaction.transaction_schedule.inflation;
                }

                // If creating a schedule clone, we need to duplicate the schedule config, and make some adjustments
                if (this.action === 'replace') {
                    this.form.original_schedule_config = JSON.parse(JSON.stringify(this.form.schedule_config));
                    this.form.original_schedule_config.next_date = undefined;

                    // Set new schedule start date to today
                    this.form.schedule_config.start_date = new Date().toISOString().slice(0, 10);

                    // Set cloned schedule end date to today - 1 day
                    this.form.original_schedule_config.end_date = new Date(new Date().getTime() - 24 * 60 * 60 * 1000).toISOString().slice(0, 10);
                }
            }

            // Check for various default values in URL
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('account_from')) {
                this.form.config.account_from_id = urlParams.get('account_from');
            }
            /* TODO: planned function to set account_to from URL
            if (urlParams.get('account_to')) {
                this.form.config.account_to_id = urlParams.get('account_to');
            }
            */

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
                            data: {
                                _token: $vm.csrfToken,
                            }
                        })
                        .done(data => {
                            $vm.from.account_currency = data;
                        });
                    } else {
                        $.ajax({
                            url:  '/api/assets/get_default_category_for_payee',
                            data: {
                                payee_id: e.params.data.id,
                                _token: $vm.csrfToken,
                            }
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

            // Load default value for account FROM, based on transaction type
            if (this.form.config.account_from_id) {
                if (this.getAccountType('from') == 'account') {
                    $.ajax({
                        url:  '/api/assets/account/' + this.form.config.account_from_id,
                        data: {
                            _token: $vm.csrfToken,
                        }
                    })
                    .done(data => {
                        // Create the option and append to Select2
                        $vm.addNewItemToSelect('#account_from', data.id, data.name);
                    });
                } else if (this.getAccountType('from') == 'payee') {
                    const data = this.transaction.config.account_from;
                    // Create the option and append to Select2
                    $vm.addNewItemToSelect('#account_from', data.id, data.name);
                }
            }

            // Account TO dropdown functionality
            $("#account_to")
                .select2(this.getAccountSelectConfig('to'))
                .on('select2:select', function (e) {
                    const event = new Event("change", { bubbles: true, cancelable: true });
                    e.target.dispatchEvent(event);

                    if ($vm.getAccountType('to') === 'account') {
                        $.ajax({
                            url:  '/api/assets/account/currency/' + e.params.data.id,
                            data: {
                                _token: $vm.csrfToken,
                            }
                        })
                        .done(data => {
                            $vm.to.account_currency = data;
                        });
                    } else if ($vm.getAccountType('to') === 'payee') {
                        $.ajax({
                            url:  '/api/assets/get_default_category_for_payee',
                            data: {
                                payee_id: e.params.data.id,
                                _token: $vm.csrfToken,
                            }
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
                if (this.getAccountType('to') == 'account') {
                    $.ajax({
                        url:  '/api/assets/account/' + this.form.config.account_to_id,
                        data: {
                            _token: $vm.csrfToken,
                        }
                    })
                    .done(data => {
                        // Create the option and append to Select2
                        $vm.addNewItemToSelect('#account_to', data.id, data.name);
                    });
                } else if (this.getAccountType('to') == 'payee') {
                    const data = this.transaction.config.account_to;
                    // Create the option and append to Select2
                    $vm.addNewItemToSelect('#account_to', data.id, data.name);
                }
            }

            // Initial sync between schedules, if applicable
            this.syncScheduleStartDate(this.form.schedule_config.start_date);
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
                        this.from.type = 'account';
                        this.resetAccount('from');
                    } else {
                        this.from.type = 'payee';
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
                        this.to.type = 'account';
                        this.resetAccount('to');
                    } else {
                        this.to.type = 'payee';
                        this.resetPayee();
                    }

                    $("#account_to")
                        .val(null).trigger('change')
                        .select2('destroy')
                        .select2(this.getAccountSelectConfig('to'));
                }

                // Update callback options
                var foundCallbackIndex = this.callbackOptions.findIndex(x => x.value === 'returnToSecondaryAccount');
                this.callbackOptions[foundCallbackIndex]['enabled'] = (newState === 'transfer')

                // Ensure, that selected item is enabled. Otherwise, set to first enabled option
                var selectedCallbackIndex = this.callbackOptions.findIndex(x => x.value === this.callback);
                if (! this.callbackOptions[selectedCallbackIndex].enabled) {
                    this.callback = this.callbackOptions.find(option => option.enabled)['value'];
                }
            },

            // Add a new empty item to list of transaction items
            addTransactionItem() {
                this.form.items.push({
                    id: this.itemCounter++,
                });
            },

            // Check if TO or FROM is account or payee
            getAccountType(type) {
                if (this.form.transaction_type === 'withdrawal') {
                    return type == 'from' ? 'account' : 'payee';
                }
                if (this.form.transaction_type === 'deposit') {
                    return type == 'from' ? 'payee' : 'account';
                }

                // Transfer
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

            getPlaceholder(type) {
                if (this.getAccountType(type) == 'account') {
                    return 'Select account';
                }
                return 'Select payee';
            },

            getAccountSelectConfig (type) {
                let $vm = this;
                let otherType = (type == 'from' ? 'to' : 'from');

                return {
                    ajax: {
                        url: $vm.getAccountApiUrl(type),
                        dataType: 'json',
                        delay: 150,
                        data: function (params) {
                            return {
                                _token: $vm.csrfToken,
                                q: params.term,
                                transaction_type: $vm.form.transaction_type,
                                account_type: type,
                                account_id: $vm.accountId,
                            };
                        },
                        processResults: function (data) {
                            // Exclude account that is selected in other account select
                            let otherAccountId = $vm.form.config['account_' + otherType + '_id'];
                            if (otherAccountId) {
                                data = data.filter(item => item.id != otherAccountId);
                            }

                            return {
                                results: data,
                            };
                        },
                        cache: true
                    },
                    selectOnClose: false,
                    // Set placeholder based on type parameter and transaction type
                    placeholder: this.getPlaceholder(type),
                    allowClear: true,
                    width: 'resolve',
                };
            },

            // Determine, which account to use as a callback, if user wants to return to selected account
            getReturnAccount(accountType) {
                if (accountType === 'primary' && this.form.transaction_type == 'deposit') {
                    return this.form.config.account_to_id;
                }

                if (accountType === 'secondary') {
                    return this.form.config.account_to_id;
                }

                // Withdrawal and transfer primary
                return this.form.config.account_from_id;
            },

            loadCallbackUrl(transactionId) {
                if (this.callback === 'returnToDashboard') {
                    location.href = route('home');
                }

                if (this.callback === 'new') {
                    location.href = route('transactions.createStandard');
                }

                if (this.callback === 'clone') {
                    location.href = route('transactions.openStandard', { transaction: transactionId, action: 'clone' });
                }

                if (this.callback === 'returnToPrimaryAccount') {
                    location.href = route('account.history', { account: this.getReturnAccount('primary') });
                }

                if (this.callback === 'returnToSecondaryAccount') {
                    location.href = route('account.history', { account: this.getReturnAccount('secondary') });
                }

                // Default, return back
                if (document.referrer) {
                    location.href = document.referrer;
                } else {
                    history.back();
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
                            this.loadCallbackUrl(response.data.transaction_id);
                        });
                } else {
                    this.form.patch(this.formUrl, this.form)
                        .then(( response ) => {
                            this.loadCallbackUrl(response.data.transaction_id);
                        });
                }
            },

            setPayee(payee) {
                // Determine which of the accounts need update
                if (!['withdrawal', 'deposit'].includes(this.form.transaction_type)) {
                    return;
                }

                var accountSelector = (this.form.transaction_type == 'withdrawal' ? '#account_to' : '#account_from');

                this.addNewItemToSelect(accountSelector, payee.id, payee.name);
            },

            addNewItemToSelect(selector, id, name) {
                $(selector)
                    .append(new Option(name, id, true, true))
                    .trigger('change')
                    .trigger({
                        type: 'select2:select',
                        params: {
                            data: {
                                id: id,
                                name: name,
                            }
                        }
                    });
            },

            // Sync the standard schedule start date to the cloned schedule end date
            syncScheduleStartDate(newDate) {
                if (!this.form.original_schedule_config) {
                    return;
                }

                if (!this.$refs.scheduleOriginal || this.$refs.scheduleOriginal.allowCustomization) {
                    return;
                }

                let date = new Date(newDate);
                date.setDate( date.getDate() - 1);
                this.form.original_schedule_config.end_date = date.toISOString().split('T')[0];
            },
        },

        watch: {
            remainingAmountToPayeeDefault (newAmount) {
                this.form.remaining_payee_default_amount = newAmount;
            },

            payeeDefaultCategory (newId) {
                this.form.remaining_payee_default_category_id = newId;
            },

            // On change of new schedule start date, adjust original schedule end date to previous day
            "form.schedule_config.start_date": function (newDate) {
                this.syncScheduleStartDate(newDate);
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

    // Initialize tooltips
    // TODO: can this be part of Vue init?
    $(document).ready(function() {
        $('[data-toggle="tooltip"]').tooltip()
    });

</script>

<style scoped>
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
