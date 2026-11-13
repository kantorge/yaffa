<template>
  <div id="transactionFormInvestment" :data-loaded="formLoaded">
    <AlertErrors
      :form="form"
      :message="__('There were some problems with your input.')"
    />

    <form accept-charset="UTF-8" autocomplete="off" @submit.prevent="onSubmit">
      <template-fields
        v-if="isTemplate"
        v-model:name="templateName"
        v-model:is-featured="templateIsFeatured"
        :notices="templateInfo?.notices || []"
        :errors="form.errors"
      ></template-fields>
      <div class="row">
        <div class="col-md-4">
          <div class="card mb-3">
            <div class="card-header d-flex justify-content-between">
              <div class="card-title">
                {{ __('Settings') }}
              </div>
              <span
                class="fa fa-info-circle text-info"
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
                <div class="col-12 col-md-8 col-lg-12 col-xl-8 mb-2 mb-xl-0">
                  <div class="form-group">
                    <label for="transaction_type" class="form-label">
                      {{ __('Transaction type') }}
                    </label>
                    <select
                      id="transaction_type"
                      :value="form.transaction_type"
                      class="form-select"
                      :disabled="!isBaseSettingsEditsAllowed"
                      @change="transactionTypeChanged($event)"
                    >
                      <option
                        v-for="item in transactionTypes"
                        :key="item.value"
                        :value="item.value"
                      >
                        {{ item.name }}
                      </option>
                    </select>
                  </div>
                </div>
                <div
                  v-if="!simplified"
                  class="col col-xl-4 d-flex justify-content-center"
                >
                  <input
                    id="checkbox-investment-transaction-schedule"
                    v-model="form.schedule"
                    class="btn-check"
                    :disabled="form.reconciled || !isBaseSettingsEditsAllowed"
                    type="checkbox"
                    autocomplete="off"
                    value="1"
                  />
                  <label
                    class="btn btn-outline-secondary w-100"
                    dusk="checkbox-transaction-schedule"
                    for="checkbox-investment-transaction-schedule"
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
                  v-if="!isTemplate"
                  class="col-4 col-sm-2 col-md-4 col-lg-2 mb-3 mb-sm-0 mb-md-3 mb-lg-0 d-flex justify-content-center"
                >
                  <input
                    id="checkbox-investment-transaction-reconciled"
                    v-model="form.reconciled"
                    class="btn-check"
                    :disabled="form.schedule"
                    type="checkbox"
                    autocomplete="off"
                    value="1"
                  />
                  <label
                    class="btn btn-outline-success"
                    for="checkbox-investment-transaction-reconciled"
                  >
                    <span class="fa fa-check"></span><br />
                    {{ __('Reconciled') }}
                  </label>
                </div>
                <div
                  v-if="!isTemplate"
                  :class="[
                    action === 'enter'
                      ? 'col-8 col-sm-2 col-md-4 col-lg-2'
                      : 'col-8 col-sm-4 col-md-8 col-lg-4',
                    'mb-3 mb-sm-0 mb-md-3 mb-lg-0',
                    {
                      'has-error': form.errors.has('date'),
                    },
                  ]"
                >
                  <label class="form-label" for="investment-date">
                    {{ __('Date') }}
                  </label>
                  <input
                    id="investment-date"
                    v-model="dateInput"
                    type="date"
                    class="form-control"
                    :disabled="form.schedule"
                  />
                </div>
                <div
                  v-if="action === 'enter'"
                  class="col-12 col-sm-2 col-md-4 col-lg-2 mb-3 mb-sm-0 mb-md-3 mb-lg-0"
                >
                  <div class="form-check">
                    <input
                      id="checkbox-investment-catch-up-schedule"
                      v-model="form.catch_up_schedule"
                      class="form-check-input"
                      type="checkbox"
                      value="1"
                    />
                    <label
                      class="form-check-label"
                      for="checkbox-investment-catch-up-schedule"
                    >
                      {{ __('Skip to nearest future occurrence') }}
                      <i
                        class="fa fa-info-circle text-info"
                        data-bs-toggle="tooltip"
                        data-bs-placement="top"
                        :title="
                          __(
                            'Instead of the next date, also advances the schedule past any other occurrences still due, so its next occurrence is today or later.',
                          )
                        "
                      ></i>
                      <i
                        v-if="catchUpMayCloseSchedule"
                        class="fa fa-triangle-exclamation text-warning ms-1"
                        data-bs-toggle="tooltip"
                        data-bs-placement="top"
                        :title="
                          __(
                            'May close this schedule if no occurrences remain.',
                          )
                        "
                      ></i>
                    </label>
                  </div>
                </div>
                <div
                  class="col-12 col-sm-6 col-md-12 col-lg-6 col-sm-6 mb-0"
                  :class="form.errors.has('comment') ? 'has-error' : ''"
                >
                  <label for="investment-comment" class="form-label">
                    {{ __('Comment') }}
                  </label>
                  <input
                    id="investment-comment"
                    v-model="form.comment"
                    class="form-control"
                    maxlength="255"
                    type="text"
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
            <div class="card-header d-flex justify-content-between">
              <div class="card-title">
                {{ __('Details') }}
              </div>
              <span
                class="fa fa-info-circle text-info"
                data-bs-toggle="tooltip"
                data-bs-placement="right"
                :title="
                  __(
                    'The currency of the account and the investment must be the same. This will limit the account and investment options available in the dropdowns.',
                  )
                "
              ></span>
            </div>
            <div class="card-body">
              <div v-if="selectDataError" class="alert alert-danger mb-3">
                {{ __('Could not load the account and investment lists.') }}
                <button
                  type="button"
                  class="btn btn-sm btn-outline-danger ms-2"
                  @click="retrySelectData"
                >
                  {{ __('Retry') }}
                </button>
              </div>
              <div class="row">
                <div class="col-12 mb-3">
                  <div class="form-group">
                    <label for="account" class="form-label">
                      {{ __('Account') }}
                    </label>
                    <select
                      id="account"
                      class="form-select"
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
                      id="investment"
                      class="form-control"
                      style="width: 100% !important"
                    ></select>
                  </div>
                </div>
                <div v-if="reactivated.length > 0" class="col-12 mt-2">
                  <div class="form-check">
                    <input
                      id="checkbox-deactivate-reactivated"
                      v-model="deactivateAfterSave"
                      class="form-check-input"
                      type="checkbox"
                    />
                    <label
                      class="form-check-label"
                      for="checkbox-deactivate-reactivated"
                    >
                      {{
                        reactivated.length === 1
                          ? __('One-time use: keep item inactive')
                          : __('One-time use: keep items inactive')
                      }}
                    </label>
                  </div>
                </div>
              </div>
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
                <div class="col-md-4 mb-3">
                  <div class="form-group">
                    <label for="transaction_quantity" class="form-label">
                      {{ __('Quantity') }}
                    </label>
                    <MathInput
                      id="transaction_quantity"
                      v-model="form.config.quantity"
                      class="form-control"
                      :disabled="!transactionTypeSettings.quantity"
                    ></MathInput>
                  </div>
                </div>
                <div class="col-md-4 mb-3">
                  <div class="form-group focus-zoom">
                    <label for="transaction_price" class="form-label">
                      {{ __('Price') }}
                    </label>
                    <div class="input-group">
                      <span v-if="currency" class="input-group-text">
                        {{ getCurrencySymbol(locale, currency.iso_code) }}
                      </span>
                      <MathInput
                        id="transaction_price"
                        v-model="form.config.price"
                        class="form-control"
                        :disabled="!transactionTypeSettings.price"
                        @input="onPriceChange"
                      ></MathInput>
                      <span
                        v-if="existingPriceForDate !== null"
                        class="input-group-text existing-price-label"
                        :title="
                          __('Existing price for this date. Click to apply.')
                        "
                        data-bs-toggle="tooltip"
                        data-bs-placement="top"
                        @click="form.config.price = existingPriceForDate"
                        >{{
                          toFormattedCurrency(
                            existingPriceForDate,
                            locale,
                            investment_currency,
                          )
                        }}</span
                      >
                      <!-- Toggle checkbox as button addon -->
                      <input
                        v-if="
                          shouldShowStorePriceCheckbox &&
                          existingPriceForDate === null
                        "
                        id="store_price_checkbox"
                        v-model="storePriceEnabled"
                        class="btn-check"
                        type="checkbox"
                        autocomplete="off"
                      />
                      <label
                        v-if="
                          shouldShowStorePriceCheckbox &&
                          existingPriceForDate === null
                        "
                        class="btn btn-secondary"
                        :class="{
                          active: storePriceEnabled,
                        }"
                        for="store_price_checkbox"
                        :title="
                          __(
                            'Store this value as the investment price for this date',
                          )
                        "
                        data-bs-toggle="tooltip"
                        data-bs-placement="top"
                      >
                        {{ __('Store price') }}
                      </label>
                    </div>
                  </div>
                </div>
                <div class="col-md-4">
                  <div class="form-group">
                    <label for="transaction_dividend" class="form-label">
                      {{ __('Dividend') }}
                    </label>
                    <div class="input-group">
                      <span v-if="currency" class="input-group-text">
                        {{ getCurrencySymbol(locale, currency.iso_code) }}
                      </span>
                      <MathInput
                        id="transaction_dividend"
                        v-model="form.config.dividend"
                        class="form-control"
                        :disabled="!transactionTypeSettings.dividend"
                      ></MathInput>
                    </div>
                  </div>
                </div>
              </div>
              <div class="row">
                <div class="col-md-4 mb-3">
                  <div class="form-group">
                    <label for="transaction_commission" class="form-label">
                      {{ __('Commission') }}
                    </label>
                    <div class="input-group">
                      <span v-if="currency" class="input-group-text">
                        {{ getCurrencySymbol(locale, currency.iso_code) }}
                      </span>
                      <MathInput
                        id="transaction_commission"
                        v-model="form.config.commission"
                        class="form-control"
                      ></MathInput>
                    </div>
                  </div>
                </div>
                <div class="col-md-4 mb-3">
                  <div class="form-group">
                    <label for="transaction_tax" class="form-label">
                      {{ __('Tax') }}
                    </label>
                    <div class="input-group">
                      <span v-if="currency" class="input-group-text">
                        {{ getCurrencySymbol(locale, currency.iso_code) }}
                      </span>
                      <MathInput
                        id="transaction_tax"
                        v-model="form.config.tax"
                        class="form-control"
                      ></MathInput>
                    </div>
                  </div>
                </div>
                <div class="col-md-4">
                  <dl class="row">
                    <dt class="col-12 form-label">
                      {{ __('Total cashflow value') }}
                    </dt>
                    <dd class="col-12">
                      <span
                        v-if="currency"
                        class="me-1"
                        dusk="transaction-total-value"
                      >
                        {{ toFormattedCurrency(total, locale, currency) }}
                      </span>
                      <span v-else>
                        {{ total }}
                      </span>
                    </dd>
                  </dl>
                </div>
              </div>
              <div
                v-if="showSettlement"
                class="row border-top pt-3"
                dusk="settlement-block"
              >
                <div class="col-md-8 mb-2">
                  <label for="settlement_amount" class="form-label">
                    {{
                      total > 0
                        ? __('Settled into :account', {
                            account: settlementAccount.name,
                          })
                        : __('Funded from :account', {
                            account: settlementAccount.name,
                          })
                    }}
                  </label>
                  <div class="input-group">
                    <span class="input-group-text">
                      {{ getCurrencySymbol(locale, baseCurrency.iso_code) }}
                    </span>
                    <MathInput
                      id="settlement_amount"
                      v-model="settlementAmount"
                      class="form-control"
                      :class="{
                        'is-invalid': form.errors.has('settlement.amount'),
                      }"
                    ></MathInput>
                  </div>
                  <div
                    v-if="form.errors.has('settlement.amount')"
                    class="invalid-feedback d-block"
                  >
                    {{ form.errors.get('settlement.amount') }}
                  </div>
                  <div class="form-text">
                    {{
                      __(
                        'The amount the broker or bank booked in :currency. A transfer of exactly the total cashflow value is recorded with this transaction. Leave empty to skip.',
                        { currency: baseCurrency.iso_code },
                      )
                    }}
                  </div>
                </div>
                <div v-if="impliedRate" class="col-md-4">
                  <dl class="row">
                    <dt class="col-12 form-label">
                      {{ __('Implied exchange rate') }}
                    </dt>
                    <dd class="col-12" dusk="settlement-implied-rate">
                      1 {{ currency.iso_code }} = {{ impliedRate }}
                      {{ baseCurrency.iso_code }}
                    </dd>
                  </dl>
                </div>
              </div>
              <div
                v-if="existingSettlementTransferId"
                class="alert alert-info mb-0 mt-2"
              >
                {{
                  __(
                    'This transaction has a settlement transfer. If you change the amounts, update the transfer too.',
                  )
                }}
                <a
                  :href="
                    route('transaction.open', {
                      transaction: existingSettlementTransferId,
                      action: 'edit',
                    })
                  "
                  >{{ __('Edit settlement transfer') }}</a
                >
              </div>
            </div>
          </div>
        </div>
      </div>

      <transaction-schedule
        v-if="form.schedule"
        :is-schedule="form.schedule"
        :schedule="form.schedule_config"
        :form="form"
        @update:schedule="Object.assign(form.schedule_config, $event)"
      ></transaction-schedule>

      <transaction-schedule
        v-if="form.schedule && action === 'replace'"
        ref="scheduleOriginal"
        :with-checkbox="true"
        :title="__('Update base schedule')"
        :allow-customization="false"
        :is-schedule="form.schedule"
        :schedule="form.original_schedule_config"
        :form="form"
        field-prefix="original_schedule_config"
        @update:schedule="Object.assign(form.original_schedule_config, $event)"
      ></transaction-schedule>

      <div class="card mb-3">
        <div class="card-body">
          <div class="row justify-content-end">
            <div
              v-if="!fromModal && !isTemplate"
              class="d-none d-lg-block col-lg-12 col-xl-9 mb-3 mb-lg-3 mb-xl-0"
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
                  class="btn btn-outline-secondary"
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
              v-if="!fromModal && !isTemplate"
              class="col-12 col-sm-8 d-block d-lg-none mb-3 mb-sm-0"
            >
              <label
                class="form-label"
                for="callback-selector-mobile-investment"
              >
                {{ __('Action after saving') }}
              </label>
              <select
                id="callback-selector-mobile-investment"
                v-model="callback"
                class="form-control"
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
                class="btn btn btn-secondary"
                type="button"
                @click="onCancel"
              >
                {{ __('Cancel') }}
              </button>
              <SubmitButton
                id="transactionFormInvestment-Save"
                class="btn btn-primary ms-2"
                :disabled="form.busy"
                :form="form"
              >
                <span v-show="!form.busy" class="fa fa-save me-1"></span>
                {{ __('Save') }}
              </SubmitButton>
            </div>
          </div>
        </div>
      </div>
    </form>
  </div>
