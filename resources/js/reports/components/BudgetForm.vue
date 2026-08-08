<template>
  <div class="modal" tabindex="-1" :id="id">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <form
          accept-charset="UTF-8"
          @submit.prevent="onSubmit"
          autocomplete="off"
        >
          <div class="modal-header">
            <h5 class="modal-title" v-if="action == 'new'">
              {{ __('Add new budget') }}
            </h5>
            <h5 class="modal-title" v-if="action == 'edit'">
              {{ __('Edit budget') }}
            </h5>
            <button
              type="button"
              class="btn-close"
              data-coreui-dismiss="modal"
              aria-label="Close"
            ></button>
          </div>
          <div class="modal-body">
            <AlertErrors
              :form="form"
              :message="__('There were some problems with your input.')"
            />
            <AlertSuccess
              :form="form"
              :message="__('Your changes have been saved!')"
            />

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
                  class="fa fa-info-circle text-primary"
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
                <label class="btn btn-outline-dark" :for="withdrawalRadioId">
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
                <label class="btn btn-outline-dark" :for="depositRadioId">
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
          </div>
          <div class="modal-footer">
            <button
              type="button"
              class="btn btn-default"
              data-coreui-dismiss="modal"
            >
              {{ __('Close') }}
            </button>
            <Button
              class="btn btn-primary"
              :disabled="form.busy"
              :form="form"
              >{{ __('Save') }}</Button
            >
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script>
  import { initializeSelect2 } from '@/shared/lib/select2';
  initializeSelect2(window.YAFFA.userSettings.language);

  import Form from 'vform';
  import {
    Button,
    AlertErrors,
    AlertSuccess,
  } from 'vform/src/components/bootstrap5';

  import TransactionSchedule from '@/transactions/components/form/TransactionSchedule.vue';
  import { __ } from '@/shared/lib/i18n';

  export default {
    components: {
      Button,
      AlertErrors,
      AlertSuccess,
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
        // The selected account's own currency (iso_code), or null for an account-agnostic
        // budget, which is always priced in the base currency (FR-4).
        accountCurrencyCode: null,
      };
    },

    computed: {
      formInstanceId() {
        return this.instanceId || this.id;
      },
      currencyCode() {
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

    mounted() {
      this.initializeCategorySelect();
      this.initializeAccountSelect();

      this.modal = new coreui.Modal(document.getElementById(this.id));
    },

    methods: {
      show(budgetId = null) {
        this.resetForm();

        if (budgetId !== null) {
          this.loadBudgetData(budgetId);
        }

        this.modal.show();
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
          return;
        }

        fetch(route('api.v1.accounts.show', { accountEntity: accountId }))
          .then((response) => (response.ok ? response.json() : null))
          .then((data) => {
            this.accountCurrencyCode = data?.config?.currency?.iso_code ?? null;
          })
          .catch(() => {
            this.accountCurrencyCode = null;
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
            this.form.amount = data.amount;
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
        this.accountCurrencyCode = null;

        if (this.categorySelect) {
          this.categorySelect.empty().val(null).trigger('change');
        }
        if (this.accountSelect) {
          this.accountSelect.empty().val(null).trigger('change');
        }

        this.budgetId = null;
      },

      processAfterSubmit(response) {
        setTimeout(() => this.hideAndReset(), 1000);

        this.$emit('budgetSaved', response.data);
      },

      hideAndReset() {
        this.resetForm();
        this.modal.hide();
      },

      onSubmit() {
        if (this.action === 'new') {
          this.form
            .post(route('api.v1.budgets.store'), this.form)
            .then((response) => this.processAfterSubmit(response));
        } else {
          if (this.formUrl === null) {
            this.form.errors.set({
              general: __('Failed to determine API endpoint'),
            });

            return;
          }

          this.form
            .patch(this.formUrl, this.form)
            .then((response) => this.processAfterSubmit(response));
        }
      },
      __,
    },
  };
</script>
