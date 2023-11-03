<template>
    <div id="transactionFormStandard">
        <AlertErrors :form="form" message="There were some problems with your input."/>

        <payee-form
                action="new"
                :payee="{}"
                id="newPayeeModal"
                @payeeSelected="setPayee"
                v-if="!fromModal"
        ></payee-form>

        <!-- form start -->
        <form
                accept-charset="UTF-8"
                @submit.prevent="onSubmit"
                autocomplete="off"
        >
            <div class="row">
                <div class="col-md-4">
                    <div class="card mb-3">
                        <div class="card-header">
                            <div class="card-title">
                                {{ __('Properties') }}
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="row mb-3">
                                <div class="col-12 col-sm-6">
                                    <label class="block-label">
                                        {{ __('Type') }}
                                    </label>
                                    <div class="btn-group">
                                        <button
                                                class="btn btn-outline-primary"
                                                :class="{ active : form.transaction_type === 'withdrawal'}"
                                                dusk="transaction-type-withdrawal"
                                                type="button"
                                                value="withdrawal"
                                                @click="changeTransactionType"
                                        >
                                            {{ __('Withdrawal') }}
                                        </button>
                                        <button
                                                class="btn btn-outline-primary"
                                                :class="{ active : form.transaction_type === 'deposit'}"
                                                dusk="transaction-type-deposit"
                                                type="button"
                                                value="deposit"
                                                @click="changeTransactionType"
                                        >
                                            {{ __('Deposit') }}
                                        </button>
                                        <button
                                                class="btn btn-outline-primary"
                                                :class="{ active : form.transaction_type === 'transfer'}"
                                                dusk="transaction-type-transfer"
                                                type="button"
                                                value="transfer"
                                                @click="changeTransactionType"
                                                :disabled="form.budget"
                                        >
                                            {{ __('Transfer') }}
                                        </button>
                                    </div>
                                </div>
                                <div
                                        class="col-12 col-sm-6"
                                        :class="{ 'has-error' : form.errors.has('date')}"
                                >
                                    <label class="block-label" for="date">
                                        {{ __('Date') }}
                                    </label>
                                    <Datepicker
                                            id="date"
                                            v-model="form.date"
                                            :disabled="form.schedule || form.budget"
                                            autoApply
                                            format="yyyy. MM. dd."
                                            :enableTimePicker="false"
                                            utc="preserve"
                                    ></Datepicker>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div
                                        class="col-12 col-sm-6"
                                        :class="form.errors.has('config.account_from_id') ? 'has-error' : ''"
                                >
                                    <label class="control-label block-label">
                                        {{ accountFromFieldLabel }}
                                    </label>
                                    <div class="input-group" id="account_from_container">
                                        <select
                                                class="form-select"
                                                id="account_from"
                                                v-model="form.config.account_from_id">
                                        </select>
                                        <button
                                                class="btn btn-success"
                                                style="padding: 0.05rem 0.25rem;"
                                                :title="__('Add a new payee')"
                                                type="button"
                                                data-coreui-toggle="modal"
                                                data-coreui-target="#newPayeeModal"
                                                v-if="form.transaction_type === 'deposit' && !fromModal"
                                        ><span class="fa fa-fw fa-plus"></span></button>
                                    </div>
                                </div>

                                <div
                                        class="col-12 col-sm-6"
                                        :class="form.errors.has('config.account_to_id') ? 'has-error' : ''"
                                >
                                    <label class="control-label block-label">
                                        {{ accountToFieldLabel }}
                                    </label>
                                    <div class="input-group" id="account_to_container">
                                        <select
                                                class="form-select"
                                                id="account_to"
                                                v-model="form.config.account_to_id">
                                        </select>
                                        <button
                                                class="btn btn-success"
                                                style="padding: 0.05rem 0.25rem;"
                                                :title="__('Add a new payee')"
                                                type="button"
                                                data-coreui-toggle="modal"
                                                data-coreui-target="#newPayeeModal"
                                                v-if="form.transaction_type === 'withdrawal' && !fromModal"
                                        ><span class="fa fa-fw fa-plus"></span></button>
                                    </div>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div
                                        class="col-12"
                                        :class="form.errors.has('comment') ? 'has-error' : ''"
                                >
                                    <label for="comment" class="control-label block-label">
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

                            <div class="row mb-3">
                                <div class="col" v-if="!simplified">
                                    <label
                                            :title="(action === 'replace' ? __('You cannot change schedule settings for this type of action') : '')"
                                            :data-toggle="(action === 'replace' ? 'tooltip' : '')"
                                    >
                                        <input
                                                :disabled="form.reconciled || action === 'replace'"
                                                dusk="checkbox-transaction-schedule"
                                                type="checkbox"
                                                value="1"
                                                v-model="form.schedule"
                                        >
                                        {{ __('Scheduled') }}
                                    </label>
                                </div>
                                <div class="col" v-if="!simplified">
                                    <label
                                            :title="(action === 'replace' ? __('You cannot change schedule settings for this type of action') : '')"
                                            :data-toggle="(action === 'replace' ? 'tooltip' : '')"
                                    >
                                        <input
                                                :disabled="form.reconciled || form.transaction_type == 'transfer' || action === 'replace'"
                                                dusk="checkbox-transaction-budget"
                                                type="checkbox"
                                                value="1"
                                                v-model="form.budget"
                                        >
                                        {{ __('Budget') }}
                                    </label>
                                </div>
                                <div class="col">
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
                        </div>
                    </div>
                    <div class="card mb-3">
                        <div class="card-header">
                            <div class="card-title">
                                {{ __('Amounts') }}
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="row mb-3">
                                <div
                                        class="col-4"
                                        :class="form.errors.has('config.amount_from') ? 'has-error' : ''"
                                >
                                    <label for="transaction_amount_from" class="control-label">
                                        {{ ammountFromFieldLabel }}
                                        <span v-if="ammountFromCurrencyLabel" dusk="label-amountFrom-currency">
                                            ({{ ammountFromCurrencyLabel }})
                                        </span>
                                        <span v-if="form.budget && !ammountFromCurrencyLabel">
                                            ({{ getCurrencySymbol(locale, baseCurrency.iso_code) }})
                                            <span class="fa fa-info-circle text-primary"
                                                  :title="__('Budget is calculated using your base currency, unless you define an account with an other currency.')"
                                                  data-toggle="tooltip"
                                            ></span>
                                        </span>
                                    </label>
                                    <MathInput
                                            class="form-control"
                                            id="transaction_amount_from"
                                            v-model="form.config.amount_from"
                                    ></MathInput>
                                </div>
                                <div
                                        v-show="exchangeRatePresent"
                                        class="col-4"
                                        dusk="label-transaction-exchange-rate"
                                >
                                    <span class="block-label">
                                        {{ __('Exchange rate') }}
                                    </span>
                                    {{ exchangeRate }}
                                </div>
                                <div
                                        v-show="exchangeRatePresent"
                                        class="col-4"
                                        :class="form.errors.has('config.amount_to') ? 'has-error' : ''"
                                >
                                    <label for="transaction_amount_slave" class="control-label">
                                        {{ __('Amount to') }}
                                        <span v-if="to.account_currency"
                                              dusk="label-amountTo-currency">({{ to.account_currency }})</span>
                                    </label>
                                    <MathInput
                                            class="form-control"
                                            id="transaction_amount_to"
                                            v-model="form.config.amount_to"
                                    ></MathInput>
                                </div>
                            </div>
                            <dl class="row" v-if="!transactionTypeIsTransfer">
                                <dt class="col-sm-8">
                                    {{ __('Total amount') }}:
                                </dt>
                                <dd class="col-sm-4">
                                    {{ form.config.amount_from || 0 }}
                                    <span v-if="ammountFromCurrencyLabel">{{ ammountFromCurrencyLabel }}</span>
                                </dd>
                                <dt class="col-sm-8">
                                    {{ __('Total allocated') }}:
                                </dt>
                                <dd class="col-sm-4">
                                    {{ allocatedAmount }}
                                    <span v-if="ammountFromCurrencyLabel">{{ ammountFromCurrencyLabel }}</span>
                                </dd>
                                <dt class="col-sm-8" v-show="payeeCategory.id">
                                    {{ __('Remaining amount to') }}
                                    <span class="notbold"><br>{{ payeeCategory.text }}</span>:
                                </dt>
                                <dd class="col-sm-4" v-show="payeeCategory.id">
                                    {{ remainingAmountToPayeeDefault }}
                                    <span v-if="ammountFromCurrencyLabel">{{ ammountFromCurrencyLabel }}</span>
                                </dd>
                                <dt class="col-sm-8" v-show="!payeeCategory.id">
                                    {{ __('Not allocated') }}:
                                </dt>
                                <dd class="col-sm-4" v-show="!payeeCategory.id">
                                    {{ remainingAmountNotAllocated }}
                                    <span v-if="ammountFromCurrencyLabel">{{ ammountFromCurrencyLabel }}</span>
                                </dd>
                            </dl>
                        </div>
                    </div>

                    <transaction-schedule
                            v-if="form.schedule || form.budget"
                            :isSchedule="form.schedule"
                            :isBudget="form.budget"
                            :schedule="form.schedule_config"
                            :form="form"
                            key="current"
                    ></transaction-schedule>

                    <transaction-schedule
                            v-if="(form.schedule || form.budget) && action === 'replace'"
                            :withCheckbox="true"
                            title="Update base schedule"
                            :allowCustomization="false"

                            :isSchedule="form.schedule"
                            :isBudget="form.budget"
                            :schedule="form.original_schedule_config"
                            :form="form"
                            ref="scheduleOriginal"
                            key="original"
                    ></transaction-schedule>

                </div>
                <div class="col-md-8">
                    <transaction-item-container
                            @addTransactionItem="addTransactionItem"
                            :transactionItems="form.items"
                            :currency="from.account_currency"
                            :payee="payeeId"
                            :remainingAmount="remainingAmountNotAllocated || remainingAmountToPayeeDefault || 0"
                            :enabled="!transactionTypeIsTransfer"
                    ></transaction-item-container>
                </div>
            </div>

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
                                    id="transactionFormStandard-Save"
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
import {todayInUTC, getCurrencySymbol, processTransaction} from "../helpers";

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

