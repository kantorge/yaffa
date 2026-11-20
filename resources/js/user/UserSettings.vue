<template>
  <div id="userSettingsForm" class="card">
    <form
      accept-charset="UTF-8"
      autocomplete="off"
      @submit.prevent="onSubmit"
      @keydown="form.onKeydown($event)"
    >
      <div class="card-header">
        <div class="card-title">
          {{ __('Update user settings') }}
        </div>
      </div>
      <div class="card-body">
        <div class="row">
          <label for="language" class="col-form-label col-sm-3">
            {{ __('Language') }}
          </label>
          <div class="col-sm-9">
            <div class="input-group">
              <select
                id="language"
                v-model="form.language"
                class="form-select"
                name="language"
              >
                <option
                  v-for="(language, code) in languages"
                  :key="code"
                  :value="code"
                >
                  {{ language }}
                </option>
              </select>
              <span
                class="input-group-text btn btn-outline-input-info"
                data-coreui-toggle="tooltip"
                data-coreui-placement="top"
                :title="__('Controls the language used in YAFFA.')"
              >
                <i class="fa fa-info-circle"></i>
              </span>
            </div>
            <HasError field="language" :form="form" />
          </div>
        </div>
        <div class="row">
          <label for="locale" class="col-form-label col-sm-3">
            {{ __('Locale') }}
          </label>
          <div class="col-sm-9">
            <div class="input-group">
              <select
                id="locale"
                v-model="form.locale"
                class="form-select"
                name="locale"
              >
                <option
                  v-for="(locale, code) in locales"
                  :key="code"
                  :value="code"
                >
                  {{ locale }}
                </option>
              </select>
              <span
                class="input-group-text btn btn-outline-input-info"
                data-coreui-toggle="tooltip"
                data-coreui-placement="top"
                :title="
                  __('Controls how numbers, dates, currencies are formatted.')
                "
              >
                <i class="fa fa-info-circle text-info"></i>
              </span>
            </div>
            <HasError field="locale" :form="form" />
          </div>
        </div>
        <div class="row">
          <label for="start_date" class="col-form-label col-sm-3">
            {{ __('Start date for YAFFA') }}
          </label>
          <div class="col-sm-9">
            <div class="input-group">
              <input
                id="start_date"
                v-model="startDateInput"
                type="date"
                class="form-control"
                required
              />
              <span
                class="input-group-text btn btn-outline-input-info"
                data-coreui-toggle="tooltip"
                data-coreui-placement="top"
                :title="
                  __(
                    'The earliest date YAFFA uses to retrieve currency exchange rates and investment prices. You can record transactions to earlier dates, if needed.',
                  )
                "
              >
                <i class="fa fa-info-circle"></i>
              </span>
            </div>
            <HasError field="start_date" :form="form" />
          </div>
        </div>
        <div class="row">
          <label for="end_date" class="col-form-label col-sm-3">
            {{ __('End date for YAFFA') }}
          </label>
          <div class="col-sm-9">
            <div class="input-group">
              <input
                id="end_date"
                v-model="endDateInput"
                type="date"
                class="form-control"
                required
              />
              <span
                class="input-group-text btn btn-outline-input-info"
                data-coreui-toggle="tooltip"
                data-coreui-placement="top"
                :title="
                  __('How long would you like YAFFA to calculate forecasts.')
                "
              >
                <i class="fa fa-info-circle"></i>
              </span>
            </div>
            <HasError field="end_date" :form="form" />
          </div>
        </div>
        <div class="row">
          <label
            for="account_details_date_range"
            class="col-form-label col-sm-3"
          >
            {{ __('Default date range for transaction history') }}
          </label>
          <div class="col-sm-9">
            <div class="input-group">
              <select
                id="account_details_date_range"
                v-model="form.account_details_date_range"
                class="form-select"
                name="account_details_date_range"
              >
                <option value="none">
                  {{ __("Don't load data by default") }}
                </option>
                <optgroup
                  v-for="group in datePresets"
                  :key="group.label"
                  :label="__(group.label)"
                >
                  <option
                    v-for="option in group.options"
                    :key="option.value"
                    :value="option.value"
                  >
                    {{ __(option.label) }}
                  </option>
                </optgroup>
              </select>
              <span
                class="input-group-text btn btn-outline-input-info"
                data-coreui-toggle="tooltip"
                data-coreui-placement="top"
                :title="
                  __(
                    'The default date range to load transactions from when opening account, payee or category details. This can be changed on the fly in the details view.',
                  )
                "
              >
                <i class="fa fa-info-circle"></i>
              </span>
            </div>
            <HasError field="account_details_date_range" :form="form" />
          </div>
        </div>
        <div class="row">
          <label
            for="auto_merge_standard_transaction_items"
            class="col-form-label col-sm-3"
          >
            {{ __('Auto-merge transaction items') }}
          </label>
          <div class="col-sm-9">
            <div class="input-group">
              <div class="input-group-text">
                <input
                  id="auto_merge_standard_transaction_items"
                  v-model="form.auto_merge_standard_transaction_items"
                  class="form-check-input mt-0"
                  type="checkbox"
                  name="auto_merge_standard_transaction_items"
                />
              </div>
              <span
                class="input-group-text btn btn-outline-input-info"
                data-coreui-toggle="tooltip"
                data-coreui-placement="top"
                :title="
                  __(
                    'When enabled, YAFFA automatically merges transaction items that share the same category and tags (or no tags) and have no comment into a single item, summing their amounts.',
                  )
                "
              >
                <i class="fa fa-info-circle"></i>
              </span>
            </div>
            <HasError
              field="auto_merge_standard_transaction_items"
              :form="form"
            />
          </div>
        </div>
        <div class="row">
          <label for="recharge_tags_enabled" class="col-form-label col-sm-3">
            {{ __('Recharge tags') }}
          </label>
          <div class="col-sm-9">
            <div class="input-group">
              <div class="input-group-text">
                <input
                  id="recharge_tags_enabled"
                  v-model="form.recharge_tags_enabled"
                  class="form-check-input mt-0"
                  type="checkbox"
                  name="recharge_tags_enabled"
                  data-testid="user-settings-recharge-tags-enabled"
                />
              </div>
              <span
                class="input-group-text btn btn-outline-input-info"
                data-coreui-toggle="tooltip"
                data-coreui-placement="top"
                :title="
                  __(
                    'Track purchases made on behalf of someone else. Enables the tag requirement setting on categories, the save reminder and the recharge reports.',
                  )
                "
              >
                <i class="fa fa-info-circle"></i>
              </span>
            </div>
            <HasError field="recharge_tags_enabled" :form="form" />
          </div>
        </div>
        <div v-if="form.recharge_tags_enabled" class="row">
          <label for="recharge_backstop_date" class="col-form-label col-sm-3">
            {{ __('Recharge backstop date') }}
          </label>
          <div class="col-sm-9">
            <div class="input-group">
              <input
                id="recharge_backstop_date"
                v-model="backstopDateInput"
                class="form-control"
                type="date"
                name="recharge_backstop_date"
                data-testid="user-settings-recharge-backstop-date"
              />
              <span
                class="input-group-text btn btn-outline-input-info"
                data-coreui-toggle="tooltip"
                data-coreui-placement="top"
                :title="
                  __(
                    'Recharge activity before this date is hidden from the recharge reports. Leave empty to show everything.',
                  )
                "
              >
                <i class="fa fa-info-circle"></i>
              </span>
            </div>
            <HasError field="recharge_backstop_date" :form="form" />
          </div>
        </div>
      </div>
      <div class="card-footer">
        <SubmitButton class="btn btn-primary" :form="form">
          {{ __('Save') }}
        </SubmitButton>
      </div>
    </form>
  </div>
