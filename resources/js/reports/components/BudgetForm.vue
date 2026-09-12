<template>
  <FormModal
    ref="formModal"
    :id="id"
    size="lg"
    :action="action"
    :new-title="__('Add new budget')"
    :edit-title="__('Edit budget')"
    :replace-title="__('Edit and create new budget period')"
    :form="form"
    :show-success-alert="false"
    @submit="onSubmit"
  >
            <div class="row mb-3">
              <label :for="categorySelectId" class="form-label col-sm-3">
                {{ __('Category') }}
              </label>
              <div class="col-sm-9">
                <select
                  :id="categorySelectId"
                  class="form-select category"
                  style="width: 100%"
                ></select>
              </div>
            </div>

            <div class="row mb-3">
              <label :for="accountSelectId" class="form-label col-sm-3">
                {{ __('Account') }}
                <i
                  class="fa fa-info-circle text-info"
                  :title="
                    __(
                      'Optional. Leave empty for an account-agnostic budget, calculated in your base currency.',
                    )
                  "
                  data-bs-toggle="tooltip"
                ></i>
              </label>
              <div class="col-sm-9">
                <select
                  :id="accountSelectId"
                  class="form-select account"
                  style="width: 100%"
                ></select>
              </div>
            </div>

            <div class="row mb-3">
              <label class="form-label col-sm-3">
                {{ __('Transaction type') }}
              </label>
              <div
                class="col-sm-9 btn-group"
                role="group"
                :class="{ 'has-error': form.errors.has('transaction_type') }"
              >
                <input
                  class="btn-check"
                  type="radio"
                  autocomplete="off"
                  value="withdrawal"
                  :id="withdrawalRadioId"
                  v-model="form.transaction_type"
                />
                <label class="btn btn-outline-primary" :for="withdrawalRadioId">
                  <span class="fa fa-circle-minus text-danger"></span><br />
                  {{ __('Withdrawal') }}
                </label>
                <input
                  class="btn-check"
                  type="radio"
                  autocomplete="off"
                  value="deposit"
                  :id="depositRadioId"
                  v-model="form.transaction_type"
                />
                <label class="btn btn-outline-primary" :for="depositRadioId">
                  <span class="fa fa-circle-plus text-success"></span><br />
                  {{ __('Deposit') }}
                </label>
              </div>
            </div>

            <div class="row mb-3">
              <label :for="amountInputId" class="form-label col-sm-3">
                {{ __('Amount') }}
              </label>
              <div class="col-sm-9">
                <div class="input-group">
                  <input
                    class="form-control"
                    :id="amountInputId"
                    type="number"
                    step="any"
                    min="0"
                    v-model="form.amount"
                    :class="{ 'has-error': form.errors.has('amount') }"
                  />
                  <span class="input-group-text">{{ currencyCode }}</span>
                </div>
              </div>
            </div>

            <div class="row mb-3">
              <label :for="commentInputId" class="form-label col-sm-3">
                {{ __('Comment') }}
              </label>
              <div class="col-sm-9">
                <input
                  class="form-control"
                  :id="commentInputId"
                  maxlength="255"
                  type="text"
                  v-model="form.comment"
                />
              </div>
            </div>

            <transaction-schedule
              :isSchedule="false"
              :isBudget="true"
              :schedule="form"
              :form="form"
              fieldPrefix=""
              bare
              key="budget-period"
            ></transaction-schedule>

            <transaction-schedule
              v-if="action === 'replace' && form.original_schedule_config"
              :isSchedule="false"
              :isBudget="true"
              :withCheckbox="true"
              :title="__('Update base period')"
              :allowCustomization="false"
              :schedule="form.original_schedule_config"
              :form="form"
              fieldPrefix="original_schedule_config"
              ref="scheduleOriginal"
              key="budget-period-original"
            ></transaction-schedule>
  </FormModal>
</template>