import TransactionItemContainer from './TransactionItemContainer.vue'
import TransactionSchedule from './TransactionSchedule.vue'

import PayeeForm from './../components/PayeeForm.vue'

export default {
    components: {
        TransactionItemContainer, TransactionSchedule, PayeeForm,
        Datepicker,
        Button, AlertErrors,
        MathInput,
    },

    props: {
        action: String,
        initialCallback: {
            type: String,
            default: 'create',
        },
        transaction: Object,
        simplified: {
            // If true, no schedule or budget option is shown
            type: Boolean,
            default: false,
        },
        fromModal: {
            // If true, the form is shown in a modal, which controls a few parts of the form
            // - notification behavior
            // - availability of new payee button
            // - availability of callback options
            type: Boolean,
            default: false,
        },
        sourceId: {
            type: Number,
            default: null,
        }
    },

    data() {
        let data = {};

        // Storing all data and references about source account or payee
        // Set as withdrawal by default
        data.from = {
            account_currency: null,
        };

        // Storing all data and references about target account or payee
        // Set as withdrawal by default
        data.to = {
            account_currency: null,
        };

        // Additional data about payee, if present
        data.payeeCategory = {
            id: null,
            text: null,
        };

        // Main form data
        data.form = new Form({
            fromModal: this.fromModal,
            transaction_type: 'withdrawal',
            config_type: 'transaction_detail_standard',
            date: todayInUTC(),
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
            source_id: null,
        });

        // Id counter for items
        data.itemCounter = 0;

        data.callback = this.initialCallback;

        // Various callback options
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
                value: 'returnToSecondaryAccount',
                label: __('Return to target account'),
                enabled: false,
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
        ];

        return data;
    },

    computed: {
        // Account TO and FROM labels based on transaction type
        accountFromFieldLabel() {
            return (['withdrawal', 'transfer'].includes(this.form.transaction_type) ? __('Account from') : __('Payee'))
        },

        accountToFieldLabel() {
            return (['deposit', 'transfer'].includes(this.form.transaction_type) ? __('Account to') : __('Payee'))
        },

        // Return only those callback options, which are available based on the current form state
        activeCallbackOptions() {
            return this.callbackOptions.filter(option => option.enabled);
        },

        // Amount from label is different for transfer
        ammountFromFieldLabel() {
            return (this.exchangeRatePresent ? __('Amount from') : __('Amount'))
        },

        // Amount from currency is dependent on many other data
        ammountFromCurrencyLabel() {
            if (['withdrawal', 'transfer'].includes(this.form.transaction_type)) {
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
                .reduce((amount, currentValue) => amount + currentValue, 0);
        },

        // Provide the base currency from the global scope for the template
        baseCurrency() {
            return window.YAFFA.baseCurrency;
        },
        // Provide the locale from the global scope for the template
        locale() {
            return window.YAFFA.locale;
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
            return this.from.account_currency && this.to.account_currency && this.from.account_currency !== this.to.account_currency;
        },

        exchangeRate() {
            const from = this.form.config.amount_from;
            const to = this.form.config.amount_to;

            if (from && to) {
                return (Number(to) / Number(from)).toFixed(4);
            }

            return 0;
        },

        transactionTypeIsTransfer() {
            return this.form.transaction_type === 'transfer';
        },
    },

    created() {
        // Copy values of existing transaction into component form data
        this.initializeTransaction();
    },

    mounted() {
        let $vm = this;

        // Account FROM dropdown functionality
        $("#account_from")
            .select2(this.getAccountSelectConfig('from'))
            .on('select2:select', function (e) {
                const event = new Event("change", {bubbles: true, cancelable: true});
                e.target.dispatchEvent(event);

                if ($vm.getAccountType('from') === 'account') {
                    $.ajax({
                        url: '/api/assets/account/' + e.params.data.id,
                        data: {
                            _token: $vm.csrfToken,
                        }
                    })
                        .done(data => {
                            $vm.from.account_currency = getCurrencySymbol(window.YAFFA.locale, data.config.currency.iso_code);
                        });
                } else {
                    $.ajax({
                        url: '/api/assets/payee/' + e.params.data.id,
                        data: {
                            _token: $vm.csrfToken,
                        }
                    })
                        .done(function (data) {
                            if (data.config.category) {
                                $vm.payeeCategory.id = data.config.category.id;
                                $vm.payeeCategory.text = data.config.category.full_name;
                            }
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
        this.getDefaultAccountDetails(this.transaction?.config?.account_from_id, 'from');

        // Account TO dropdown functionality
        $("#account_to")
            .select2(this.getAccountSelectConfig('to'))
            .on('select2:select', function (e) {
                const event = new Event("change", {bubbles: true, cancelable: true});
                e.target.dispatchEvent(event);

                if ($vm.getAccountType('to') === 'account') {
                    $.ajax({
                        url: '/api/assets/account/' + e.params.data.id,
                        data: {
                            _token: $vm.csrfToken,
                        }
                    })
                        .done(data => {
                            $vm.to.account_currency = getCurrencySymbol(window.YAFFA.locale, data.config.currency.iso_code);
                        });
                } else if ($vm.getAccountType('to') === 'payee') {
                    $.ajax({
                        url: '/api/assets/payee/' + e.params.data.id,
                        data: {
                            _token: $vm.csrfToken,
                        }
                    })
                        .done(function (data) {
                            if (data.config.category) {
                                $vm.payeeCategory.id = data.config.category.id;
                                $vm.payeeCategory.text = data.config.category.full_name;
                            }
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
        this.getDefaultAccountDetails(this.transaction?.config?.account_to_id, 'to');

        // Initial sync between schedules, if applicable
        this.syncScheduleStartDate(this.form.schedule_config.start_date);
    },

    methods: {
        getCurrencySymbol,
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
                this.form.config.amount_from = this.transaction.config.amount_from;
                this.form.config.amount_to = this.transaction.config.amount_to;
                this.form.config.account_from_id = this.transaction.config.account_from_id;
                this.form.config.account_to_id = this.transaction.config.account_to_id;

                // Copy items, and ensure that amount is number
                if (this.transaction.transaction_items?.length > 0) {
                    this.transaction.transaction_items
                        .map((item) => {
                            item.id = this.itemCounter++;
                            item.amount = Number(item.amount);
                            return item;
                        })
                        .forEach(item => this.form.items.push(item));
                }

                // Copy schedule config
                // TODO: date conversion should take place here, or elsewehere?
                if (this.transaction.transaction_schedule) {
                    this.form.schedule_config.frequency = this.transaction.transaction_schedule.frequency;
                    this.form.schedule_config.count = this.transaction.transaction_schedule.count;
                    this.form.schedule_config.interval = this.transaction.transaction_schedule.interval;

                    this.form.schedule_config.start_date = this.copyDateObject(this.transaction.transaction_schedule.start_date);
                    this.form.schedule_config.next_date = this.copyDateObject(this.transaction.transaction_schedule.next_date);
                    this.form.schedule_config.automatic_recording = this.transaction.transaction_schedule.automatic_recording;
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
                    this.form.original_schedule_config.automatic_recording = this.form.schedule_config.automatic_recording;

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

            // Assign any source ID passed to the form. Currently, this can be a received mail ID
            this.form.source_id = this.sourceId;

            // Set form action
            this.form.action = this.action;
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

        changeTransactionType: function (event) {
            // Get new type from event
            const newState = event.currentTarget.getAttribute('value');

            // Get existing type from form
            const oldState = this.form.transaction_type;

            // Ignore event if no actual change has happened
            if (newState === oldState) {
                return false;
            }

            // Confirm transaction type change with user
            if (!confirm(__("Are you sure, you want to change the transaction type? Some data might get lost."))) {
                event.currentTarget.blur();
                return false;
            }

            // Proceed with component update
            this.onChangeTransactionType(newState, false);
        },

        /**
         * @param {string} newState
         * @param {boolean} forceUpdate
         */
        onChangeTransactionType(newState, forceUpdate) {
            const oldTypeFrom = this.getAccountType('from');
            const oldTypeTo = this.getAccountType('to');

            this.form.transaction_type = newState;

            // Reassign account FROM functionality, if changed
            if (oldTypeFrom !== this.getAccountType('from') || forceUpdate) {
                // Account (currency) is always reset
                this.resetAccount('from');
                // Payee data is reset only if new type is payee
                if (this.getAccountType('from') !== 'account') {
                    this.resetPayee();
                }

                $("#account_from")
                    .val(null).trigger('change')
                    .select2('destroy')
                    .select2(this.getAccountSelectConfig('from'));
            }

            // Reassign account TO functionality, if changed
            if (oldTypeTo !== this.getAccountType('to') || forceUpdate) {
                this.resetAccount('to');
                if (this.getAccountType('to') !== 'account') {
                    this.resetPayee();
                }

                $("#account_to")
                    .val(null).trigger('change')
                    .select2('destroy')
                    .select2(this.getAccountSelectConfig('to'));
            }

            // Remove all items, if transaction type is transfer
            if (this.form.transaction_type === 'transfer') {
                this.form.items = [];
            }

            // Update callback options
            const foundCallbackIndex = this.callbackOptions.findIndex(x => x.value === 'returnToSecondaryAccount');
            this.callbackOptions[foundCallbackIndex]['enabled'] = (newState === 'transfer')

            // Ensure, that selected item is enabled. Otherwise, set to first enabled option
            const selectedCallbackIndex = this.callbackOptions.findIndex(x => x.value === this.callback);
            if (!this.callbackOptions[selectedCallbackIndex].enabled) {
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
                return type === 'from' ? 'account' : 'payee';
            }
            if (this.form.transaction_type === 'deposit') {
                return type === 'from' ? 'payee' : 'account';
            }

            // Transfer
            return 'account';
        },

        // Get url to payee or account list, based on source or target type
        getAccountApiUrl(type) {
            const accountUrl = '/api/assets/account';
            const payeeUrl = '/api/assets/payee';

            return this.getAccountType(type) === 'account' ? accountUrl : payeeUrl;
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
            if (this.getAccountType(type) === 'account') {
                return __('Select account');
            }
            return __('Select payee');
        },

        getAccountSelectConfig(type) {
            let $vm = this;
            let otherType = (type === 'from' ? 'to' : 'from');

            return {
                theme: 'bootstrap-5',
                language: window.YAFFA.language,
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
                            account_entity_id: $vm.accountId,
                        };
                    },
                    processResults: function (data) {
                        // Exclude account that is selected in other account select
                        let otherAccountId = $vm.form.config['account_' + otherType + '_id'];
                        if (otherAccountId) {
                            data = data.filter(item => item.id != otherAccountId);
                        }

                        return {
                            results: data.map(function (account) {
                                return {
                                    id: account.id,
                                    text: account.name,
                                }
                            }),
                        };
                    },
                    cache: true
                },
                selectOnClose: false,
                // Set placeholder based on type parameter and transaction type
                placeholder: this.getPlaceholder(type),
                allowClear: true,
                width: 'resolve',
                // Component should not be aware where it is used, but we need to hint Select2
                dropdownParent: $(document.getElementById("modal-transaction-form-standard") || document.querySelector('body'))
            };
        },

        getDefaultAccountDetails(account_entity_id, type) {
            if (!account_entity_id) {
                return;
            }

            if (!['account', 'payee'].includes(this.getAccountType(type))) {
                return;
            }

            const $vm = this;
            const selector = '#account_' + type;

            $.ajax({
                url: '/api/assets/' + this.getAccountType(type) + '/' + account_entity_id,
                data: {
                    _token: $vm.csrfToken,
                }
            })
                .done(data => {
                    // Create the option and append to Select2
                    $vm.addNewItemToSelect(selector, data.id, data.name);
                });
        },

        onCancel() {
            if (confirm(__('Are you sure you want to discard any changes?'))) {
                this.$emit('cancel');
            }
            return false;
        },

        onSubmit() {
            // Some preparation before submitting the form

            // Adjust the "amount to" value. It needs to match the "amount from" value, if the currencies are the same,
            // or if only one of the values is set
            if (this.from.account_currency === this.to.account_currency || !this.from.account_currency || !this.to.account_currency) {
                this.form.config.amount_to = this.form.config.amount_from;
            }

            // Editing an existing transaction needs PATCH method
            if (this.action === 'edit') {
                this.form.patch(
                    window.route('api.transactions.updateStandard', {transaction: this.form.id}),
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
                window.route('api.transactions.storeStandard'),
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

        setPayee(payee) {
            // Determine which of the accounts need update
            if (!['withdrawal', 'deposit'].includes(this.form.transaction_type)) {
                return;
            }

            const accountSelector = (this.form.transaction_type === 'withdrawal' ? '#account_to' : '#account_from');

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
            if (!newDate || !this.form.original_schedule_config || !this.$refs.scheduleOriginal || this.$refs.scheduleOriginal.allowCustomization) {
                return;
            }
            let date = this.copyDateObject(newDate);
            this.form.original_schedule_config.end_date = new Date(date.getTime() - 24 * 60 * 60 * 1000);
        },
    },

    watch: {
        remainingAmountToPayeeDefault(newAmount) {
            this.form.remaining_payee_default_amount = newAmount;
        },

        payeeDefaultCategory(newId) {
            this.form.remaining_payee_default_category_id = newId;
        },

        // On change of new schedule start date, adjust original schedule end date to previous day
        "form.schedule_config.start_date": function (newDate) {
            this.syncScheduleStartDate(newDate);
        },

        transaction(transaction) {
            // TODO: consider using form.update()
            this.form.reset();

            // Copy values of existing transaction into component form data
            this.initializeTransaction();

            // Ensure that new transaction type is set
            // TODO: should this be part of initializeTransaction()?
            this.onChangeTransactionType(transaction.transaction_type.name, true);

            // Load default value for accounts
            this.getDefaultAccountDetails(transaction.config.account_from_id, 'from');
            this.getDefaultAccountDetails(transaction.config.account_to_id, 'to');
        }
    }
}

// Initialize tooltips
// TODO: how to better support dynamic icons?
const tooltipTriggerList = document.querySelectorAll('[data-coreui-toggle="tooltip"]');
Array.from(tooltipTriggerList).map(tooltipTriggerEl => new coreui.Tooltip(tooltipTriggerEl));
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
