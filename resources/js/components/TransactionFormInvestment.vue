<template>
    <div>
        <AlertErrors :form="form" message="There were some problems with your input." />

        <!-- form start -->
        <form
            accept-charset="UTF-8"
            @submit.prevent="onSubmit"
            autocomplete="off"
        >
            <div class="box box-primary">
                <div class="box-header with-border">
                    <h3 class="box-title">
                        Transaction properties
                    </h3>
                </div>
                <!-- /.box-header -->

                <div class="box-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group valid">
                                        <label for="transaction_type" class="control-label">Transaction type</label>
                                        <select id="transaction_type" class="form-control" v-model="form.transaction_type" @change="transactionTypeChanged($event)">
                                            <option
                                                v-for="item in transactionTypes"
                                                :key="item.name"
                                                :value="item.name"
                                            >{{ item.name }}</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="account" class="control-label">Account</label>
                                        <select
                                            class="form-control"
                                            id="account"
                                            v-model="form.config.account_id">
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="date" class="control-label">Date</label>
                                        <date-picker
                                            id="date"
                                            :lang="dataPickerLanguage"
                                            v-model="form.date"
                                            value-type="format"
                                            format="YYYY-MM-DD"
                                            type="date"
                                            :disabled="form.schedule"
                                        ></date-picker>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <input
                                            id="entry_type_schedule"
                                            class="checkbox-inline"
                                            :disabled="form.reconciled"
                                            type="checkbox"
                                            value="1"
                                            v-model="form.schedule"
                                        >
                                        <label for="entry_type_schedule" class="control-label">Scheduled</label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="investment" class="control-label">Investment</label>
                                        <select
                                            class="form-control"
                                            id="investment"
                                            v-model="form.config.investment_id">
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-12">
                                    <div class="form-group">
                                        <label for="comment" class="control-label">Comment</label>
                                        <input
                                            class="form-control"
                                            id="comment"
                                            maxlength="255"
                                            type="text"
                                            v-model="form.comment"
                                        />
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="transaction_quantity" class="control-label">Quantity</label>
                                        <MathInput
                                            class="form-control"
                                            id="transaction_quantity"
                                            v-model="form.config.quantity"
                                            :disabled="!transactionTypeSettings.quantity"
                                        ></MathInput>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="transaction_price" class="control-label">Price</label>
                                        <MathInput
                                            class="form-control"
                                            id="transaction_price"
                                            v-model="form.config.price"
                                            :disabled="!transactionTypeSettings.price"
                                        ></MathInput>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="transaction_commission" class="control-label">Commission</label>
                                        <MathInput
                                            class="form-control"
                                            id="transaction_commission"
                                            v-model="form.config.commission"
                                        ></MathInput>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="transaction_tax" class="control-label">Tax</label>
                                        <MathInput
                                            class="form-control"
                                            id="transaction_tax"
                                            v-model="form.config.tax"
                                        ></MathInput>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="transaction_dividend" class="control-label">Dividend</label>
                                        <MathInput
                                            class="form-control"
                                            id="transaction_dividend"
                                            v-model="form.config.dividend"
                                            :disabled="!transactionTypeSettings.dividend"
                                        ></MathInput>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="transaction_total" class="control-label">
                                            Total
                                            <span v-if="currency">({{currency}})</span>
                                        </label>
                                        <input type="text" :value="total" class="form-control" disabled="disabled">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
                <!-- /.box-body -->

        <!-- /.box-footer -->
    </div>
    <!-- /.box -->

        <transaction-schedule
            :isVisible="form.schedule"
            :isSchedule="form.schedule"
            :isBudget="false"
            :schedule="form.schedule_config"
            :form="form"
        ></transaction-schedule>

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
                            <button
                                class="btn btn-sm btn-default"
                                type="button"
                                style="margin-left: 10px; margin-bottom: 5px;"
                                @click="onCancel"
                            >
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
    import {Button, AlertErrors} from 'vform/src/components/bootstrap4'

    import DatePicker from 'vue2-datepicker';
    import 'vue2-datepicker/index.css';

    import TransactionSchedule from './TransactionSchedule.vue'

    export default {
        components: {
            'transaction-schedule': TransactionSchedule,
            MathInput,
            DatePicker,
            Button, AlertErrors
        },

        props: {
            action: String,
            transaction: Object,
        },

        data() {
            let data = {};

            // Main form data
            data.form = new Form({
                transaction_type: 'Buy',
                config_type: 'transaction_detail_investment',
                date: '',
                comment: null,
                schedule: false,
                budget: false,
                reconciled: false,
                config: {},
                schedule_config: {
                    frequency: 'DAILY',
                    interval: 1,
                },
            });

            // Other values
            data.account_currency = null;
            data.account_currency_id = null;
            data.investment_currency = null;
            data.investment_currency_id = null;

            data.csrfToken = $('meta[name="csrf-token"]').attr('content');

            // TODO: adjust initial callback based on action
            data.callback = 'new';

            // Date picker settings
            data.dataPickerLanguage = {
                formatLocale: {
                    firstDayOfWeek: 1,
                },
                monthBeforeYear: false,
            };

            return data;
        },

        computed: {
            formUrl() {
                if (this.action === 'edit') {
                    return route('transactions.updateInvestment', {transaction: this.form.id});
                }

                return route('transactions.storeInvestment');
            },

            total() {
                return    (this.form.config.quantity || 0) * (this.form.config.price || 0)
                        + (this.form.config.dividend || 0)
                        - (this.form.config.commission || 0)
                        - (this.form.config.tax || 0);
            },

            transactionTypeSettings() {
                return this.transactionTypes.filter(item => item.name == this.form.transaction_type)[0];
            },

            currency() {
                return this.account_currency || this.investment_currency;
            }
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

                //TODO: add reconciled support
                //this.form.reconciled = this.transaction.reconciled;

                // Copy configuration
                this.form.config.account_id = this.transaction.config.account_id;
                this.form.config.investment_id = this.transaction.config.investment_id;

                this.form.config.quantity = this.transaction.config.quantity;
                this.form.config.price = this.transaction.config.price;
                this.form.config.dividend = this.transaction.config.dividend;
                this.form.config.commission = this.transaction.config.commission;
                this.form.config.tax = this.transaction.config.tax;

                // Copy schedule config
                if (this.transaction.transaction_schedule) {
                    this.form.schedule_config.frequency = this.transaction.transaction_schedule.frequency;
                    this.form.schedule_config.count = this.transaction.transaction_schedule.count;
                    this.form.schedule_config.interval = this.transaction.transaction_schedule.interval;
                    this.form.schedule_config.start_date = this.transaction.transaction_schedule.start_date;
                    this.form.schedule_config.next_date = this.transaction.transaction_schedule.next_date;
                    this.form.schedule_config.end_date = this.transaction.transaction_schedule.end_date;
                }
            }

            // TODO: make the list dynamic based on database settings
            this.transactionTypes = [
                {
                    name: 'Buy',
                    quantity: true,
                    price: true,
                    dividend: false,
                },
                {
                    name: 'Sell',
                    quantity: true,
                    price: true,
                    dividend: false,
                    },
                {
                    name: 'Add shares',
                    quantity: true,
                    price: false,
                    dividend: false,
                    },
                {
                    name: 'Remove shares',
                    quantity: true,
                    price: false,
                    dividend: false,
                    },
                {
                    name: 'Dividend',
                    quantity: false,
                    price: false,
                    dividend: true,
                },
                {
                    name: 'S-Term Cap Gains Dist',
                    quantity: false,
                    price: false,
                    dividend: true,
                },
                {
                    name: 'L-Term Cap Gains Dist',
                    quantity: false,
                    price: false,
                    dividend: true,
                },
            ];

            // Check for various default values in URL
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('account')) {
                this.form.config.account_id = urlParams.get('account');
            }

            // Set form action
            this.form.action = this.action;
        },

        mounted() {
            let $vm = this;

            // Account dropdown functionality
            $("#account")
                .select2({
                    ajax: {
                        url: '/api/assets/account/investment',
                        dataType: 'json',
                        delay: 150,
                        data: function (params) {
                            return {
                                q: params.term,
                                transaction_type: $vm.form.transaction_type,
                                currency_id: $vm.investment_currency_id,
                                _token: $vm.csrfToken,
                            };
                        },
                        processResults: function (data) {
                            return {
                                results: data
                            };
                        },
                        cache: true
                    },
                    selectOnClose: true,
                    placeholder: "Select account",
                    allowClear: true
                })
                .on('select2:select', function (e) {
                    const event = new Event("change", { bubbles: true, cancelable: true });
                    e.target.dispatchEvent(event);

                    $.ajax({
                        url:  '/api/assets/account/' + e.params.data.id,
                        data: {
                            _token: $vm.csrfToken,
                        },
                    })
                    .done(data => {
                        $vm.account_currency = data.config.currency.suffix;
                        $vm.account_currency_id = data.config.currency.id;
                    });
                })
                .on('select2:unselect', function (e) {
                    $vm.account_id  = null;
                    $vm.account_currency  = null;
                });

            // Load default value for account
            if (this.form.config.account_id) {
                $.ajax({
                    url:  '/api/assets/account/' + this.form.config.account_id,
                    data: {
                        _token: $vm.csrfToken,
                    },
                })
                .done(data => {
                    // Create the option and append to Select2
                    $("#account")
                        .append(new Option(data.name, data.id, true, true))
                        .trigger('change');

                    // Manually trigger the `select2:select` event
                    $("#account").trigger({
                        type: 'select2:select',
                        params: {
                            data: data
                        }
                    });
                });
            }

            // Investment dropdown functionality
            $('#investment').select2({
                ajax: {
                    url: '/api/assets/investment',
                    data: function (params) {
                            return {
                                q: params.term,
                                currency_id: $vm.account_currency_id,
                                _token: $vm.csrfToken,
                            };
                    },
                    dataType: 'json',
                    delay: 150,
                    processResults: function (data) {
                        return {
                            results: data
                        };
                    },
                    cache: true
                },
                selectOnClose: true,
                placeholder: "Select investment",
                allowClear: true
            })
            .on('select2:select', function (e) {
                const event = new Event("change", { bubbles: true, cancelable: true });
                e.target.dispatchEvent(event);

                $.ajax({
                    url: route('investment.getDetails', {'investment': e.params.data.id}),
                    data: {
                        _token: $vm.csrfToken,
                    },
                })
                .done(function( data ) {
                    $vm.investment_currency_id = data.currency.id;
                    $vm.investment_currency = data.currency.suffix;
                });
            }).on('select2:unselect', function (e) {
                $vm.investment_id = null;
                $vm.investment_currency_id = null;
                $vm.investment_currency = null;
            });

            // Load default value for investment
            if (this.form.config.investment_id) {
                const data = this.transaction.config.investment;

                // Create the option and append to Select2
                $("#investment")
                    .append(new Option(data.name, data.id, true, true))
                    .trigger('change');

                // Manually trigger the `select2:select` event
                $("#investment").trigger({
                    type: 'select2:select',
                    params: {
                        data: data
                    }
                });
            }

            // Display fixed footer
            setTimeout(function() {
                $("footer").removeClass("hidden");
            }, 1000);
        },

        methods: {
            transactionTypeChanged() {
                const settings = this.transactionTypeSettings;
                if (!settings.quantity) {
                    this.form.config.quantity = null;
                }
                if (!settings.price) {
                    this.form.config.price = null;
                }
                if (!settings.dividend) {
                    this.form.config.dividend = null;
                }
            },
            getCallbackUrl(transactionId) {
                if (this.callback == 'returnToDashboard') {
                    return route('home');
                }

                if (this.callback == 'new') {
                    return route('transactions.createInvestment');
                }

                if (this.callback == 'clone') {
                    return route('transactions.openInvestment', { transaction: transactionId, action: 'clone' });
                }

                if (this.callback == 'returnToPrimaryAccount') {
                    return route('account.history', { account: this.form.config.account_id });
                }

                if (this.callback == 'returnToSecondaryAccount') {
                    return route('account.history', { account: this.form.config.account_id });
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

        }
    }
</script>
