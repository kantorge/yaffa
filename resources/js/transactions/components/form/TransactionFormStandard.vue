<template>
  <div id="transactionFormStandard">
    <AlertErrors
      :form="form"
      message="There were some problems with your input."
    />

    <payee-form
      action="new"
      :payee="{}"
      :simplified="true"
      id="newPayeeModal"
      @payee-selected="setPayee"
      v-if="!fromModal"
    ></payee-form>

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
                data-bs-toggle="tooltip"
                data-bs-placement="right"
                :title="
                  __(
                    'These settings cannot be changed after saving the transaction.',
                  )
                "
              ></span>
            </div>
            <div class="card-body">
              <div class="row">
                <div class="col btn-group mb-3 mb-xl-0">
                  <button
                    class="btn"
                    :class="transactionTypeBaseClass('withdrawal')"
                    :disabled="!isBaseSettingsEditsAllowed"
                    dusk="transaction-type-withdrawal"
                    type="button"
                    value="withdrawal"
                    @click="changeTransactionType"
                  >
                    <span class="fa fa-circle-minus text-danger"></span><br />
                    {{ __('Withdraw') }}
                  </button>
                  <button
                    class="btn"
                    :class="transactionTypeBaseClass('deposit')"
                    :disabled="!isBaseSettingsEditsAllowed"
                    dusk="transaction-type-deposit"
                    type="button"
                    value="deposit"
                    @click="changeTransactionType"
                  >
                    <span class="fa fa-circle-plus text-success"></span><br />
                    {{ __('Deposit') }}
                  </button>
                  <button
                    class="btn"
                    :class="transactionTypeBaseClass('transfer')"
                    :disabled="form.budget || !isBaseSettingsEditsAllowed"
                    dusk="transaction-type-transfer"
                    type="button"
                    value="transfer"
                    @click="changeTransactionType"
                  >
                    <span class="fa fa-exchange-alt"></span><br />
                    {{ __('Transfer') }}
                  </button>
                </div>
                <div
                  class="col d-flex justify-content-between gap-2 mb-0"
                  v-if="!simplified"
                >
                  <input
                    class="btn-check"
                    :disabled="form.reconciled || !isBaseSettingsEditsAllowed"
                    id="checkbox-standard-transaction-schedule"
                    type="checkbox"
                    autocomplete="off"
                    value="1"
                    v-model="form.schedule"
                  />
                  <label
                    class="btn btn-outline-dark w-100"
                    dusk="checkbox-transaction-schedule"
                    for="checkbox-standard-transaction-schedule"
                    :title="
                      action === 'replace'
                        ? __(
                            'You cannot change schedule settings for this type of action',
                          )
                        : ''
                    "
                    :data-bs-toggle="action === 'replace' ? 'tooltip' : ''"
                  >
                    <span class="fa-solid fa-arrows-rotate"></span><br />
                    {{ __('Scheduled') }}
                  </label>
                  <input
                    class="btn-check"
                    :disabled="
                      form.reconciled ||
                      form.transaction_type == 'transfer' ||
                      !isBaseSettingsEditsAllowed
                    "
                    id="checkbox-standard-transaction-budget"
                    type="checkbox"
                    autocomplete="off"
                    value="1"
                    v-model="form.budget"
                  />
                  <label
                    class="btn btn-outline-dark w-100"
                    dusk="checkbox-transaction-budget"
                    for="checkbox-standard-transaction-budget"
                    :title="
                      action === 'replace'
                        ? __(
                            'You cannot change schedule settings for this type of action',
                          )
                        : ''
                    "
                    :data-bs-toggle="action === 'replace' ? 'tooltip' : ''"
                  >
                    <span class="fa-solid fa-hourglass-half"></span><br />
                    {{ __('Budget') }}
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
                  class="col-4 col-sm-2 col-md-4 col-lg-2 mb-3 mb-sm-0 mb-md-3 mb-lg-0 d-flex justify-content-center"
                >
                  <input
                    class="btn-check"
                    :disabled="form.schedule || form.budget"
                    id="checkbox-standard-transaction-reconciled"
                    type="checkbox"
                    autocomplete="off"
                    value="1"
                    v-model="form.reconciled"
                  />
                  <label
                    class="btn btn-outline-success"
                    for="checkbox-standard-transaction-reconciled"
                  >
                    <span class="fa fa-check"></span><br />
                    {{ __('Reconciled') }}
                  </label>
                </div>
                <div
                  :class="[
                    action === 'enter'
                      ? 'col-8 col-sm-2 col-md-4 col-lg-2'
                      : 'col-8 col-sm-4 col-md-8 col-lg-4',
                    'mb-3 mb-sm-0 mb-md-3 mb-lg-0',
                    { 'has-error': form.errors.has('date') },
                  ]"
                >
                  <label class="form-label" for="standard-date">
                    {{ __('Date') }}
                  </label>
                  <input
                    type="date"
                    class="form-control"
                    :disabled="form.schedule || form.budget"
                    id="standard-date"
                    v-model="dateInput"
                  />
                </div>
                <div
                  class="col-12 col-sm-2 col-md-4 col-lg-2 mb-3 mb-sm-0 mb-md-3 mb-lg-0"
                  v-if="action === 'enter'"
                >
                  <div class="form-check">
                    <input
                      class="form-check-input"
                      dusk="checkbox-standard-catch-up-schedule"
                      id="checkbox-standard-catch-up-schedule"
                      type="checkbox"
                      value="1"
                      v-model="form.catch_up_schedule"
                    />
                    <label
                      class="form-check-label"
                      for="checkbox-standard-catch-up-schedule"
                    >
                      {{ __('Skip to nearest future occurrence') }}
                      <i
                        class="fa fa-info-circle text-primary"
                        data-bs-toggle="tooltip"
                        data-bs-placement="top"
                        :title="
                          __(
                            'Instead of the next date, also advances the schedule past any other occurrences still due, so its next occurrence is today or later.',
                          )
                        "
                      ></i>
                      <i
                        class="fa fa-triangle-exclamation text-warning ms-1"
                        data-bs-toggle="tooltip"
                        data-bs-placement="top"
                        :title="
                          __(
                            'May close this schedule if no occurrences remain.',
                          )
                        "
                        v-if="catchUpMayCloseSchedule"
                      ></i>
                    </label>
                  </div>
                </div>
                <div
                  class="col-12 col-sm-6 col-md-12 col-lg-6 col-sm-6 mb-0"
                  :class="form.errors.has('comment') ? 'has-error' : ''"
                >
                  <label for="standard-comment" class="form-label">
                    {{ __('Comment') }}
                  </label>
                  <input
                    class="form-control"
                    id="standard-comment"
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
        <div class="col-12">
          <transaction-schedule
            v-if="form.schedule || form.budget"
            :isSchedule="form.schedule"
            :isBudget="form.budget"
            :schedule="form.schedule_config"
            :form="form"
            key="current"
          ></transaction-schedule>
        </div>
        <div class="col-12">
          <transaction-schedule
            v-if="(form.schedule || form.budget) && action === 'replace'"
            :withCheckbox="true"
            :title="__('Update base schedule')"
            :allowCustomization="false"
            :isSchedule="form.schedule"
            :isBudget="form.budget"
            :schedule="form.original_schedule_config"
            :form="form"
            field-prefix="original_schedule_config"
            ref="scheduleOriginal"
            key="original"
          ></transaction-schedule>
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
                <div
                  class="col-12 mb-3"
                  :class="
                    form.errors.has('config.account_from_id') ? 'has-error' : ''
                  "
                >
                  <span class="form-label block-label">
                    {{ accountFromFieldLabel }}
                  </span>
                  <div class="input-group" id="account_from_container">
                    <select
                      class="form-select"
                      id="account_from"
                      v-model="form.config.account_from_id"
                    ></select>
                    <button
                      class="btn btn-success"
                      style="padding: 0.05rem 0.25rem"
                      :title="__('Add new payee')"
                      type="button"
                      data-coreui-toggle="modal"
                      data-coreui-target="#newPayeeModal"
                      v-if="form.transaction_type === 'deposit' && !fromModal"
                    >
                      <span class="fa fa-fw fa-plus"></span>
                    </button>
                  </div>
                </div>
                <div
                  class="col-12"
                  :class="
                    form.errors.has('config.account_to_id') ? 'has-error' : ''
                  "
                >
                  <span class="form-label block-label">
                    {{ accountToFieldLabel }}
                  </span>
                  <div class="input-group" id="account_to_container">
                    <select
                      class="form-select"
                      id="account_to"
                      v-model="form.config.account_to_id"
                    ></select>
                    <button
                      class="btn btn-success"
                      style="padding: 0.05rem 0.25rem"
                      :title="__('Add new payee')"
                      type="button"
                      data-coreui-toggle="modal"
                      data-coreui-target="#newPayeeModal"
                      v-if="
                        form.transaction_type === 'withdrawal' && !fromModal
                      "
                    >
                      <span class="fa fa-fw fa-plus"></span>
                    </button>
                  </div>
                </div>
              </div>
              <div class="row mb-3">
                <div
                  class="col-4"
                  :class="
                    form.errors.has('config.amount_from') ? 'has-error' : ''
                  "
                >
                  <label for="transaction_amount_from" class="form-label">
                    {{ ammountFromFieldLabel }}
                    <span
                      v-if="ammountFromCurrencyLabel"
                      dusk="label-amountFrom-currency"
                    >
                      ({{ ammountFromCurrencyLabel }})
                    </span>
                    <span v-if="form.budget && !ammountFromCurrencyLabel">
                      ({{ getCurrencySymbol(locale, baseCurrency.iso_code) }})
                      <span
                        class="fa fa-info-circle text-primary"
                        :title="
                          __(
                            'Budget is calculated using your base currency, unless you define an account with an other currency.',
                          )
                        "
                        data-bs-toggle="tooltip"
                      ></span>
                    </span>
                  </label>
                  <MathInput
                    class="form-control"
                    id="transaction_amount_from"
                    v-model="form.config.amount_from"
                    :precision="amountFromPrecision"
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
                  :class="
                    form.errors.has('config.amount_to') ? 'has-error' : ''
                  "
                >
                  <label for="transaction_amount_to" class="form-label">
                    {{ __('Amount to') }}
                    <span
                      v-if="to.account_currency"
                      dusk="label-amountTo-currency"
                    >
                      ({{
                        getCurrencySymbol(locale, to.account_currency.iso_code)
                      }})
                    </span>
                  </label>
                  <MathInput
                    class="form-control"
                    id="transaction_amount_to"
                    v-model="form.config.amount_to"
                    :precision="amountToPrecision"
                  ></MathInput>
                </div>
              </div>
              <dl class="row" v-if="!transactionTypeIsTransfer">
                <dt class="col-8">
                  {{ __('Total amount') }}
                </dt>
                <dd class="col-4">
                  <span v-if="ammountFromCurrencyLabel">
                    {{
                      toFormattedCurrency(
                        form.config.amount_from || 0,
                        this.locale,
                        this.from.account_currency,
                      )
                    }}
                  </span>
                  <span v-else>
                    {{ form.config.amount_from || 0 }}
                  </span>
                </dd>
                <dt class="col-8">
                  {{ __('Total allocated') }}
                </dt>
                <dd class="col-4">
                  <span v-if="ammountFromCurrencyLabel">
                    {{
                      toFormattedCurrency(
                        allocatedAmount,
                        this.locale,
                        this.from.account_currency,
                      )
                    }}
                  </span>
                  <span v-else>
                    {{ allocatedAmount }}
                  </span>
                </dd>
                <dt class="col-8" v-show="payeeCategory.id">
                  {{ __('Remaining amount to') }}
                  <span class="notbold"><br />{{ payeeCategory.text }}</span>
                </dt>
                <dd class="col-4" v-show="payeeCategory.id">
                  <span v-if="ammountFromCurrencyLabel">
                    {{
                      toFormattedCurrency(
                        remainingAmountToPayeeDefault,
                        this.locale,
                        this.from.account_currency,
                      )
                    }}
                  </span>
                  <span v-else>
                    {{ remainingAmountToPayeeDefault }}
                  </span>
                </dd>
                <dt class="col-8" v-show="!payeeCategory.id">
                  {{ __('Not allocated') }}:
                </dt>
                <dd class="col-4" v-show="!payeeCategory.id">
                  <span v-if="ammountFromCurrencyLabel">
                    {{
                      toFormattedCurrency(
                        remainingAmountNotAllocated,
                        this.locale,
                        this.from.account_currency,
                      )
                    }}
                  </span>
                  <span v-else>
                    {{ remainingAmountNotAllocated }}
                  </span>
                </dd>
              </dl>
            </div>
          </div>
        </div>
        <div class="col-md-8">
          <!-- AI Recommendations Info Banner -->
          <div
            v-if="hasAiRecommendations"
            class="alert alert-primary mb-3"
            role="alert"
          >
            <div class="d-flex align-items-center">
              <i class="fa fa-robot fa-2x me-3"></i>
              <div>
                <h6 class="alert-heading mb-2">
                  ✨ {{ __('AI-Assisted Transaction') }}
                </h6>
                <p class="mb-1 small">
                  {{
                    __(
                      'Review the suggested categories below. Items show the extracted description and AI recommendations with confidence scoring.',
                    )
                  }}
                </p>
              </div>
            </div>
          </div>

          <transaction-item-container
            @addTransactionItem="addTransactionItem"
            :transactionItems="form.items"
            :currencySymbol="ammountFromCurrencyLabel"
            :payee="payeeId"
            :transactionType="form.transaction_type"
            :amountFrom="form.config.amount_from"
            :remainingAmount="
              remainingAmountNotAllocated || remainingAmountToPayeeDefault || 0
            "
            :enabled="!transactionTypeIsTransfer"
            :precision="amountFromPrecision"
            :dropdown-parent-selector="dropdownParentSelector"
          ></transaction-item-container>
        </div>
      </div>

      <div class="card mb-3">
        <div class="card-body">
          <div class="row justify-content-end">
            <div
              class="d-none d-lg-block col-lg-12 col-xl-9 mb-3 mb-lg-3 mb-xl-0"
              v-if="!fromModal"
              dusk="action-after-save-desktop-button-group"
            >
              <span class="form-label block-label">
                {{ __('Action after saving') }}
              </span>
              <div
                class="btn-group"
                role="group"
                aria-label="{{ __('Action after saving') }}"
              >
                <button
                  v-for="item in activeCallbackOptions"
                  :key="item.id"
                  class="btn btn-outline-dark"
                  :class="{ active: callback === item.value }"
                  type="button"
                  :value="item.value"
                  @click="callback = $event.currentTarget.getAttribute('value')"
                >
                  {{ item.label }}
                </button>
              </div>
            </div>
            <div
              class="col-12 col-sm-8 d-block d-lg-none mb-3 mb-sm-0"
              v-if="!fromModal"
            >
              <label class="form-label" for="callback-selector-mobile-standard">
                {{ __('Action after saving') }}
              </label>
              <select
                class="form-control"
                v-model="callback"
                id="callback-selector-mobile-standard"
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
            <div
              class="col-12 col-sm-4 col-lg-12 col-xl-3 text-end align-self-end"
            >
              <button
                class="btn btn-sm btn-outline-dark align-bottom"
                @click="onCancel"
                type="button"
              >
                {{ __('Cancel') }}
              </button>
              <Button
                class="btn btn-lg btn-primary ms-3 mt-2"
                :disabled="form.busy"
                :form="form"
                id="transactionFormStandard-Save"
              >
                <span class="fa fa-floppy-disk me-1" v-show="!form.busy"></span>
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
  import { RRule } from 'rrule';
  import {
    todayInUTC,
    processTransaction,
    initializeBootstrapTooltips,
    parseIsoDate,
    byDayToRRuleWeekday,
    toDateInputValue,
    toRRuleDate,
  } from '@/shared/lib/helpers';
  import {
    __,
    getCurrencySymbol,
    getDecimalPrecision,
    toFormattedCurrency,
  } from '@/shared/lib/i18n';
  import { initializeSelect2 } from '@/shared/lib/select2';
  initializeSelect2(window.YAFFA.userSettings.language);

  import Decimal from 'decimal.js';
  import MathInput from '@/shared/ui/form/MathInput.vue';

  import Form from 'vform';
  import { Button, AlertErrors } from 'vform/src/components/bootstrap5';

  import TransactionItemContainer from './TransactionItemContainer.vue';
  import TransactionSchedule from './TransactionSchedule.vue';

  import PayeeForm from '@/payee/components/PayeeForm.vue';

  export default {
    components: {
      TransactionItemContainer,
      TransactionSchedule,
      PayeeForm,
      Button,
      AlertErrors,
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
      },
      aiDocumentId: {
        type: Number,
        default: null,
      },
      dropdownParentSelector: {
        type: String,
        default: 'body',
      },
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
        transaction_type: 'withdrawal',
        config_type: 'standard',
        date: todayInUTC(),
        comment: null,
        schedule: false,
        budget: false,
        reconciled: false,
        catch_up_schedule: false,
        config: {},
        items: [],
        schedule_config: {
          frequency: 'DAILY',
          interval: 1,
        },
        remaining_payee_default_amount: 0,
        remaining_payee_default_category_id: null,
        ai_document_id: null,
      });

      // Id counter for items
      data.itemCounter = 0;

      data.callback = this.initialCallback;

      // Various callback options
      data.callbackOptions = [
        {
          value: 'create',
          label: __('callback.newTransaction'),
          enabled: true,
        },
        {
          value: 'clone',
          label: __('callback.cloneTransaction'),
          enabled: true,
        },
        {
          value: 'show',
          label: __('callback.showTransaction'),
          enabled: true,
        },
        {
          value: 'returnToPrimaryAccount',
          label: __('callback.returnToPrimaryAccount'),
          enabled: true,
        },
        {
          value: 'returnToSecondaryAccount',
          label: __('callback.returnToSecondaryAccount'),
          enabled: false,
        },
        {
          value: 'returnToDashboard',
          label: __('callback.returnToDashboard'),
          enabled: true,
        },
        {
          value: 'back',
          label: __('callback.returnToPreviousPage'),
          enabled: true,
        },
      ];

      // Some other settings
      data.locale = window.YAFFA.userSettings.locale;
      data.initializingTransaction = false;

      return data;
    },

    computed: {
      // Account TO and FROM labels based on transaction type
      accountFromFieldLabel() {
        return ['withdrawal', 'transfer'].includes(this.form.transaction_type)
          ? __('Account from')
          : __('Payee');
      },

      accountToFieldLabel() {
        return ['deposit', 'transfer'].includes(this.form.transaction_type)
          ? __('Account to')
          : __('Payee');
      },

      // Return only those callback options, which are available based on the current form state
      activeCallbackOptions() {
        return this.callbackOptions.filter((option) => option.enabled);
      },

      // Amount from label is different for transfer
      ammountFromFieldLabel() {
        return this.exchangeRatePresent ? __('Amount from') : __('Amount');
      },

      /**
       * Computes the currency label based on the transaction type and account currency.
       *
       * - For 'withdrawal' and 'transfer' transaction types, it returns the currency symbol
       *   of the source account (`from.account_currency`).
       * - For 'deposit' transaction type, it returns the currency symbol of the destination
       *   account (`to.account_currency`).
       * - If no matching conditions are met or the account currency is unavailable, it returns an empty string.
       *
       * @returns {string} The currency symbol for the relevant account or an empty string.
       */
      ammountFromCurrencyLabel() {
        if (['withdrawal', 'transfer'].includes(this.form.transaction_type)) {
          if (this.from.account_currency) {
            return getCurrencySymbol(
              this.locale,
              this.from.account_currency.iso_code,
            );
          }
        }

        if (this.form.transaction_type === 'deposit') {
          if (this.to.account_currency) {
            return getCurrencySymbol(
              this.locale,
              this.to.account_currency.iso_code,
            );
          }
        }

        return '';
      },

      // The currency amount_from/the item amounts are denominated in - mirrors
      // ammountFromCurrencyLabel's account-selection logic, but returns the
      // currency object itself rather than just its display symbol.
      amountFromCurrency() {
        if (['withdrawal', 'transfer'].includes(this.form.transaction_type)) {
          return this.from.account_currency || null;
        }

        if (this.form.transaction_type === 'deposit') {
          return this.to.account_currency || null;
        }

        return null;
      },

      amountFromPrecision() {
        return getDecimalPrecision(this.amountFromCurrency);
      },

      amountToPrecision() {
        return getDecimalPrecision(this.to.account_currency);
      },

      // Calculate the summary of all existing items and their values, using
      // exact decimal arithmetic to avoid float drift accumulating across items.
      allocatedAmount() {
        return this.form.items
          .reduce(
            (amount, item) => amount.plus(new Decimal(item.amount || 0)),
            new Decimal(0),
          )
          .toNumber();
      },

      // Provide the base currency from the global scope for the template
      baseCurrency() {
        return window.YAFFA.userSettings.baseCurrency;
      },

      remainingAmountToPayeeDefault() {
        if (this.payeeCategory.id && !isNaN(this.form.config.amount_from)) {
          return new Decimal(this.form.config.amount_from || 0)
            .minus(this.allocatedAmount)
            .toNumber();
        }
        return 0;
      },

      remainingAmountNotAllocated() {
        if (!this.payeeCategory.id && !isNaN(this.form.config.amount_from)) {
          return new Decimal(this.form.config.amount_from || 0)
            .minus(this.allocatedAmount)
            .toNumber();
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
        return (
          this.from.account_currency &&
          this.to.account_currency &&
          this.from.account_currency.id !== this.to.account_currency.id
        );
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

      // Native <input type="date"> needs a 'YYYY-MM-DD' string, while
      // form.date may hold a Date object (see toRRuleDate above) - this
      // bridges the two without changing the stored type.
      dateInput: {
        get() {
          return toDateInputValue(this.form.date);
        },
        set(value) {
          this.form.date = value || null;
        },
      },

      // Predicts whether catching up to today would close this schedule, by checking
      // whether the rule (anchored at start_date, same as the backend's
      // TransactionSchedule::catchUpToDate()) has any occurrence left on or after
      // today. next_date doesn't need to be considered separately: it's always one of
      // the rule's own occurrences, so "no occurrence on/after today anywhere in the
      // rule" and "no occurrence on/after today reachable from next_date" agree.
      catchUpMayCloseSchedule() {
        if (this.action !== 'enter') {
          return false;
        }

        const schedule = this.form.schedule_config;
        if (!schedule?.frequency || !schedule?.start_date) {
          return false;
        }

        const start = toRRuleDate(schedule.start_date);
        if (!start) {
          return false;
        }

        try {
          const rule = new RRule({
            freq: RRule[schedule.frequency],
            interval: schedule.interval || 1,
            dtstart: start,
            until: schedule.end_date ? toRRuleDate(schedule.end_date) : null,
            count: schedule.count || null,
            byweekday: schedule.by_day ? byDayToRRuleWeekday(schedule.by_day) : null,
            bymonth: schedule.by_month || null,
          });

          return rule.after(toRRuleDate(new Date()), true) === null;
        } catch {
          return false;
        }
      },

      // Do we allow the user to edit the base settings?
      isBaseSettingsEditsAllowed() {
        return ['create', 'finalize'].includes(this.action);
      },

      // Check if any items have AI recommendations
      hasAiRecommendations() {
        return this.form.items.some(
          (item) =>
            item.recommended_category_id || item.description || item.match_type,
        );
      },
    },

    created() {
      // Copy values of existing transaction into component form data
      this.initializeTransaction();
    },

    mounted() {
      // Account FROM dropdown functionality
      $('#account_from')
        .select2(this.getAccountSelectConfig('from'))
        .on('select2:select', (e) => {
          const event = new Event('change', {
            bubbles: true,
            cancelable: true,
          });
          e.target.dispatchEvent(event);

          if (this.getAccountType('from') === 'account') {
            $.ajax({
              url: '/api/v1/accounts/' + e.params.data.id,
              data: {
                _token: this.csrfToken,
              },
            }).done((data) => {
              this.from.account_currency = data.config.currency;
            });
          } else {
            $.ajax({
              url: '/api/v1/payees/' + e.params.data.id,
              data: {
                _token: this.csrfToken,
              },
            }).done((data) => {
              if (data.config.category) {
                this.payeeCategory.id = data.config.category.id;
                this.payeeCategory.text = data.config.category.full_name;
              }
            });
          }
        })
        .on('select2:unselect', () => {
          this.resetAccount('from');
          if (this.getAccountType('from') === 'payee') {
            this.resetPayee();
          }
        });

      // Load default value for account FROM, based on transaction type
      this.getDefaultAccountDetails(
        this.transaction?.config?.account_from_id,
        'from',
      );

      // Account TO dropdown functionality
      $('#account_to')
        .select2(this.getAccountSelectConfig('to'))
        .on('select2:select', (e) => {
          const event = new Event('change', {
            bubbles: true,
            cancelable: true,
          });
          e.target.dispatchEvent(event);

          if (this.getAccountType('to') === 'account') {
            $.ajax({
              url: '/api/v1/accounts/' + e.params.data.id,
              data: {
                _token: this.csrfToken,
              },
            }).done((data) => {
              this.to.account_currency = data.config.currency;
            });
          } else if (this.getAccountType('to') === 'payee') {
            $.ajax({
              url: '/api/v1/payees/' + e.params.data.id,
              data: {
                _token: this.csrfToken,
              },
            }).done((data) => {
              if (data.config.category) {
                this.payeeCategory.id = data.config.category.id;
                this.payeeCategory.text = data.config.category.full_name;
              }
            });
          }
        })
        .on('select2:unselect', () => {
          this.resetAccount('to');
          if (this.getAccountType('to') === 'payee') {
            this.resetPayee();
          }
        });

      // Load default value for account TO
      this.getDefaultAccountDetails(
        this.transaction?.config?.account_to_id,
        'to',
      );

      // Initial sync between schedules, if applicable
      this.syncScheduleStartDate(this.form.schedule_config.start_date);

      // Initialize tooltips
      initializeBootstrapTooltips(this.$el);
    },

    beforeUnmount() {
      $('#account_from').off().select2('destroy');
      $('#account_to').off().select2('destroy');
    },

    methods: {
      getCurrencySymbol,
      toFormattedCurrency,
      initializeTransaction() {
        this.initializingTransaction = true;

        if (this.transaction && Object.keys(this.transaction).length > 0) {
          // Populate form data with already known values
          this.form.id = this.transaction.id;
          // Transaction type is now directly the enum value string
          this.form.transaction_type = this.transaction.transaction_type;

          // Populate date from source transaction, and ensure that it's a Date object
          this.form.date = parseIsoDate(this.transaction.date);

          this.form.comment = this.transaction.comment;
          this.form.schedule = this.transaction.schedule ?? false;
          this.form.budget = this.transaction.budget ?? false;
          this.form.reconciled = this.transaction.reconciled ?? false;

          // Copy configuration
          this.form.config.amount_from = this.transaction.config.amount_from;
          this.form.config.amount_to = this.transaction.config.amount_to;
          this.form.config.account_from_id =
            this.transaction.config.account_from_id;
          this.form.config.account_to_id =
            this.transaction.config.account_to_id;

          // Copy transaction items from either saved transaction or AI draft
          // Both sources use 'transaction_items' key for consistency
          //
          // Property mapping between sources:
          // - Saved transactions: Have category_id, comment, tags (from database)
          // - AI drafts: Have description, recommended_category_id, match_type, confidence_score (ephemeral AI metadata)
          //
          // Note: AI-specific properties (description, match_type, confidence_score) are used during
          // finalization but NOT persisted to the database. Only the user-selected category_id and
          // optional comment are saved. The 'description' field is preserved for category learning.
          if (this.transaction.transaction_items?.length > 0) {
            this.transaction.transaction_items
              .map((item) => this.normalizeTransactionItem(item))
              .forEach((item) => this.form.items.push(item));
          }

          // Copy schedule config
          // TODO: date conversion should take place here, or elsewehere?
          if (this.transaction.transaction_schedule) {
            this.form.schedule_config.frequency =
              this.transaction.transaction_schedule.frequency;
            this.form.schedule_config.count =
              this.transaction.transaction_schedule.count;
            this.form.schedule_config.interval =
              this.transaction.transaction_schedule.interval;
            this.form.schedule_config.by_day =
              this.transaction.transaction_schedule.by_day;
            this.form.schedule_config.by_month =
              this.transaction.transaction_schedule.by_month;

            this.form.schedule_config.start_date = parseIsoDate(
              this.transaction.transaction_schedule.start_date,
            );
            this.form.schedule_config.next_date = parseIsoDate(
              this.transaction.transaction_schedule.next_date,
            );
            this.form.schedule_config.automatic_recording =
              this.transaction.transaction_schedule.automatic_recording;
            this.form.schedule_config.end_date = parseIsoDate(
              this.transaction.transaction_schedule.end_date,
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
            this.form.original_schedule_config.by_day =
              this.form.schedule_config.by_day;
            this.form.original_schedule_config.by_month =
              this.form.schedule_config.by_month;
            this.form.original_schedule_config.inflation =
              this.form.schedule_config.inflation;
            this.form.original_schedule_config.start_date = parseIsoDate(
              this.form.schedule_config.start_date,
            );
            this.form.original_schedule_config.automatic_recording =
              this.form.schedule_config.automatic_recording;

            // Reset the next date of the original schedule config to set it ended
            this.form.original_schedule_config.next_date = null;

            // Set new schedule start date to today
            this.form.schedule_config.start_date = todayInUTC();

            // If this is a schedule, then set the new next date to today
            if (this.form.schedule) {
              this.form.schedule_config.next_date = todayInUTC();
            }

            // The end date carried over from the original schedule may now be
            // in the past relative to the new start date - the new schedule
            // can't already be over before its first occurrence, so clear it.
            // Compared as calendar-date strings (not raw timestamps), since
            // todayInUTC() and parseIsoDate() don't share the same
            // time-of-day representation in every timezone.
            const newEndDateValue = toDateInputValue(
              this.form.schedule_config.end_date,
            );
            const newStartDateValue = toDateInputValue(
              this.form.schedule_config.start_date,
            );
            if (newEndDateValue && newEndDateValue < newStartDateValue) {
              this.form.schedule_config.end_date = null;
            }

            // Set original schedule end date to today - 1 day
            this.form.original_schedule_config.end_date = new Date(
              todayInUTC().getTime() - 24 * 60 * 60 * 1000,
            );
          }
        }

        // Assign AI document ID passed to the form for future reference when saving the transaction
        this.form.ai_document_id = this.aiDocumentId;

        // Set form action
        this.form.action = this.action;

        this.$nextTick(() => {
          this.initializingTransaction = false;
        });
      },

      normalizeTransactionItem(rawItem) {
        const item = { ...rawItem };

        item.id = this.itemCounter++;
        item.amount = Number(item.amount);
        item.learnRecommendation = true;

        item.category_full_name =
          item.category_full_name || item.category?.full_name || null;

        item.recommended_category_full_name =
          item.recommended_category_full_name ||
          item.recommended_category?.full_name ||
          null;

        item.description = item.description || null;
        item.match_type = item.match_type || null;
        item.confidence_score = item.confidence_score || null;

        return item;
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
        if (
          !confirm(
            __(
              'Are you sure, you want to change the transaction type? Some data might get lost.',
            ),
          )
        ) {
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

          $('#account_from')
            .val(null)
            .trigger('change')
            .select2('destroy')
            .select2(this.getAccountSelectConfig('from'));
        }

        // Reassign account TO functionality, if changed
        if (oldTypeTo !== this.getAccountType('to') || forceUpdate) {
          this.resetAccount('to');
          if (this.getAccountType('to') !== 'account') {
            this.resetPayee();
          }

          $('#account_to')
            .val(null)
            .trigger('change')
            .select2('destroy')
            .select2(this.getAccountSelectConfig('to'));
        }

        // Remove all items, if transaction type is transfer
        if (this.form.transaction_type === 'transfer') {
          this.form.items = [];
        }

        // Update callback options
        const foundCallbackIndex = this.callbackOptions.findIndex(
          (x) => x.value === 'returnToSecondaryAccount',
        );
        this.callbackOptions[foundCallbackIndex]['enabled'] =
          newState === 'transfer';

        // Ensure, that selected item is enabled. Otherwise, set to first enabled option
        const selectedCallbackIndex = this.callbackOptions.findIndex(
          (x) => x.value === this.callback,
        );
        if (!this.callbackOptions[selectedCallbackIndex].enabled) {
          this.callback = this.callbackOptions.find((option) => option.enabled)[
            'value'
          ];
        }
      },

      // Add a new empty item to list of transaction items
      addTransactionItem() {
        this.form.items.push({
          id: this.itemCounter++,
          learnRecommendation: true,
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
        const accountUrl = '/api/v1/accounts';
        const payeeUrl = '/api/v1/payees';

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
        let otherType = type === 'from' ? 'to' : 'from';

        return {
          theme: 'bootstrap-5',
          language: window.YAFFA.userSettings.language,
          ajax: {
            url: this.getAccountApiUrl(type),
            dataType: 'json',
            delay: 150,
            data: (params) => {
              return {
                _token: this.csrfToken,
                q: params.term,
                transaction_type: this.form.transaction_type,
                account_type: type,
                account_entity_id: this.accountId,
              };
            },
            processResults: (data) => {
              // Exclude account that is selected in other account select
              let otherAccountId =
                this.form.config['account_' + otherType + '_id'];
              if (otherAccountId) {
                data = data.filter((item) => item.id != otherAccountId);
              }

              return {
                results: data.map((account) => {
                  return {
                    id: account.id,
                    text: account.name,
                  };
                }),
              };
            },
            cache: true,
          },
          selectOnClose: false,
          // Set placeholder based on type parameter and transaction type
          placeholder: this.getPlaceholder(type),
          allowClear: true,
          width: 'resolve',
          // Component should not be aware where it is used, but we need to hint Select2
          dropdownParent: $(this.dropdownParentSelector),
        };
      },

      getDefaultAccountDetails(account_entity_id, type) {
        if (!account_entity_id) {
          return;
        }

        if (!['account', 'payee'].includes(this.getAccountType(type))) {
          return;
        }

        const selector = '#account_' + type;

        $.ajax({
          url: this.getAccountApiUrl(type) + '/' + account_entity_id,
          data: {
            _token: this.csrfToken,
          },
        }).done((data) => {
          // Create the option and append to Select2
          this.addNewItemToSelect(selector, data.id, data.name);
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
        if (
          !this.from.account_currency ||
          !this.to.account_currency ||
          this.from.account_currency.iso_code ===
            this.to.account_currency.iso_code
        ) {
          this.form.config.amount_to = this.form.config.amount_from;
        }

        // Editing an existing transaction needs PATCH method
        if (this.action === 'edit') {
          this.form
            .patch(
              this.route('api.v1.transactions.update-standard', {
                transaction: this.form.id,
              }),
              this.form,
            )
            .then((response) => {
              this.$emit(
                'success',
                processTransaction(response.data.transaction),
                {
                  callback: this.callback,
                  categoryLearningSummary:
                    response.data.category_learning_summary,
                },
              );
            });
          return;
        }

        // Any type of new transaction needs POST method
        this.form
          .post(this.route('api.v1.transactions.store-standard'), this.form)
          .then((response) => {
            this.$emit(
              'success',
              processTransaction(response.data.transaction),
              {
                callback: this.callback,
                categoryLearningSummary:
                  response.data.category_learning_summary,
              },
            );
          });
      },

      setPayee(payee) {
        // Determine which of the accounts need update
        if (!['withdrawal', 'deposit'].includes(this.form.transaction_type)) {
          return;
        }

        const accountSelector =
          this.form.transaction_type === 'withdrawal'
            ? '#account_to'
            : '#account_from';

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
              },
            },
          });
      },

      // Sync the standard schedule start date to the cloned schedule end date
      syncScheduleStartDate(newDate) {
        if (
          !newDate ||
          !this.form.original_schedule_config ||
          !this.$refs.scheduleOriginal ||
          this.$refs.scheduleOriginal.allowCustomization
        ) {
          return;
        }
        const date = parseIsoDate(newDate);
        if (!date) {
          return;
        }
        const endDate = new Date(date);
        endDate.setDate(endDate.getDate() - 1);
        this.form.original_schedule_config.end_date = endDate;
      },
      __,

      transactionTypeBaseClass(transactionType) {
        // Are edits allowed?
        if (this.isBaseSettingsEditsAllowed) {
          return (
            'btn-outline-primary' +
            (this.form.transaction_type === transactionType ? ' active' : '')
          );
        }

        // Edits are not allowed, so we need to disable the button, while still making it optionally look selected
        if (this.form.transaction_type === transactionType) {
          return 'btn-primary';
        }

        return 'btn-outline-primary';
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
      'form.schedule_config.start_date': function (newDate) {
        this.syncScheduleStartDate(newDate);
      },

      // The catch-up warning icon is only rendered when this is true (v-if), so a
      // newly-mounted icon needs its own tooltip initialization - the one in
      // mounted() only covers icons already in the DOM at that point.
      catchUpMayCloseSchedule() {
        this.$nextTick(() => initializeBootstrapTooltips(this.$el));
      },

      transaction(transaction) {
        // TODO: consider using form.update()
        this.form.reset();

        // Copy values of existing transaction into component form data
        this.initializeTransaction();

        // Ensure that new transaction type is set
        // Transaction type is now directly the enum value string
        this.onChangeTransactionType(transaction.transaction_type, true);

        // Load default value for accounts
        this.getDefaultAccountDetails(
          transaction.config.account_from_id,
          'from',
        );
        this.getDefaultAccountDetails(transaction.config.account_to_id, 'to');
      },

      // Remove the form date value when schedule or budget is enabled, and restore it when disabled
      'form.schedule': function (newState) {
        if (this.initializingTransaction) {
          return;
        }

        if (newState || this.form.budget) {
          this.form.date = null;
        } else {
          this.form.date = todayInUTC();
        }
      },
      'form.budget': function (newState) {
        if (this.initializingTransaction) {
          return;
        }

        if (newState || this.form.schedule) {
          this.form.date = null;
        } else {
          this.form.date = todayInUTC();
        }
      },
    },
  };
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