</template>
<script setup>
  defineProps({
    languages: {
      type: Object,
      default: window.languages,
    },
    locales: {
      type: Object,
      default: window.locales,
    },
    datePresets: {
      type: Object,
      default: window.YAFFA.config.datePresets,
    },
  });
</script>
<script>
  import { __ } from '@/shared/lib/i18n';
  import {
    initializeBootstrapTooltips,
    toDateInputValue,
  } from '@/shared/lib/helpers';
  import * as toastHelpers from '@/shared/lib/toast';
  import Form from 'vform';
  import {
    Button as SubmitButton,
    HasError,
  } from 'vform/src/components/bootstrap5';

  export default {
    name: 'UserSettings',
    components: {
      SubmitButton,
      HasError,
    },
    data: () => ({
      form: new Form({
        language: window.YAFFA.userSettings.language,
        locale: window.YAFFA.userSettings.locale,
        end_date: window.YAFFA.userSettings.end_date,
        start_date: window.YAFFA.userSettings.start_date,
        account_details_date_range:
          window.YAFFA.userSettings.account_details_date_range || 'none',
        auto_merge_standard_transaction_items:
          window.YAFFA.userSettings.auto_merge_standard_transaction_items ||
          false,
        recharge_tags_enabled:
          window.YAFFA.userSettings.recharge_tags_enabled || false,
        recharge_backstop_date:
          window.YAFFA.userSettings.recharge_backstop_date || null,
      }),
    }),
    computed: {
      startDateInput: {
        get() {
          return toDateInputValue(this.form.start_date);
        },
        set(value) {
          this.form.start_date = value || null;
        },
      },
      backstopDateInput: {
        get() {
          return this.form.recharge_backstop_date || '';
        },
        set(value) {
          this.form.recharge_backstop_date = value || null;
        },
      },
      endDateInput: {
        get() {
          return toDateInputValue(this.form.end_date);
        },
        set(value) {
          this.form.end_date = value || null;
        },
      },
    },
    mounted() {
      // Finally, initialize tooltips
      initializeBootstrapTooltips(this.$el);
    },
    methods: {
      onSubmit: function () {
        let _vue = this;
        this.form.busy = true;

        // Send the form data to the server via the API route
        this.form
          .patch(this.route('api.v1.users.me.settings'), this.form)
          .then((response) => {
            if (response.status === 200) {
              // Update the global YAFFA object with the new settings
              window.YAFFA.userSettings.language = response.data.data.language;
              window.YAFFA.userSettings.locale = response.data.data.locale;
              window.YAFFA.userSettings.start_date =
                response.data.data.start_date;
              window.YAFFA.userSettings.end_date = response.data.data.end_date;
              window.YAFFA.userSettings.account_details_date_range =
                response.data.data.account_details_date_range;
              window.YAFFA.userSettings.auto_merge_standard_transaction_items =
                response.data.data.auto_merge_standard_transaction_items;

              // The recharge menu and category field are server-rendered: they follow on the next page load
              const rechargeChanged =
                window.YAFFA.userSettings.recharge_tags_enabled !==
                response.data.data.recharge_tags_enabled;
              window.YAFFA.userSettings.recharge_tags_enabled =
                response.data.data.recharge_tags_enabled;
              window.YAFFA.userSettings.recharge_backstop_date =
                response.data.data.recharge_backstop_date;
              if (rechargeChanged) {
                toastHelpers.showWarningToast(
                  __('Refresh the page to update the menu.'),
                );
              }

              // Emit a custom event to global scope about the result
              toastHelpers.showSuccessToast(__('User settings updated'));

              // If the cached data is recalculated, emit an additional event
              response.data.warnings.forEach((warning) => {
                toastHelpers.showWarningToast(warning);
              });
            }
          })
          .catch((error) => {
            if (error.response.status === 422) {
              toastHelpers.showErrorToast(
                __('Validation failed. Please check the form for errors.'),
              );
            } else {
              console.error(error);
              toastHelpers.showErrorToast(
                __('An error occurred. Please try again later.'),
              );
            }
          })
          .finally(() => {
            _vue.form.busy = false;
          });
      },
      __,
    },
  };
</script>

<style scoped>
  form div.row:not(:last-child) {
    /* Apply mb-3 margin only to rows except the last one */
    margin-bottom: 1rem !important;
  }
</style>