</template>

<script>
  import { RRule } from 'rrule';
  import Decimal from 'decimal.js';
  import MathInput from '@/shared/ui/form/MathInput.vue';
  import { confirmAction } from '@/shared/lib/confirm';

  import Form from 'vform';
  import templateForm from '@/transaction-templates/templateForm';
  import TemplateFields from '@/transaction-templates/components/TemplateFields.vue';
  import {
    Button as SubmitButton,
    AlertErrors,
  } from 'vform/src/components/bootstrap5';

  import TransactionSchedule from './TransactionSchedule.vue';

  import {
    processTransaction,
    toIsoDateString,
    initializeBootstrapTooltips,
    parseIsoDate,
    byDayToRRuleWeekday,
    toDateInputValue,
    toRRuleDate,
  } from '@/shared/lib/helpers';
  import {
    __,
    getCurrencySymbol,
    toFormattedCurrency,
  } from '@/shared/lib/i18n';
  import { markRaw } from 'vue';
  import {
    createLocalSelect,
    lockSelect,
    unlockSelect,
    setSelected,
    clearSelect,
  } from '@/shared/lib/tom-select';
  import {
    loadSelectData,
    upsertEntity,
    investmentUsage,
    investmentAccountUsage,
    rankByUsage,
    searchEntities,
    applySavedTransaction,
  } from '@/shared/lib/select-data';
  import * as toastHelpers from '@/shared/lib/toast';

  export default {
    components: {
      TemplateFields,
      TransactionSchedule,
      MathInput,
      SubmitButton,
      AlertErrors,
    },

    mixins: [templateForm],

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
        // - availability of callback options
        type: Boolean,
        default: false,
      },
      locale: {
        type: String,
        default: window.YAFFA.userSettings.locale,
      },
      aiDocumentId: {
        type: Number,
        default: null,
      },
      swalTargetSelector: {
        type: String,
        default: 'body',
      },
    },

    emits: ['cancel', 'success'],

    data() {
      let data = {};

      // True once the presets have settled and the isDirty() baseline is taken (see markFormClean())
      data.formLoaded = false;

      // Main form data
      data.form = new Form({
        transaction_type: 'buy',
        config_type: 'investment',
        date: toIsoDateString(),
        comment: null,
        schedule: false,
        reconciled: false,
        catch_up_schedule: false,
        config: {
          account_id: null,
          investment_id: null,
          quantity: null,
          price: null,
          commission: null,
          tax: null,
          dividend: null,
        },
        schedule_config: {
          frequency: 'DAILY',
          interval: 1,
        },
        ai_document_id: null,
        transaction_template_id: null,
        // Optional transfer to/from the account's settlement account, only sent with a new transaction
        settlement: null,
      });

      // Other values
      data.account_currency = null;
      data.investment_currency = null;
      data.settlementAmount = null;
      // Set on account selection from the (non-reactive) reference data: the account in the user's default
      // currency the selected account settles into, and that currency
      data.settlementAccount = null;
      data.baseCurrency = null;

      // Inactive accounts/investments reactivated from the dropdown for this transaction
      data.reactivated = [];
      data.deactivateAfterSave = true;
      data.selectDataError = false;

      // Finalizing an AI document: default to returning to the document list
      data.callback =
        this.action === 'finalize' && this.aiDocumentId !== null
          ? 'returnToAiDocuments'
          : this.initialCallback;

      // Store price feature related data
      data.storePriceEnabled = false;
      data.existingPriceForDate = null;
      data.priceCheckTimeout = null;

      // Possible callback options
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
          value: 'returnToInvestment',
          label: __('callback.returnToInvestment'),
          enabled: true,
        },
        {
          value: 'returnToAiDocuments',
          label: __('callback.returnToAiDocuments'),
          // Only when finalizing an AI document
          enabled: this.action === 'finalize' && this.aiDocumentId !== null,
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

      return data;
    },

    computed: {
      // Uses exact decimal arithmetic: config.price/commission/tax/dividend arrive
      // from the API as decimal strings (MoneyCast), which native `+` would silently
      // string-concatenate instead of add.
      total() {
        const quantity = new Decimal(this.form.config.quantity || 0);
        const price = new Decimal(this.form.config.price || 0);
        const dividend = new Decimal(this.form.config.dividend || 0);
        const commission = new Decimal(this.form.config.commission || 0);
        const tax = new Decimal(this.form.config.tax || 0);

        return quantity
          .times(price)
          .plus(dividend)
          .minus(
            commission
              .plus(tax)
              // Taxes and commissions are added to the value when the transaction is a buy
              .times(this.transactionTypeSettings.amount_multiplier || 0),
          )
          .toNumber();
      },

      transactionTypeSettings() {
        return (
          this.transactionTypes.find(
            (item) => item.value === this.form.transaction_type,
          ) || {}
        );
      },

      currency() {
        return this.account_currency || this.investment_currency;
      },

      // Only a new, unscheduled transaction that moves cash can be settled in the same step
      showSettlement() {
        return (
          !this.isTemplate &&
          ['create', 'clone', 'enter', 'finalize'].includes(this.action) &&
          !this.form.schedule &&
          this.settlementAccount !== null &&
          this.baseCurrency !== null &&
          this.currency !== null &&
          this.total !== 0
        );
      },

      // Default-currency units per foreign unit, from the entered amount and the cashflow
      impliedRate() {
        const amount = Number(this.settlementAmount);
        if (!this.showSettlement || !amount || !this.total) {
          return null;
        }
        return (amount / Math.abs(this.total)).toFixed(6);
      },

      existingSettlementTransferId() {
        return this.action === 'edit'
          ? (this.transaction?.settlement_transfer?.id ?? null)
          : null;
      },

      activeCallbackOptions() {
        return this.callbackOptions.filter((option) => option.enabled);
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
            byweekday: schedule.by_day
              ? byDayToRRuleWeekday(schedule.by_day)
              : null,
            bymonth: schedule.by_month || null,
          });

          return rule.after(toRRuleDate(new Date()), true) === null;
        } catch {
          return false;
        }
      },

      // Do we allow the user to edit the base settings?
      isBaseSettingsEditsAllowed() {
        return ['create', 'clone', 'finalize', 'template'].includes(
          this.action,
        );
      },

      // Should we show the "Store this as a price" checkbox?
      shouldShowStorePriceCheckbox() {
        return (
          ['create', 'clone', 'finalize'].includes(this.action) &&
          !this.form.schedule &&
          this.transactionTypeSettings.price &&
          // At the moment, overwriting is not supported
          !this.existingPriceForDate
        );
      },
    },

    watch: {
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

      // Check for existing price when date changes
      'form.date': function () {
        this.checkExistingPrice();
      },

      // Check for existing price when investment changes
      'form.config.investment_id': function () {
        this.checkExistingPrice();
      },

      transaction(transaction) {
        this.formLoaded = false;

        // TODO: consider using form.update()
        this.form.reset();

        // Copy values of existing transaction into component form data
        this.initializeTransaction();

        // Load default value for accounts
        const accountReady = this.getDefaultAccountDetails(
          transaction.config.account_id,
        );

        // Load default value for investment, or clear if not set
        let investmentReady;
        if (transaction.config.investment_id) {
          investmentReady = this.getDefaultInvestmentDetails(
            transaction.config.investment_id,
          );
        } else {
          this.clearInvestmentDropdown();
        }

        // Snapshot the settled post-load state as the isDirty() baseline - see
        // markFormClean(). The async presets above set config.account_id/investment_id
        // and the currencies that depend on them, so this must wait for both to settle
        // rather than snapshotting right after initializeTransaction().
        Promise.all([this.selectDataReady, accountReady, investmentReady]).then(
          () => {
            this.$nextTick(() => this.markFormClean());
          },
        );
      },

      existingPriceForDate(value) {
        if (value !== null) {
          this.$nextTick(() => {
            initializeBootstrapTooltips();
          });
        }
      },
    },

    created() {
      // Load transaction types from window global context first
      const transactionTypesConfig = window.YAFFA.config.transactionTypes || {};

      // Filter and map investment types to component format
      this.transactionTypes = Object.values(transactionTypesConfig)
        .filter((type) => type.category === 'investment')
        .map((type) => ({
          name: type.label,
          value: type.value,
          quantity: ['buy', 'sell', 'add_shares', 'remove_shares'].includes(
            type.value,
          ),
          price: ['buy', 'sell'].includes(type.value),
          dividend: ['dividend', 'interest_yield'].includes(type.value),
          amount_multiplier: type.amount_multiplier,
        }));

      // Copy values of existing transaction into component form data
      this.initializeTransaction();

      // Check for various default values in URL
      const urlParams = new URLSearchParams(window.location.search);
      if (urlParams.get('account')) {
        this.form.config.account_id = urlParams.get('account');
      }
      if (urlParams.get('investment')) {
        this.form.config.investment_id = urlParams.get('investment');
      }

      // Set form action
      this.form.action = this.action;
      this.form.ai_document_id = this.aiDocumentId;
      this.form.transaction_template_id = this.transactionTemplateId;

      // The shared reference data behind the selects (not reactive: it is read on demand)
      this.selectData = null;
      this.pendingActivations = [];
      this.loadReferenceData();
    },

    mounted() {
      this.accountSelect = markRaw(
        createLocalSelect(document.getElementById('account'), {
          suggestions: () => this.accountSuggestions(),
          search: (term) =>
            this.toItems(searchEntities(this.accountCandidates(true), term)),
          searchLabel: () => this.groupLabel('Matches', 'investment'),
          placeholder: __('Select account'),
          onSelect: (item) => this.onAccountPicked(item),
          onClear: () => {
            this.form.config.account_id = null;
            this.account_currency = null;
            this.settlementAccount = null;
          },
        }),
      );

      this.investmentSelect = markRaw(
        createLocalSelect(document.getElementById('investment'), {
          suggestions: () => this.investmentSuggestions(),
          // The symbol is searchable as the entity's alias
          search: (term) =>
            this.toItems(searchEntities(this.investmentCandidates(true), term)),
          searchLabel: () => this.groupLabel('Matches', 'account'),
          // Investment names and symbols are user data: always escaped
          renderOption: (data, escape) =>
            `<div>${escape(data.text)} <span class="text-muted">(${escape(data.symbol)})</span>` +
            (data.active === false
              ? ` <span class="badge text-bg-secondary ms-1">${escape(__('inactive'))}</span>`
              : '') +
            '</div>',
          placeholder: __('Select investment'),
          onSelect: (item) => this.onInvestmentPicked(item),
          onClear: () => {
            this.investment_currency = null;
            this.form.config.investment_id = null;
            // Reset price-related data when investment is cleared
            this.existingPriceForDate = null;
            this.storePriceEnabled = false;
          },
        }),
      );

      if (!this.selectData) {
        lockSelect(this.accountSelect, __('Loading…'));
        lockSelect(this.investmentSelect, __('Loading…'));
      }

      // Load default values for account and investment
      const accountReady = this.getDefaultAccountDetails(
        this.form.config.account_id,
      );
      const investmentReady = this.getDefaultInvestmentDetails(
        this.form.config.investment_id,
      );

      // Initial sync between schedules, if applicable
      this.syncScheduleStartDate(this.form.schedule_config.start_date);

      // Initialize tooltips
      initializeBootstrapTooltips();

      // Snapshot the settled post-load state (both account/investment details loaded
      // and synced into form.config) as the isDirty() baseline.
      Promise.all([this.selectDataReady, accountReady, investmentReady]).then(
        () => {
          this.$nextTick(() => this.markFormClean());
        },
      );
    },

    beforeUnmount() {
      this.accountSelect?.destroy();
      this.investmentSelect?.destroy();
    },

    methods: {
      // Load the shared reference data. Everything the selects show, and the currencies looked up on
      // selection, come from it - no per-click requests.
      loadReferenceData() {
        this.selectDataError = false;
        this.selectDataReady = loadSelectData().then(
          (data) => {
            this.selectData = data;
            if (this.accountSelect?.isLocked) {
              unlockSelect(this.accountSelect, __('Select account'));
              unlockSelect(this.investmentSelect, __('Select investment'));
            }
          },
          (error) => {
            this.selectDataError = true;
            throw error;
          },
        );
        // The rejection is surfaced through selectDataError; callers that chain on it handle it themselves
        this.selectDataReady.catch(() => {});

        return this.selectDataReady;
      },

      // Retry a failed load, then apply the presets that were waiting for it
      retrySelectData() {
        this.loadReferenceData().then(() =>
          Promise.all([
            this.getDefaultAccountDetails(this.form.config.account_id),
            this.getDefaultInvestmentDetails(this.form.config.investment_id),
          ]).then(() => {
            this.$nextTick(() => this.markFormClean());
          }),
        );
      },

      // Active accounts in the selected investment's currency (all, if none is selected). With
      // `withInactive`, inactive ones are included too: they are found by search and reactivated on pick.
      accountCandidates(withInactive = false) {
        if (!this.selectData) {
          return [];
        }
        const currencyId = this.investment_currency?.id;

        return this.selectData.accounts.filter(
          (a) =>
            (withInactive || a.active) &&
            (!currencyId || a.currency_id === currencyId),
        );
      },

      // Active investments in the selected account's currency (all, if none is selected)
      investmentCandidates(withInactive = false) {
        if (!this.selectData) {
          return [];
        }
        const currencyId = this.account_currency?.id;

        return this.selectData.investments
          .filter(
            (i) =>
              (withInactive || i.active) &&
              (!currencyId || i.currency_id === currencyId),
          )
          .map((i) => ({ ...i, alias: i.symbol }));
      },

      toItems(entities) {
        return entities.map((e) => ({ ...e, text: e.name }));
      },

      // "Other" / "Matches", with a suffix while the other side's currency limits the list
      groupLabel(label, otherSide) {
        const selected =
          otherSide === 'investment'
            ? this.investment_currency
            : this.account_currency;

        return selected
          ? `${__(label)} - ${
              otherSide === 'investment'
                ? __('matching investment currency')
                : __('matching account currency')
            }`
          : __(label);
      },

      rankedGroups(candidates, usageMap, otherSide) {
        const { mostUsed, recent, fill } = rankByUsage(candidates, usageMap);

        return [
          { id: 'most-used', label: __('Most used'), items: mostUsed },
          { id: 'recent', label: __('Recent'), items: recent },
          {
            id: 'other',
            label: this.groupLabel('Other', otherSide),
            items: fill,
          },
        ].map((g) => ({ ...g, items: this.toItems(g.items) }));
      },

      accountSuggestions() {
        if (!this.selectData) {
          return [];
        }

        return this.rankedGroups(
          this.accountCandidates(),
          investmentAccountUsage(this.selectData.usage, {
            investmentId: this.form.config.investment_id,
            transactionType: this.form.transaction_type,
          }),
          'investment',
        );
      },

      investmentSuggestions() {
        if (!this.selectData) {
          return [];
        }

        return this.rankedGroups(
          this.investmentCandidates(),
          investmentUsage(this.selectData.usage, {
            accountId: this.form.config.account_id,
          }),
          'account',
        );
      },

      // Store the selected account and take its currency from the preloaded data. Returns a promise, so
      // presets can wait for it before the form is treated as loaded.
      onAccountSelected(id) {
        this.form.config.account_id = Number(id);

        return this.selectDataReady.then(() => {
          const account = this.selectData.accounts.find(
            (a) => a.id === Number(id),
          );
          this.account_currency =
            this.selectData.currencies.get(account?.currency_id) ?? null;
          this.settlementAccount = account?.settlement_account_id
            ? (this.selectData.accounts.find(
                (a) => a.id === account.settlement_account_id,
              ) ?? null)
            : null;
          this.baseCurrency =
            [...this.selectData.currencies.values()].find((c) => c.base) ??
            null;
        });
      },

      onInvestmentSelected(id) {
        this.form.config.investment_id = Number(id);

        return this.selectDataReady.then(() => {
          const investment = this.selectData.investments.find(
            (i) => i.id === Number(id),
          );
          this.investment_currency =
            this.selectData.currencies.get(investment?.currency_id) ?? null;
        });
      },

      // A user's pick: an inactive entity is reactivated first
      onAccountPicked(item) {
        this.onAccountSelected(item.id);
        this.reactivateIfInactive(
          item,
          'accounts',
          this.accountSelect,
          () => {
            this.form.config.account_id = null;
            this.account_currency = null;
            this.settlementAccount = null;
          },
          () => this.form.config.account_id === Number(item.id),
        );
      },

      onInvestmentPicked(item) {
        this.onInvestmentSelected(item.id);
        this.reactivateIfInactive(
          item,
          'investments',
          this.investmentSelect,
          () => {
            this.form.config.investment_id = null;
            this.investment_currency = null;
          },
          () => this.form.config.investment_id === Number(item.id),
        );
      },

      setActive(kind, id, active) {
        const url =
          kind === 'investments'
            ? window.route('api.v1.investments.patch-active', {
                investment: id,
              })
            : window.route('api.v1.account-entities.patch-active', {
                accountEntity: id,
              });

        return window.axios
          .patch(url, { active })
          .then(() => upsertEntity(kind, { id, active }));
      },

      reactivateIfInactive(item, kind, select, reset, isStillSelected) {
        if (item.active !== false) {
          return;
        }
        const id = Number(item.id);

        // Tracked, so onSubmit() can wait for it: restoreReactivated() must see this entity
        const activation = this.setActive(kind, id, true)
          .then(() => this.reactivated.push({ id, kind, name: item.text }))
          .catch(() => {
            toastHelpers.showErrorToast(
              __('Could not reactivate :name.', { name: item.text }),
            );
            // A newer pick must stay untouched
            if (isStillSelected()) {
              select.clear(true);
              reset();
            }
          });
        this.pendingActivations.push(activation);
      },

      // Put back the entities reactivated for this transaction, once it is saved
      restoreReactivated() {
        if (this.deactivateAfterSave) {
          this.reactivated.forEach(({ id, kind }) =>
            this.setActive(kind, id, false).catch(() => {}),
          );
        }
        this.reactivated = [];
      },

      notifyReactivatedStayActive() {
        if (this.reactivated.length > 0 && this.deactivateAfterSave) {
          toastHelpers.showInfoToast(
            __(
              'Reactivated items stay active, because the transaction was not saved.',
            ),
          );
        }
      },

      getDefaultAccountDetails(account_id) {
        if (!account_id) {
          return;
        }

        // Returned so callers can wait for the preset to settle before treating the form as loaded.
        return this.selectDataReady.then(() => {
          const account = this.selectData.accounts.find(
            (a) => a.id === Number(account_id),
          );
          if (!account) {
            return;
          }
          setSelected(
            this.accountSelect,
            { id: account.id, text: account.name },
            { silent: true },
          );

          return this.onAccountSelected(account.id);
        });
      },

      getDefaultInvestmentDetails(investment_id) {
        if (!investment_id) {
          return;
        }

        return this.selectDataReady.then(() => {
          const investment = this.selectData.investments.find(
            (i) => i.id === Number(investment_id),
          );
          if (!investment) {
            return;
          }
          setSelected(
            this.investmentSelect,
            {
              id: investment.id,
              text: investment.name,
              symbol: investment.symbol,
            },
            { silent: true },
          );

          return this.onInvestmentSelected(investment.id);
        });
      },

      initializeTransaction() {
        if (this.transaction && Object.keys(this.transaction).length > 0) {
          // Populate form data with already known values
          this.form.id = this.transaction.id;
          this.form.transaction_type = this.transaction.transaction_type;

          // Populate date from source transaction as a plain 'YYYY-MM-DD' string - a Date
          // object here would be re-expressed in UTC by JSON.stringify() on submit, shifting
          // the calendar day for anyone east of UTC unless the field happens to get touched.
          this.form.date = toDateInputValue(this.transaction.date) || null;

          this.form.comment = this.transaction.comment;
          this.form.schedule = this.transaction.schedule ?? false;
          this.form.reconciled = this.transaction.reconciled ?? false;

          // Copy configuration (handle both saved transactions and AI drafts)
          const config = this.transaction.config || {};
          // These may arrive as fixed-scale decimal strings (e.g. "12.5000") - Number()
          // drops the trailing zeros, the same way MathInput normalizes typed values.
          ['quantity', 'price', 'commission', 'tax', 'dividend'].forEach(
            (field) => {
              const value = config[field];
              this.form.config[field] = [null, undefined, ''].includes(value)
                ? value
                : Number(value);
            },
          );
          this.form.config.account_id = config.account_id;
          this.form.config.investment_id = config.investment_id;

          // Copy schedule config. Dates are kept as plain 'YYYY-MM-DD' strings, never
          // Date objects - a Date survives fine in the UI (toDateInputValue reads it with
          // local getters), but JSON.stringify() on submit serializes it via toISOString(),
          // which re-expresses it in UTC and can shift the calendar day back by one for
          // anyone east of UTC unless the field happens to get touched (which replaces it
          // with a string via the date input's setter, masking the bug).
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

            this.form.schedule_config.start_date =
              toDateInputValue(
                this.transaction.transaction_schedule.start_date,
              ) || null;
            this.form.schedule_config.next_date =
              toDateInputValue(
                this.transaction.transaction_schedule.next_date,
              ) || null;
            this.form.schedule_config.automatic_recording =
              this.transaction.transaction_schedule.automatic_recording;
            this.form.schedule_config.end_date =
              toDateInputValue(
                this.transaction.transaction_schedule.end_date,
              ) || null;

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
            // Already a plain string at this point (see the schedule_config copy above).
            this.form.original_schedule_config.start_date =
              this.form.schedule_config.start_date;
            this.form.original_schedule_config.automatic_recording =
              this.form.schedule_config.automatic_recording;

            // Reset next date of original schedule config to set it ended
            this.form.original_schedule_config.next_date = undefined;

            // Set new schedule start date to today
            this.form.schedule_config.start_date = toIsoDateString();

            // If this is a schedule, then set the new next date to today
            if (this.form.schedule) {
              this.form.schedule_config.next_date = toIsoDateString();
            }

            // The end date carried over from the original schedule may now be
            // in the past relative to the new start date - the new schedule
            // can't already be over before its first occurrence, so clear it.
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
            const originalEndDate = new Date();
            originalEndDate.setDate(originalEndDate.getDate() - 1);
            this.form.original_schedule_config.end_date =
              toIsoDateString(originalEndDate);
          }
        }

        // Set form action and AI document ID
        this.form.action = this.action;
        this.form.ai_document_id = this.aiDocumentId;
        this.form.transaction_template_id = this.transactionTemplateId;

        // The originalData snapshot itself is taken once the account/investment details
        // requested by the caller (mounted()/the transaction watcher) have finished
        // loading - see markFormClean() and its call sites. Assigning config.account_id
        // above doesn't keep form.originalData in sync the way form.update() does, and
        // the async presets that follow set it again, together with the currencies.
      },

      // Snapshot the current state as the "clean" baseline isDirty() compares against.
      markFormClean() {
        this.form.update(this.form.data());
        this.formLoaded = true;
      },

      // The select is not v-model bound: the type only changes once the user accepted the data loss
      transactionTypeChanged(event) {
        const newType = event.target.value;
        const settings = this.transactionTypes.find((t) => t.value === newType);
        const lostFields = ['quantity', 'price', 'dividend'].filter(
          (field) =>
            !settings[field] &&
            ![null, undefined, ''].includes(this.form.config[field]),
        );

        const apply = () => {
          this.form.transaction_type = newType;
          lostFields.forEach((field) => (this.form.config[field] = null));
        };

        if (lostFields.length === 0) {
          apply();
          return;
        }

        confirmAction(
          __(
            'Are you sure, you want to change the transaction type? Some data might get lost.',
          ),
          { icon: 'warning', target: this.swalTargetSelector },
        ).then((result) => {
          if (result.isConfirmed) {
            apply();
          } else {
            event.target.value = this.form.transaction_type;
          }
        });
      },

      loadCallbackUrl(transactionId) {
        if (this.callback === 'returnToDashboard') {
          location.href = this.route('home');
          return;
        }

        if (this.callback === 'new') {
          location.href = this.route('transaction.create', {
            type: 'investment',
          });
          return;
        }

        if (this.callback === 'clone') {
          location.href = this.route('transaction.open', {
            transaction: transactionId,
            action: 'clone',
          });
          return;
        }

        if (this.callback === 'returnToPrimaryAccount') {
          location.href = this.route('account.history', {
            account: this.form.config.account_id,
          });
          return;
        }

        if (this.callback === 'returnToSecondaryAccount') {
          location.href = this.route('account.history', {
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
        if (!this.isDirty()) {
          this.notifyReactivatedStayActive();
          this.$emit('cancel');
          return false;
        }

        confirmAction(__('Are you sure you want to discard any changes?'), {
          icon: 'warning',
          target: this.swalTargetSelector,
        }).then((result) => {
          if (result.isConfirmed) {
            this.notifyReactivatedStayActive();
            this.$emit('cancel');
          }
        });
        return false;
      },

      // True once any field differs from its state right after the form finished
      // loading (new/cloned/editing/finalizing, etc - see the originalData snapshot
      // at the end of initializeTransaction()).
      isDirty() {
        return (
          JSON.stringify(this.form.data()) !==
          JSON.stringify(this.form.originalData)
        );
      },

      // Only the filled fields, in the draft format shared with AI documents (schema v2)
      buildTemplateDraft() {
        const config = this.form.config;

        return {
          schema_version: 2,
          config_type: 'investment',
          transaction_type: this.form.transaction_type,
          ...this.compactDraftPart({ comment: this.form.comment }),
          config: this.compactDraftPart({
            account_id: this.draftId(config.account_id),
            investment_id: this.draftId(config.investment_id),
            quantity: this.draftDecimal(config.quantity),
            price: this.draftDecimal(config.price),
            commission: this.draftDecimal(config.commission),
            tax: this.draftDecimal(config.tax),
            dividend: this.draftDecimal(config.dividend),
          }),
        };
      },

      async onSubmit() {
        // Let in-flight reactivations settle (they never reject; failures clear the selection)
        await Promise.all(this.pendingActivations);

        if (this.isTemplate) {
          this.saveTemplate(this.buildTemplateDraft());
          return;
        }

        // Editing an existing transaction needs PATCH method
        if (this.action === 'edit') {
          this.form
            .patch(
              this.route('api.v1.transactions.update-investment', {
                transaction: this.form.id,
              }),
              this.form,
            )
            .then((response) => {
              this.restoreReactivated();
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

        // Only sent when the settlement field is shown and filled in
        this.form.settlement =
          this.showSettlement &&
          this.settlementAmount !== null &&
          this.settlementAmount !== ''
            ? { amount: this.settlementAmount }
            : null;

        // Any type of new transaction needs POST method
        this.form
          .post(this.route('api.v1.transactions.store-investment'), this.form)
          .then(async (response) => {
            // Count it into the suggestions, so the next entry in this page session ranks correctly
            if (this.selectData) {
              applySavedTransaction(this.selectData.usage, {
                ...response.data.transaction,
                config_type: 'investment',
              });
            }
            this.restoreReactivated();

            if (response.data.settlement_transaction) {
              toastHelpers.showSuccessToast(__('Settlement transfer recorded'));
            }
            this.settlementAmount = null;

            // Store price if enabled
            const investmentPriceStoredResult = await this.storePriceIfEnabled(
              response.data.transaction,
            );

            this.$emit(
              'success',
              processTransaction(response.data.transaction),
              {
                callback: this.callback,
                investmentPriceStoredResult: investmentPriceStoredResult,
              },
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

        const date = parseIsoDate(newDate);
        if (!date) {
          return;
        }
        date.setDate(date.getDate() - 1);
        this.form.original_schedule_config.end_date = toIsoDateString(date);
      },

      // Clear the investment dropdown (used when resetting the form in modal context)
      clearInvestmentDropdown() {
        clearSelect(this.investmentSelect, { silent: true });
        this.form.config.investment_id = null;
        this.investment_currency = null;
      },

      async checkExistingPrice() {
        if (
          !this.form.config.investment_id ||
          !this.form.date ||
          !this.shouldShowStorePriceCheckbox
        ) {
          this.existingPriceForDate = null;
          return;
        }

        try {
          // Ensure date is properly formatted
          let dateString;
          if (this.form.date instanceof Date) {
            dateString = toIsoDateString(this.form.date);
          } else if (typeof this.form.date === 'string') {
            // If it's already a string, use it directly
            dateString = this.form.date;
          } else {
            // Fallback to today if date is invalid
            dateString = toIsoDateString(new Date());
          }

          const response = await window.axios.get(
            this.route('api.v1.investment-prices.check', {
              investment: this.form.config.investment_id,
            }),
            {
              params: {
                date: dateString,
              },
            },
          );

          if (response.data.exists) {
            // The API emits price as a decimal string (MoneyCast); toFormattedCurrency()
            // (used to display it below) requires a plain Number to format correctly.
            this.existingPriceForDate = Number(response.data.price);
            this.storePriceEnabled = false;
          } else {
            this.existingPriceForDate = null;
          }
        } catch (error) {
          console.error('Failed to check existing price:', error);
          this.existingPriceForDate = null;
        }
      },

      onPriceChange() {
        // Debounce price check
        if (this.priceCheckTimeout) {
          clearTimeout(this.priceCheckTimeout);
        }

        this.priceCheckTimeout = setTimeout(() => {
          this.checkExistingPrice();
        }, 500);
      },

      async storePriceIfEnabled(transaction) {
        if (!this.storePriceEnabled || !transaction.config.price) {
          return;
        }

        try {
          await window.axios.post(
            this.route('api.v1.investment-prices.store'),
            {
              investment_id: transaction.config.investment_id,
              date: transaction.date.split('T')[0], // At this point, date is in ISO format with time
              price: transaction.config.price,
            },
          );

          // Show success toast
          return 'success';
        } catch (error) {
          // If duplicate (422), show warning instead of error
          if (error.response && error.response.status === 422) {
            return 'skipped';
          } else {
            return 'error';
          }
        }
      },
      getCurrencySymbol,
      toFormattedCurrency,
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
