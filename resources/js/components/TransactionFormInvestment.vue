<template>
    <div id="transactionFormInvestment">
        <AlertErrors :form="form" :message="__('There were some problems with your input.')"/>

        <form
                accept-charset="UTF-8"
                @submit.prevent="onSubmit"
                autocomplete="off"
        >
            <div class="row">
                <div class="col-md-6">
                    <div class="card mb-3">
                        <div class="card-header">
                            <div class="card-title">
                                {{ __('Properties') }}
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6 mb-2">
                                    <div class="form-group">
                                        <label for="transaction_type" class="control-label">
                                            {{ __('Transaction type') }}
                                        </label>
                                        <select
                                                id="transaction_type"
                                                class="form-select"
                                                v-model="form.transaction_type"
                                                @change="transactionTypeChanged($event)"
                                        >
                                            <option
                                                    v-for="item in transactionTypes"
                                                    :key="item.name"
                                                    :value="item.name"
                                            >
                                                {{ item.name }}
                                            </option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-2">
                                    <div class="form-group">
                                        <label for="date" class="control-label">
                                            {{ __('Date') }}
                                        </label>
                                        <Datepicker
                                                id="date"
                                                v-model="form.date"
                                                :disabled="form.schedule"
                                                autoApply
                                                format="yyyy. MM. dd."
                                                :enableTimePicker="false"
                                                utc="preserve"
                                        ></Datepicker>
                                    </div>
                                </div>
                            </div>
                            <div class="row align-items-end">
                                <div class="col-md-6 mb-2">
                                    <div class="form-group">
                                        <label for="account" class="control-label">
                                            {{ __('Account') }}
                                        </label>
                                        <select
                                                class="form-select"
                                                id="account"
                                                v-model="form.config.account_id">
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-2">
                                    <div class="form-group">
                                        <label for="investment" class="control-label">
                                            {{ __('Investment') }}
                                        </label>
                                        <select
                                                class="form-control"
                                                id="investment"
                                                v-model="form.config.investment_id">
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-3 mb-2 align-self-end">
                                    <div class="form-check" v-if="!simplified">
                                        <input
                                                id="entry_type_schedule"
                                                class="form-check-input"
                                                :disabled="form.reconciled || action === 'replace'"
                                                type="checkbox"
                                                value="1"
                                                v-model="form.schedule"
                                        >
                                        <label
                                                for="entry_type_schedule"
                                                class="form-check-label"
                                                :title="(action === 'replace' ? __('You cannot change schedule settings for this type of action') : '')"
                                                :data-toggle="(action === 'replace' ? 'tooltip' : '')"
                                        >
                                            {{ __('Scheduled') }}
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-3 mb-2 align-self-end">
                                    <div class="form-check" v-if="!simplified">
                                        <label>
                                            <input
                                                    :disabled="form.schedule || form.budget"
                                                    type="checkbox"
                                                    value="1"
                                                    v-model="form.reconciled"
                                            >
                                            {{ __('Reconciled') }}
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-2">
                                    <div class="form-group">
                                        <label for="comment" class="control-label">
                                            {{ __('Comment') }}
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
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card mb-3">
                        <div class="card-header">
                            <div class="card-title">
                                {{ __('Ammounts') }}
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6 mb-2">
                                    <div class="form-group">
                                        <label for="transaction_quantity" class="control-label">
                                            {{ __('Quantity') }}
                                        </label>
                                        <MathInput
                                                class="form-control"
                                                id="transaction_quantity"
                                                v-model="form.config.quantity"
                                                :disabled="!transactionTypeSettings.quantity"
                                        ></MathInput>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-2">
                                    <div class="form-group">
                                        <label for="transaction_price" class="control-label">
                                            {{ __('Price') }}
                                        </label>
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
                                <div class="col-md-6 mb-2">
                                    <div class="form-group">
                                        <label for="transaction_commission" class="control-label">
                                            {{ __('Commission') }}
                                        </label>
                                        <MathInput
                                                class="form-control"
                                                id="transaction_commission"
                                                v-model="form.config.commission"
                                        ></MathInput>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-2">
                                    <div class="form-group">
                                        <label for="transaction_tax" class="control-label">
                                            {{ __('Tax') }}
                                        </label>
                                        <MathInput
                                                class="form-control"
                                                id="transaction_tax"
                                                v-model="form.config.tax"
                                        ></MathInput>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-2">
                                    <div class="form-group">
                                        <label for="transaction_dividend" class="control-label">
                                            {{ __('Dividend') }}
                                        </label>
                                        <MathInput
                                                class="form-control"
                                                id="transaction_dividend"
                                                v-model="form.config.dividend"
                                                :disabled="!transactionTypeSettings.dividend"
                                        ></MathInput>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-2">
                                    <div class="form-group">
                                        <label class="control-label">
                                            {{ __('Total') }}
                                            <span v-if="currency" dusk="label-currency">({{ currency }})</span>
                                        </label>
                                        <input type="text" :value="total" class="form-control" disabled="disabled">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <transaction-schedule
                    v-if="form.schedule"
                    :isSchedule="form.schedule"
                    :isBudget="false"
                    :schedule="form.schedule_config"
                    :form="form"
            ></transaction-schedule>

            <transaction-schedule
                    v-if="form.schedule && action === 'replace'"
                    :withCheckbox="true"
                    :title="__('Update base schedule')"
                    :allowCustomization="false"
                    ref="scheduleOriginal"
                    :isSchedule="form.schedule"
                    :isBudget="false"
                    :schedule="form.original_schedule_config"
                    :form="form"
            ></transaction-schedule>

            <div class="card mb-3">
                <div class="card-body">
                    <div class="row">
                        <div class="d-none d-md-block col-md-10">
                            <div v-show="!fromModal" dusk="action-after-save-desktop-button-group">
                                <div class="btn-group">
                                    <button
                                            class="btn btn-secondary"
                                            disabled
                                    >
                                        {{ __('Action after saving') }}
                                    </button>
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
                        </div>
                        <div class="col-12 d-block d-md-none">
                            <div v-show="!fromModal">
                                <label class="control-label block-label">
                                    {{ __('Action after saving') }}
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
                        <div class="col-12 col-md-2 text-end align-self-end">
                            <button
                                    class="btn btn-sm btn-default"
                                    @click="onCancel"
                                    type="button"
                            >
                                {{ __('Cancel') }}
                            </button>
                            <Button
                                    class="btn btn-primary ms-2"
                                    :disabled="form.busy"
                                    :form="form"
                                    id="transactionFormInvestment-Save"
                            >
                                {{ __('Save') }}
                            </Button>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</template>