<script>
  import { initializeSelect2 } from '@/shared/lib/select2';
  initializeSelect2(window.YAFFA.userSettings.language);

  import Form from 'vform';

  import FormModal from '@/shared/ui/FormModal.vue';
  import TransactionSchedule from '@/transactions/components/form/TransactionSchedule.vue';
  import { __ } from '@/shared/lib/i18n';
  import {
    toIsoDateString,
    toDateInputValue,
    parseIsoDate,
  } from '@/shared/lib/helpers';

  export default {
    components: {
      FormModal,
      TransactionSchedule,
    },

    props: {
      action: String,
      id: {
        type: String,
        default: 'newBudgetModal',
      },
      instanceId: {
        type: String,
        default: null,
      },
    },

    emits: ['budgetSaved'],

    data() {
      return {
        form: new Form({
          category_id: null,
          account_id: null,
          transaction_type: 'withdrawal',
          amount: null,
          comment: null,
          frequency: 'MONTHLY',
          interval: 1,
          by_day: null,
          by_month: null,
          start_date: null,
          end_date: null,
          count: null,
          inflation: null,
        }),
        budgetId: null,
        categorySelect: null,
        accountSelect: null,
        modal: undefined,
        // The selected account's own currency (iso_code), or null for an account-agnostic
        // budget, which is always priced in the base currency (FR-4).
        accountCurrencyCode: null,
        // True while an account is selected but its currency hasn't loaded (yet, or failed) -
        // keeps the amount suffix from falsely showing the base currency in that window.
        accountCurrencyPending: false,
      };
    },

    computed: {
      formInstanceId() {
        return this.instanceId || this.id;
      },
      currencyCode() {
        if (this.accountCurrencyPending) {
          return '';
        }

        return this.accountCurrencyCode || window.YAFFA.userSettings.baseCurrency?.iso_code;
      },
      categorySelectId() {
        return `${this.formInstanceId}-category_id`;
      },
      accountSelectId() {
        return `${this.formInstanceId}-account_id`;
      },
      withdrawalRadioId() {
        return `${this.formInstanceId}-transaction_type_withdrawal`;
      },
      depositRadioId() {
        return `${this.formInstanceId}-transaction_type_deposit`;
      },
      amountInputId() {
        return `${this.formInstanceId}-amount`;
      },
      commentInputId() {
        return `${this.formInstanceId}-comment`;
      },
      formUrl() {
        if (this.budgetId === null) {
          return null;
        }

        return route('api.v1.budgets.update', { budget: this.budgetId });
      },
    },

    watch: {
      // On change of the new period's start date, adjust the source period's closing end
      // date to the previous day.
      'form.start_date': function (newDate) {
        this.syncScheduleStartDate(newDate);
      },
    },

    mounted() {
      this.initializeCategorySelect();
      this.initializeAccountSelect();
    },

    methods: {
      show(budgetId = null) {
        this.resetForm();

        if (budgetId !== null) {
          this.loadBudgetData(budgetId);
        }

        this.$refs.formModal.show();
      },

      initializeCategorySelect() {
        this.categorySelect = $(this.$el).find(`#${this.categorySelectId}`);

        this.categorySelect
          .select2({
            language: window.YAFFA.userSettings.language,
            theme: 'bootstrap-5',
            ajax: {
              url: '/api/v1/categories',
              dataType: 'json',
              delay: 150,
              data: function (params) {
                return {
                  _token: csrfToken,
                  q: params.term || '*',
                  withInactive: true,
                };
              },
              processResults: function (data) {
                const results = Array.isArray(data) ? data : data.data || [];

                return {
                  results: results.map(function (item) {
                    return {
                      id: item.id,
                      text: item.full_name,
                    };
                  }),
                };
              },
              cache: true,
            },
            selectOnClose: false,
            placeholder: __('Select category'),
            allowClear: true,
            dropdownParent: $('#' + this.id),
          })
          .on('select2:select select2:unselect', () => {
            const selectedValue = this.categorySelect.val();

            this.form.category_id =
              selectedValue === null || selectedValue === ''
                ? null
                : Number(selectedValue);
          });
      },

      initializeAccountSelect() {
        this.accountSelect = $(this.$el).find(`#${this.accountSelectId}`);

        this.accountSelect
          .select2({
            language: window.YAFFA.userSettings.language,
            theme: 'bootstrap-5',
            ajax: {
              url: '/api/v1/accounts',
              dataType: 'json',
              delay: 150,
              data: function (params) {
                return {
                  _token: csrfToken,
                  q: params.term || '',
                  limit: 0,
                };
              },
              processResults: function (data) {
                const results = Array.isArray(data) ? data : data.data || [];

                return {
                  results: results.map(function (item) {
                    return {
                      id: item.id,
                      text: item.name,
                    };
                  }),
                };
              },
              cache: true,
            },
            selectOnClose: false,
            placeholder: __(
              'No account (base currency, account-agnostic)',
            ),
            allowClear: true,
            dropdownParent: $('#' + this.id),
          })
          .on('select2:select select2:unselect', () => {
            const selectedValue = this.accountSelect.val();

            this.form.account_id =
              selectedValue === null || selectedValue === ''
                ? null
                : Number(selectedValue);

            this.updateAccountCurrency(this.form.account_id);
          });
      },

      // Reflects the selected account's own currency in the amount field's suffix (FR-4: a
      // budget's currency is never stored, always derived from its account, or the base
      // currency when account-agnostic) - fetched on demand rather than carried on the
      // lightweight select2 search results, which don't include the account's currency.
      updateAccountCurrency(accountId) {
        if (!accountId) {
          this.accountCurrencyCode = null;
          this.accountCurrencyPending = false;
          return;
        }

        this.accountCurrencyPending = true;

        fetch(route('api.v1.accounts.show', { accountEntity: accountId }))
          .then((response) => {
            if (!response.ok) {
              throw new Error('Failed to load account currency');
            }

            return response.json();
          })
          .then((data) => {
            this.accountCurrencyCode = data?.config?.currency?.iso_code ?? null;
            this.accountCurrencyPending = false;
          })
          .catch(() => {
            this.accountCurrencyCode = null;
            this.accountCurrencyPending = true;
          });
      },

      setSelectValue(selectElement, item, textField = 'name') {
        if (!selectElement || !item) {
          return;
        }

        const option = new Option(item[textField], item.id, true, true);
        selectElement.append(option).trigger('change');
      },

      loadBudgetData(budgetId) {
        this.budgetId = budgetId;

        fetch(route('api.v1.budgets.show', { budget: budgetId }))
          .then((response) => {
            if (!response.ok) {
              throw new Error('Failed to load budget data');
            }
            return response.json();
          })
          .then((data) => {
            this.form.category_id = data.category_id;
            this.form.account_id = data.account_id;
            this.form.transaction_type = data.transaction_type;
            // The API returns the raw MoneyCast decimal string (fixed 4-decimal scale) - round
            // it down to the currency's own generic precision so the amount field doesn't show
            // e.g. "100000,0000" for a currency (HUF) that has no fractional unit at all.
            const currency =
              data.account?.config?.currency || window.YAFFA.userSettings.baseCurrency;
            const precision = currency?.generic_decimal_precision ?? 0;
            this.form.amount =
              data.amount !== null && data.amount !== undefined
                ? Number(parseFloat(data.amount).toFixed(precision))
                : data.amount;
            this.form.comment = data.comment;
            this.form.frequency = data.frequency;
            this.form.interval = data.interval;
            this.form.by_day = data.by_day;
            this.form.by_month = data.by_month;
            this.form.start_date = data.start_date;
            this.form.end_date = data.end_date;
            this.form.count = data.count;
            this.form.inflation = data.inflation;

            this.categorySelect.empty();
            if (data.category) {
              this.setSelectValue(this.categorySelect, data.category, 'full_name');
            }

            this.accountSelect.empty();
            if (data.account) {
              this.setSelectValue(this.accountSelect, data.account, 'name');
            }

            this.accountCurrencyCode = data.account?.config?.currency?.iso_code ?? null;
            this.accountCurrencyPending = false;

            // 'replace' (mirroring TransactionFormStandard's schedule-clone flow): this
            // budget is the *source* being closed out, not the one being edited - duplicate
            // its just-loaded recurrence into original_schedule_config (which will close on
            // save), then move the new period's own start date to today.
            if (this.action === 'replace') {
              this.form.id = budgetId;

              this.form.original_schedule_config = {
                frequency: this.form.frequency,
                interval: this.form.interval,
                by_day: this.form.by_day,
                by_month: this.form.by_month,
                count: this.form.count,
                inflation: this.form.inflation,
                start_date: this.form.start_date,
              };

              this.form.start_date = toIsoDateString();

              // The end date carried over from the source period may now be in the past
              // relative to the new start date - the new period can't already be over
              // before its first occurrence, so clear it.
              const newEndDateValue = toDateInputValue(this.form.end_date);
              const newStartDateValue = toDateInputValue(this.form.start_date);
              if (newEndDateValue && newEndDateValue < newStartDateValue) {
                this.form.end_date = null;
              }

              // Close the source period the day before the new one starts.
              const originalEndDate = new Date();
              originalEndDate.setDate(originalEndDate.getDate() - 1);
              this.form.original_schedule_config.end_date =
                toIsoDateString(originalEndDate);
            }

            // The freshly-loaded values are the "clean" baseline for the
            // dirty check in FormModal, not the blank values the Form was
            // constructed with.
            this.form.originalData = JSON.parse(
              JSON.stringify(this.form.data()),
            );
          })
          .catch((error) => {
            console.error('Error loading budget:', error);
            this.form.errors.set({
              general: __('Failed to load budget data'),
            });
          });
      },

      resetForm() {
        this.form.reset();
        this.form.errors.clear();

        this.form.transaction_type = 'withdrawal';
        this.form.frequency = 'MONTHLY';
        this.form.interval = 1;
        this.form.start_date = null;
        this.form.end_date = null;
        this.form.count = null;
        this.form.inflation = null;
        this.form.action = this.action;
        this.form.id = null;
        this.form.original_schedule_config = null;
        this.accountCurrencyCode = null;
        this.accountCurrencyPending = false;

        if (this.categorySelect) {
          this.categorySelect.empty().val(null).trigger('change');
        }
        if (this.accountSelect) {
          this.accountSelect.empty().val(null).trigger('change');
        }

        this.budgetId = null;
      },

      // Keeps the source period's closing end date one day behind the new period's start
      // date as the user edits it live, mirroring TransactionFormStandard's
      // syncScheduleStartDate() - unless the "Customize" checkbox has been used to take
      // manual control of the source period.
      syncScheduleStartDate(newDate) {
        if (
          !newDate ||
          !this.form.original_schedule_config ||
          !this.$refs.scheduleOriginal ||
          this.$refs.scheduleOriginal.allowCustomizationData
        ) {
          return;
        }
        const date = parseIsoDate(newDate);
        if (!date) {
          return;
        }
        const endDate = new Date(date);
        endDate.setDate(endDate.getDate() - 1);
        this.form.original_schedule_config.end_date = toIsoDateString(endDate);
      },

      processAfterSubmit(response) {
        setTimeout(() => this.hideAndReset(), 1000);

        this.$emit('budgetSaved', response.data);
      },

      hideAndReset() {
        this.resetForm();
        this.$refs.formModal.hide();
      },

      onSubmit() {
        this.form.action = this.action;

        // 'new' and 'replace' both create a budget row via the store endpoint; 'replace'
        // additionally closes the source budget named by form.id/original_schedule_config
        // (see BudgetService::replace()). Only 'edit' patches the existing row in place.
        if (this.action === 'edit') {
          if (this.formUrl === null) {
            this.form.errors.set({
              general: __('Failed to determine API endpoint'),
            });

            return;
          }

          this.form
            .patch(this.formUrl, this.form)
            .then((response) => this.processAfterSubmit(response));

          return;
        }

        this.form
          .post(route('api.v1.budgets.store'), this.form)
          .then((response) => this.processAfterSubmit(response));
      },
      __,
    },
  };
</script>
