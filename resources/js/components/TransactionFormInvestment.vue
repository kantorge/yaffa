<template>
  <div id="transactionFormInvestment">
    <AlertErrors
      :form="form"
      :message="__('There were some problems with your input.')"
    />

    <form accept-charset="UTF-8" @submit.prevent="onSubmit" autocomplete="off">
      <div class="row">
        <div class="col-md-4">
          <div class="card mb-3">
            <div class="card-header d-flex justify-content-between">
              <div class="card-title">
                {{ __('Settings') }}
              </div>
              <span
                class="fa fa-info-circle text-primary"
                data-coreui-toggle="tooltip"
                data-coreui-placement="right"
                :title="
                  __(
                    'These settings cannot be changed after saving the transaction.'
                  )
                "
              ></span>
            </div>
            <div class="card-body">
              <div class="row">
                <div class="col-md-8 mb-2">
                  <div class="form-group">
                    <label for="transaction_type" class="form-label">
                      {{ __('Transaction type') }}
                    </label>
                    <select
                      id="transaction_type"
                      class="form-select"
                      v-model="form.transaction_type"
                      :disabled="!isBaseSettingsEditsAllowed"
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
                <div
                  class="col d-flex justify-content-between gap-2 mb-0"
                  v-if="!simplified"
                >
                  <input
                    class="btn-check"
                    :disabled="form.reconciled || !isBaseSettingsEditsAllowed"
                    id="checkbox-transaction-schedule"
                    type="checkbox"
                    autocomplete="off"
                    value="1"
                    v-model="form.schedule"
                  />
                  <label
                    class="btn btn-outline-dark w-100"
                    data-test="checkbox-transaction-schedule"
                    for="checkbox-transaction-schedule"
                    :title="
                      action === 'replace'
                        ? __(
                            'You cannot change schedule settings for this type of action'
                          )
                        : ''
                    "
                    :data-toggle="action === 'replace' ? 'tooltip' : ''"
                  >
                    <span class="fa-solid fa-arrows-rotate"></span><br />
                    {{ __('Scheduled') }}
                  </label>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="col-md-8">
          <div class="card mb-3">
            <div class="card-header">
              <div class="card-title">
                {{ __('Properties') }}
              </div>
            </div>
            <div class="card-body">
              <div class="row">
                <div
                  class="col-6 col-sm-2 mb-3 mb-sm-0 d-flex justify-content-center"
                >
                  <input
                    class="btn-check"
                    :disabled="form.schedule"
                    id="checkbox-transaction-reconciled"
                    type="checkbox"
                    autocomplete="off"
                    value="1"
                    v-model="form.reconciled"
                  />
                  <label
                    class="btn btn-outline-success"
                    for="checkbox-transaction-reconciled"
                  >
                    <span class="fa fa-check"></span><br />
                    {{ __('Reconciled') }}
                  </label>
                </div>
                <div
                  class="col-6 col-sm-2 mb-3 mb-sm-0"
                  :class="{ 'has-error': form.errors.has('date') }"
                >
                  <label class="block-label" for="date">
                    {{ __('Date') }}
                  </label>
                  <DatePicker
                    :columns="2"
                    :initial-page="datePickerInitialPage"
                    :masks="{
                      L: 'YYYY-MM-DD',
                      modelValue: 'YYYY-MM-DD',
                    }"
                    mode="date"
                    :popover="{
                      visibility: 'click',
                      showDelay: 0,
                      hideDelay: 0,
                    }"
                    v-model.string="form.date"
                  >
                    <template #default="{ inputValue, inputEvents }">
                      <input
                        class="form-control"
                        :disabled="form.schedule"
                        id="date"
                        :value="inputValue"
                        v-on="inputEvents"
                      />
                    </template>
                  </DatePicker>
                </div>
                <div
                  class="col-12 col-sm-8 mb-0"
                  :class="form.errors.has('comment') ? 'has-error' : ''"
                >
                  <label for="comment" class="block-label">
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
      <div class="row">
        <div class="col-md-4">
          <div class="card mb-3">
            <div class="card-header">
              <div class="card-title">
                {{ __('Details') }}
              </div>
            </div>
            <div class="card-body">
              <div class="row mb-3">
                <div class="col-12 mb-3">
                  <div class="form-group">
                    <label for="account" class="form-label">
                      {{ __('Account') }}
                    </label>
                    <select
                      class="form-select"
                      id="account"
                      v-model="form.config.account_id"
                      style="width: 100% !important"
                    ></select>
                  </div>
                </div>
                <div class="col-12 mb-2">
                  <div class="form-group">
                    <label for="investment" class="form-label">
                      {{ __('Investment') }}
                    </label>
                    <select
                      class="form-control"
                      id="investment"
                      v-model="form.config.investment_id"
                      style="width: 100% !important"
                    ></select>
                  </div>
                </div>
              </div>
              <dl class="row">
                <dt class="col-8">
                  {{ __('Total cashflow value') }}
                </dt>
                <dd class="col-4">
                  <span
                    class="me-1"
                    v-if="currency"
                    data-test="transaction-total-value"
                  >
                    {{ toFormattedCurrency(total, this.locale, currency) }}
                  </span>
                  <span v-else>
                    {{ total }}
                  </span>
                </dd>
              </dl>
            </div>
          </div>
        </div>
        <div class="col-md-8">
          <div class="card mb-3">
            <div class="card-header">
              <div class="card-title">
                {{ __('Details') }}
              </div>
            </div>
            <div class="card-body">
              <div class="row">
                <div class="col-md-6 mb-3">
                  <div class="form-group">
                    <label for="transaction_quantity" class="form-label">
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
                <div class="col-md-6 mb-3">
                  <div class="form-group">
                    <label for="transaction_price" class="form-label">
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
                <div class="col-md-6 mb-3">
                  <div class="form-group">
                    <label for="transaction_commission" class="form-label">
                      {{ __('Commission') }}
                    </label>
                    <MathInput
                      class="form-control"
                      id="transaction_commission"
                      v-model="form.config.commission"
                    ></MathInput>
                  </div>
                </div>
                <div class="col-md-6 mb-3">
                  <div class="form-group">
                    <label for="transaction_tax" class="form-label">
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
                <div class="col-md-6">
                  <div class="form-group">
                    <label for="transaction_dividend" class="form-label">
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
              <div
                v-show="!fromModal"
                data-test="action-after-save-desktop-button-group"
              >
                <div class="btn-group">
                  <button class="btn btn-secondary" disabled>
                    {{ __('Action after saving') }}
                  </button>
                  <button
                    v-for="item in activeCallbackOptions"
                    :key="item.id"
                    class="btn btn-outline-dark"
                    :class="{ active: callback === item.value }"
                    type="button"
                    :value="item.value"
                    @click="
                      callback = $event.currentTarget.getAttribute('value')
                    "
                  >
                    {{ item.label }}
                  </button>
                </div>
              </div>
            </div>
            <div class="col-12 d-block d-md-none">
              <div v-show="!fromModal">
                <label
                  class="form-label block-label"
                  for="callback-selector-mobile"
                >
                  {{ __('Action after saving') }}
                </label>
                <select
                  class="form-control"
                  v-model="callback"
                  id="callback-selector-mobile"
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
    require('select2/src/js/select2/i18n/' + window.YAFFA.language)
  );

  import MathInput from './MathInput.vue';

  import Form from 'vform';
  import { Button, AlertErrors } from 'vform/src/components/bootstrap5';

  import { DatePicker } from 'v-calendar';

  import TransactionSchedule from './TransactionSchedule.vue';

  import {
    toFormattedCurrency,
    getCurrencySymbol,
    processTransaction,
    todayInUTC,
    toIsoDateString,
  } from '../helpers';

  export default {
    components: {
      TransactionSchedule,
      MathInput,
      DatePicker,
      Button,
      AlertErrors,
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
        config_type: 'investment',
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
      data.investment_currency = null;

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
      ];

      // Some other settings
      data.locale = window.YAFFA.locale;

      return data;
    },

    computed: {
      total() {
        return (
          (this.form.config.quantity || 0) * (this.form.config.price || 0) +
          (this.form.config.dividend || 0) -
          ((this.form.config.commission || 0) + (this.form.config.tax || 0)) *
            // Taxes and commissions are added to the value when the transaction is a buy
            this.transactionTypeSettings.amount_multiplier
        );
      },

      transactionTypeSettings() {
        return (
          this.transactionTypes.find(
            (item) => item.name === this.form.transaction_type
          ) || {}
        );
      },

      currency() {
        return this.account_currency || this.investment_currency;
      },

      activeCallbackOptions() {
        return this.callbackOptions.filter((option) => option.enabled);
      },

      datePickerInitialPage() {
        let date = this.form.date || new Date();
        if (typeof date === 'string') {
          date = new Date(date);
        }
        return {
          year: date.getFullYear(),
          month: date.getMonth(),
        };
      },

      // Do we allow the user to edit the base settings?
      isBaseSettingsEditsAllowed() {
        return ['create', 'clone', 'finalize'].includes(this.action);
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
          amount_multiplier: -1,
        },
        {
          name: 'Sell',
          quantity: true,
          price: true,
          dividend: false,
          amount_multiplier: 1,
        },
        {
          name: 'Add shares',
          quantity: true,
          price: false,
          dividend: false,
          amount_multiplier: null,
        },
        {
          name: 'Remove shares',
          quantity: true,
          price: false,
          dividend: false,
          amount_multiplier: null,
        },
        {
          name: 'Dividend',
          quantity: false,
          price: false,
          dividend: true,
          amount_multiplier: 1,
        },
        {
          name: 'Interest yield',
          quantity: false,
          price: false,
          dividend: true,
          amount_multiplier: 1,
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
      $('#account')
        .select2({
          ajax: {
            url: '/api/assets/account/investment',
            dataType: 'json',
            delay: 150,
            data: function (params) {
              return {
                q: params.term,
                transaction_type: $vm.form.transaction_type,
                currency_id: $vm.investment_currency?.id,
                _token: $vm.csrfToken,
              };
            },
            processResults: function (data) {
              return {
                results: data,
              };
            },
            cache: true,
          },
          selectOnClose: false,
          placeholder: __('Select account'),
          allowClear: true,
          width: 'resolve',
          theme: 'bootstrap-5',
          // Component should not be aware where it is used, but we need to hint Select2
          dropdownParent: $(
            document.getElementById('modal-transaction-form-investment') ||
              document.querySelector('body')
          ),
        })
        .on('select2:select', function (e) {
          const event = new Event('change', {
            bubbles: true,
            cancelable: true,
          });
          e.target.dispatchEvent(event);

          $.ajax({
            url: '/api/assets/account/' + e.params.data.id,
            data: {
              _token: $vm.csrfToken,
            },
          }).done((data) => {
            $vm.account_currency = data.config.currency;
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
      $('#investment')
        .select2({
          ajax: {
            url: '/api/assets/investment',
            data: function (params) {
              return {
                q: params.term,
                currency_id: $vm.account_currency?.id,
                _token: $vm.csrfToken,
              };
            },
            dataType: 'json',
            delay: 150,
            processResults: function (data) {
              return {
                results: data,
              };
            },
            cache: true,
          },
          selectOnClose: false,
          placeholder: __('Select investment'),
          allowClear: true,
          width: 'resolve',
          theme: 'bootstrap-5',
          // Component should not be aware where it is used, but we need to hint Select2
          dropdownParent: $(
            document.getElementById('modal-transaction-form-investment') ||
              document.querySelector('body')
          ),
        })
        .on('select2:select', function (e) {
          const event = new Event('change', {
            bubbles: true,
            cancelable: true,
          });
          e.target.dispatchEvent(event);

          $.ajax({
            url: route('investment.getDetails', {
              investment: e.params.data.id,
            }),
            data: {
              _token: $vm.csrfToken,
            },
          }).done(function (data) {
            $vm.investment_currency = data.currency;
          });
        })
        .on('select2:unselect', function () {
          $vm.investment_id = null;
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
        }).done((data) => {
          // Create the option and append to Select2
          $('#account')
            .append(new Option(data.name, data.id, true, true))
            .trigger('change')
            .trigger({
              type: 'select2:select',
              params: {
                data: data,
              },
            });
        });
      },

      getDefaultInvestmentDetails(investment_id) {
        if (!investment_id) {
          return;
        }
        const $vm = this;

        $.ajax({
          url: route('investment.getDetails', { investment: investment_id }),
          data: {
            _token: $vm.csrfToken,
          },
        }).done(function (data) {
          // Create the option and append to Select2
          $('#investment')
            .append(new Option(data.name, data.id, true, true))
            .trigger('change')
            .trigger({
              type: 'select2:select',
              params: {
                data: data,
              },
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
          this.form.id = this.transaction.id;
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
          this.form.config.investment_id =
            this.transaction.config.investment_id;

          // Copy schedule config
          // TODO: date conversion should take place here, or elsewehere?
          if (this.transaction.transaction_schedule) {
            this.form.schedule_config.frequency =
              this.transaction.transaction_schedule.frequency;
            this.form.schedule_config.count =
              this.transaction.transaction_schedule.count;
            this.form.schedule_config.interval =
              this.transaction.transaction_schedule.interval;

            this.form.schedule_config.start_date = this.copyDateObject(
              this.transaction.transaction_schedule.start_date
            );
            this.form.schedule_config.next_date = this.copyDateObject(
              this.transaction.transaction_schedule.next_date
            );
            this.form.schedule_config.automatic_recording =
              this.transaction.transaction_schedule.automatic_recording;
            this.form.schedule_config.end_date = this.copyDateObject(
              this.transaction.transaction_schedule.end_date
            );

            this.form.schedule_config.inflation =
              this.transaction.transaction_schedule.inflation;
          }

          // If creating a schedule clone, we need to duplicate the schedule config, and make some adjustments
          if (this.action === 'replace') {
            this.form.original_schedule_config = {};
            this.form.original_schedule_config.frequency =
              this.form.schedule_config.frequency;
            this.form.original_schedule_config.count =
              this.form.schedule_config.count;
            this.form.original_schedule_config.interval =
              this.form.schedule_config.interval;
            this.form.original_schedule_config.inflation =
              this.form.schedule_config.inflation;
            this.form.original_schedule_config.start_date = this.copyDateObject(
              this.form.schedule_config.start_date
            );
            this.form.original_schedule_config.automatic_recording =
              this.form.schedule_config.automatic_recording;

            // Reset next date of original schedule config to set it ended
            this.form.original_schedule_config.next_date = undefined;

            // Set new schedule start date to today
            this.form.schedule_config.start_date = todayInUTC();

            // If this is a schedule, then set the new next date to today
            if (this.form.schedule) {
              this.form.schedule_config.next_date = todayInUTC();
            }

            // Set original schedule end date to today - 1 day
            this.form.original_schedule_config.end_date = new Date(
              todayInUTC().getTime() - 24 * 60 * 60 * 1000
            );
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
          location.href = window.route('transaction.create', {
            type: 'investment',
          });
          return;
        }

        if (this.callback === 'clone') {
          location.href = window.route('transaction.open', {
            transaction: transactionId,
            action: 'clone',
          });
          return;
        }

        if (this.callback === 'returnToPrimaryAccount') {
          location.href = window.route('account.history', {
            account: this.form.config.account_id,
          });
          return;
        }

        if (this.callback === 'returnToSecondaryAccount') {
          location.href = window.route('account.history', {
            account: this.form.config.account_id,
          });
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
          this.form
            .patch(
              window.route('api.transactions.updateInvestment', {
                transaction: this.form.id,
              }),
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
          return;
        }

        // Any type of new transaction needs POST method
        this.form
          .post(window.route('api.transactions.storeInvestment'), this.form)
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

        if (
          !this.$refs.scheduleOriginal ||
          this.$refs.scheduleOriginal.allowCustomization
        ) {
          return;
        }

        let date = new Date(newDate);
        date.setDate(date.getDate() - 1);
        this.form.original_schedule_config.end_date = toIsoDateString(date);
      },

      toFormattedCurrency,
    },

    watch: {
      // On change of new schedule start date, adjust original schedule end date to previous day
      'form.schedule_config.start_date': function (newDate) {
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
      },
    },
  };

  // Initialize tooltips
  // TODO: can this be part of Vue init?
  $(document).ready(function () {
    $('[data-toggle="tooltip"]').tooltip();
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