<script>
require('select2');
$.fn.select2.amd.define(
    'select2/i18n/' + window.YAFFA.language,
    [],
    require("select2/src/js/select2/i18n/" + window.YAFFA.language)
);

import MathInput from './MathInput.vue'

import Form from 'vform'
import {Button, AlertErrors} from 'vform/src/components/bootstrap5'

import Datepicker from '@vuepic/vue-datepicker';
import '@vuepic/vue-datepicker/dist/main.css'

import TransactionSchedule from './TransactionSchedule.vue'

import {getCurrencySymbol, processTransaction, todayInUTC, toIsoDateString} from "../helpers";

export default {
    components: {
        TransactionSchedule,
        MathInput,
        Datepicker,
        Button, AlertErrors,
    },

    props: {
        action: String,
        initialCallback: {
            type: String,
            default: 'create',
        },
        transaction: Object,
        simplified: {
            // If true, no schedule option is shown
            type: Boolean,
            default: false,
        },
        fromModal: {
            // If true, the form is shown in a modal, which controls a few parts of the form
            // - notification behavior
            // - availability of callback options
            type: Boolean,
            default: false,
        },
    },

    data() {
        let data = {};

        // Main form data
        data.form = new Form({
            fromModal: this.fromModal,
            transaction_type: 'Buy',
            config_type: 'transaction_detail_investment',
            date: toIsoDateString(),
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

        data.csrfToken = window.csrfToken;
        data.callback = this.initialCallback;

        // Possible callback options
        data.callbackOptions = [
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
                value: 'show',
                label: __('Show this transaction'),
                enabled: true,
            },
            {
                value: 'returnToPrimaryAccount',
                label: __('Return to selected account'),
                enabled: true,
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
        ]

        return data;
    },

    computed: {
        total() {
            return (this.form.config.quantity || 0) * (this.form.config.price || 0)
                + (this.form.config.dividend || 0)
                - (this.form.config.commission || 0)
                - (this.form.config.tax || 0);
        },

        transactionTypeSettings() {
            return this.transactionTypes.find(item => item.name === this.form.transaction_type) || {};
        },

        currency() {
            return this.account_currency || this.investment_currency;
        },

        activeCallbackOptions() {
            return this.callbackOptions.filter(option => option.enabled);
        },
    },

    created() {
        // Copy values of existing transaction into component form data
        this.initializeTransaction();

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
                name: 'Interest yield',
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
                selectOnClose: false,
                placeholder: __("Select account"),
                allowClear: true,
                theme: 'bootstrap-5',
            })
            .on('select2:select', function (e) {
                const event = new Event("change", {bubbles: true, cancelable: true});
                e.target.dispatchEvent(event);

                $.ajax({
                    url: '/api/assets/account/' + e.params.data.id,
                    data: {
                        _token: $vm.csrfToken,
                    },
                })
                    .done(data => {
                        $vm.account_currency = getCurrencySymbol(window.YAFFA.locale, data.config.currency.iso_code);
                        $vm.account_currency_id = data.config.currency.id;
                    });
            })
            .on('select2:unselect', function () {
                $vm.account_id = null;
                $vm.account_currency = null;
                $vm.account_currency_id = null;
            });

        // Load default value for account
        this.getDefaultAccountDetails(this.form.config.account_id);

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
            selectOnClose: false,
            placeholder: __("Select investment"),
            allowClear: true,
            theme: 'bootstrap-5',
        })
        .on('select2:select', function (e) {
            const event = new Event("change", {bubbles: true, cancelable: true});
            e.target.dispatchEvent(event);

            $.ajax({
                url: route('investment.getDetails', {'investment': e.params.data.id}),
                data: {
                    _token: $vm.csrfToken,
                },
            })
                .done(function (data) {
                    $vm.investment_currency_id = data.currency.id;
                    $vm.investment_currency = getCurrencySymbol(window.YAFFA.locale, data.currency.iso_code);
                });
        }).on('select2:unselect', function () {
            $vm.investment_id = null;
            $vm.investment_currency_id = null;
            $vm.investment_currency = null;
        });

        // Load default value for investment
        this.getDefaultInvestmentDetails(this.form.config.investment_id);

        // Initial sync between schedules, if applicable
        this.syncScheduleStartDate(this.form.schedule_config.start_date);
    },

    methods: {
        getDefaultAccountDetails(account_id) {
            if (!account_id) {
                return;
            }
            const $vm = this;

            $.ajax({
                url: '/api/assets/account/' + this.form.config.account_id,
                data: {
                    _token: $vm.csrfToken,
                },
            })
                .done(data => {
                    // Create the option and append to Select2
                    $("#account")
                        .append(new Option(data.name, data.id, true, true))
                        .trigger('change')
                        .trigger({
                            type: 'select2:select',
                            params: {
                                data: data
                            }
                        });
                });
        },

        getDefaultInvestmentDetails(investment_id) {
            if (!investment_id) {
                return;
            }
            const $vm = this;

            $.ajax({
                url: route('investment.getDetails', {'investment': investment_id}),
                data: {
                    _token: $vm.csrfToken,
                },
            })
                .done(function (data) {
                    // Create the option and append to Select2
                    $("#investment")
                        .append(new Option(data.name, data.id, true, true))
                        .trigger('change')
                        .trigger({
                            type: 'select2:select',
                            params: {
                                data: data
                            }
                        });
                });
        },

        copyDateObject(date) {
            if (date instanceof Date) {
                return date;
            }
            if (date) {
                return new Date(date);
            }

            return null;
        },

        initializeTransaction() {
            if (this.transaction && Object.keys(this.transaction).length > 0) {
                // Populate form data with already known values
                this.form.id = this.transaction.id
                this.form.transaction_type = this.transaction.transaction_type?.name;

                // Populate date from source transaction, and ensure that it's a Date object
                this.form.date = this.copyDateObject(this.transaction.date);

                this.form.comment = this.transaction.comment;
                this.form.schedule = this.transaction.schedule;
                this.form.budget = this.transaction.budget;
                this.form.reconciled = this.transaction.reconciled;

                // Copy configuration
                this.form.config.quantity = this.transaction.config?.quantity;
                this.form.config.price = this.transaction.config?.price;
                this.form.config.commission = this.transaction.config?.commission;
                this.form.config.tax = this.transaction.config?.tax;
                this.form.config.dividend = this.transaction.config?.dividend;

                this.form.config.account_id = this.transaction.config.account_id;
                this.form.config.investment_id = this.transaction.config.investment_id;

                // Copy schedule config
                // TODO: date conversion should take place here, or elsewehere?
                if (this.transaction.transaction_schedule) {
                    this.form.schedule_config.frequency = this.transaction.transaction_schedule.frequency;
                    this.form.schedule_config.count = this.transaction.transaction_schedule.count;
                    this.form.schedule_config.interval = this.transaction.transaction_schedule.interval;

                    this.form.schedule_config.start_date = this.copyDateObject(this.transaction.transaction_schedule.start_date);
                    this.form.schedule_config.next_date = this.copyDateObject(this.transaction.transaction_schedule.next_date);
                    this.form.schedule_config.end_date = this.copyDateObject(this.transaction.transaction_schedule.end_date);

                    this.form.schedule_config.inflation = this.transaction.transaction_schedule.inflation;
                }

                // If creating a schedule clone, we need to duplicate the schedule config, and make some adjustments
                if (this.action === 'replace') {
                    this.form.original_schedule_config = {};
                    this.form.original_schedule_config.frequency = this.form.schedule_config.frequency;
                    this.form.original_schedule_config.count = this.form.schedule_config.count;
                    this.form.original_schedule_config.interval = this.form.schedule_config.interval;
                    this.form.original_schedule_config.inflation = this.form.schedule_config.inflation;
                    this.form.original_schedule_config.start_date = this.copyDateObject(this.form.schedule_config.start_date);

                    // Reset next date of original schedule config to set it ended
                    this.form.original_schedule_config.next_date = undefined;

                    // Set new schedule start date to today
                    this.form.schedule_config.start_date = todayInUTC();

                    // If this is a schedule, then set the new next date to today
                    if (this.form.schedule) {
                        this.form.schedule_config.next_date = todayInUTC();
                    }

                    // Set original schedule end date to today - 1 day
                    this.form.original_schedule_config.end_date = new Date(todayInUTC().getTime() - 24 * 60 * 60 * 1000);
                }
            }

            // Set form action
            this.form.action = this.action;
        },

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
        loadCallbackUrl(transactionId) {
            if (this.callback === 'returnToDashboard') {
                location.href = window.route('home');
                return;
            }

            if (this.callback === 'new') {
                location.href = window.route('transaction.create', {type: 'investment'});
                return;
            }

            if (this.callback === 'clone') {
                location.href = window.route('transaction.open', {transaction: transactionId, action: 'clone'});
                return;
            }

            if (this.callback === 'returnToPrimaryAccount') {
                location.href = window.route('account.history', {account: this.form.config.account_id});
                return;
            }

            if (this.callback === 'returnToSecondaryAccount') {
                location.href = window.route('account.history', {account: this.form.config.account_id});
                return;
            }

            // Default, return back
            if (document.referrer) {
                location.href = document.referrer;
            } else {
                history.back();
            }
        },

        onCancel() {
            if (confirm(__('Are you sure you want to discard any changes?'))) {
                this.$emit('cancel');
            }
            return false;
        },

        onSubmit() {
            // Editing an existing transaction needs PATCH method
            if (this.action === 'edit') {
                this.form.patch(
                    window.route('api.transactions.updateInvestment', {transaction: this.form.id}),
                    this.form
                )
                    .then((response) => {
                        this.$emit(
                            'success',
                            processTransaction(response.data.transaction),
                            {
                                callback: this.callback,
                            },
                        );
                    });
                return;
            }

            // Any type of new transaction needs POST method
            this.form.post(
                window.route('api.transactions.storeInvestment'),
                this.form
            )
                .then((response) => {
                    this.$emit(
                        'success',
                        processTransaction(response.data.transaction),
                        {
                            callback: this.callback,
                        }
                    );
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
            date.setDate(date.getDate() - 1);
            this.form.original_schedule_config.end_date = toIsoDateString(date);
        },
    },

    watch: {
        // On change of new schedule start date, adjust original schedule end date to previous day
        "form.schedule_config.start_date": function (newDate) {
            this.syncScheduleStartDate(newDate);
        },

        transaction(transaction) {
            // TODO: consider using form.update()
            this.form.reset();

            // Copy values of existing transaction into component form data
            this.initializeTransaction();

            // Load default value for accounts
            this.getDefaultAccountDetails(transaction.config.account_id);
            this.getDefaultInvestmentDetails(transaction.config.investment_id);
        }
    }
}

// Initialize tooltips
// TODO: can this be part of Vue init?
$(document).ready(function () {
    $('[data-toggle="tooltip"]').tooltip()
});
</script>

<style scoped>
@media (min-width: 576px) {
    .block-label {
        display: block;
    }
}

@media (max-width: 575.98px) {
    .block-label {
        margin-right: 10px;
    }
}
</style>
