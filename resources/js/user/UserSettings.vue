<template>
    <div class="card" id="userSettingsForm">
        <form
                accept-charset="UTF-8"
                @submit.prevent="onSubmit"
                @keydown="form.onKeydown($event)"
                autocomplete="off"
        >
            <div class="card-header">
                <div class="card-title">
                    {{ __('Update user settings') }}
                </div>
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <label for="language" class="col-form-label col-sm-3">
                        {{ __('Language') }}
                    </label>
                    <div class="col-sm-9">
                        <div class="input-group">
                            <select
                                    class="form-select"
                                    id="language"
                                    name="language"
                                    v-model="form.language"
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
                                    class="input-group-text btn btn-info"
                                    data-coreui-toggle="tooltip"
                                    data-coreui-placement="top"
                                    :title="__('Controls the language used in YAFFA.')"
                            >
                                <i
                                        class="fa fa-info-circle"
                                ></i>
                            </span>
                        </div>
                        <HasError field="language" :form="form" />
                    </div>
                </div>
                <div class="row mb-3">
                    <label for="locale" class="col-form-label col-sm-3">
                        {{ __('Locale') }}
                    </label>
                    <div class="col-sm-9">
                        <div class="input-group">
                            <select
                                    class="form-select"
                                    id="locale"
                                    name="locale"
                                    v-model="form.locale"
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
                                    class="input-group-text btn btn-info"
                                    data-coreui-toggle="tooltip"
                                    data-coreui-placement="top"
                                    :title="__('Controls how numbers, dates, currencies are formatted.')"
                            >
                                <i
                                        class="fa fa-info-circle"
                                ></i>
                            </span>
                        </div>
                        <HasError field="locale" :form="form" />
                    </div>
                </div>
                <div class="row mb-3">
                    <label for="start_date" class="col-form-label col-sm-3">
                        {{ __('Start date for YAFFA') }}
                    </label>
                    <div class="col-sm-9">
                        <div class="input-group">
                            <DatePicker
                                    :is-required="true"
                                    :masks="{
                                        L: 'YYYY-MM-DD',
                                        modelValue: 'YYYY-MM-DD'
                                    }"
                                    mode="date"
                                    :popover="{
                                        visibility: 'click',
                                        showDelay: 0,
                                        hideDelay: 0
                                    }"
                                    v-model.string="form.start_date"
                            >
                                <template #default="{inputValue, inputEvents}">
                                    <input
                                            class="form-control"
                                            id="start_date"
                                            :value="inputValue"
                                            v-on="inputEvents"
                                    >
                                </template>
                            </DatePicker>
                            <span
                                    class="input-group-text btn btn-info"
                                    data-coreui-toggle="tooltip"
                                    data-coreui-placement="top"
                                    :title="__('The earliest date YAFFA uses to retrieve currency exchange rates and investment prices. You can record transactions to earlier dates, if needed.')"
                            >
                                <i
                                        class="fa fa-info-circle"
                                ></i>
                            </span>
                        </div>
                        <HasError field="start_date" :form="form" />
                    </div>
                </div>
                <div class="row mb-3">
                    <label for="end_date" class="col-form-label col-sm-3">
                        {{ __('End date for YAFFA') }}
                    </label>
                    <div class="col-sm-9">
                        <div class="input-group">
                            <DatePicker
                                    :is-required="true"
                                    :masks="{
                                        L: 'YYYY-MM-DD',
                                        modelValue: 'YYYY-MM-DD'
                                    }"
                                    mode="date"
                                    :popover="{
                                        visibility: 'click',
                                        showDelay: 0,
                                        hideDelay: 0
                                    }"
                                    v-model.string="form.end_date"
                            >
                                <template #default="{inputValue, inputEvents}">
                                    <input
                                            class="form-control"
                                            id="end_date"
                                            :value="inputValue"
                                            v-on="inputEvents"
                                    >
                                </template>
                            </DatePicker>
                            <span
                                    class="input-group-text btn btn-info"
                                    data-coreui-toggle="tooltip"
                                    data-coreui-placement="top"
                                    :title="__('How long would you like YAFFA to calculate forecasts.')"
                            >
                                <i
                                        class="fa fa-info-circle"
                                ></i>
                            </span>
                        </div>
                        <HasError field="end_date" :form="form" />
                    </div>
                </div>
                <div class="row mb-3">
                    <label for="account_details_date_range" class="col-form-label col-sm-3">
                        {{ __('Default date range for account details') }}
                    </label>
                    <div class="col-sm-9">
                        <div class="input-group">
                            <select
                                    class="form-select"
                                    id="account_details_date_range"
                                    name="account_details_date_range"
                                    v-model="form.account_details_date_range"
                            >
                                <option value="none">{{ __("Don't load data by default") }}</option>
                                <optgroup v-for="(group) in datePresets" :label="group.label">
                                    <option v-for="option in group.options" :value="option.value">
                                        {{ option.label }}
                                    </option>
                                </optgroup>
                            </select>
                            <span
                                    class="input-group-text btn btn-info"
                                    data-coreui-toggle="tooltip"
                                    data-coreui-placement="top"
                                    :title="__('The default date range to load transactions from when opening account details. This can be changed on the fly in the account details view.')"
                            >
                                <i
                                        class="fa fa-info-circle"
                                ></i>
                            </span>
                        </div>
                        <HasError field="account_details_date_range" :form="form" />
                    </div>

                </div>
            </div>
            <div class="card-footer">
                <Button
                        class="btn btn-primary"
                        :form="form"
                        dusk="button-update-settings"
                >
                  {{ __('Save') }}
                </Button>
            </div>
        </form>
    </div>
</template>
<script setup>
    const props = defineProps({
        languages: {
            type: Object,
            default: window.languages
        },
        locales: {
            type: Object,
            default: window.locales
        },
        datePresets: {
            type: Object,
            default: window.datePresets
        }
    });
</script>
<script>
    import { DatePicker } from "v-calendar";
    import * as helpers from "../helpers";
    import Form from 'vform';
    import { Button, HasError } from 'vform/src/components/bootstrap5'

    export default {
        name: 'UserSettings',
        components: {
            DatePicker, Button, HasError
        },
        data: () => ({
            form: new Form({
                language: window.YAFFA.language,
                locale: window.YAFFA.locale,
                end_date: window.YAFFA.end_date,
                start_date: window.YAFFA.start_date,
                account_details_date_range: window.YAFFA.account_details_date_range || 'none',
            }),
        }),
        mounted() {
            // Finally, initialize tooltips
            const tooltipTriggerList = document.querySelectorAll('[data-coreui-toggle="tooltip"]');
            [...tooltipTriggerList].map(tooltipTriggerEl => new coreui.Tooltip(tooltipTriggerEl));

        },
        methods: {
            onSubmit: function () {
                let _vue = this;
                this.form.busy = true;

                // Send the form data to the server via the API route user.settings.update
                this.form.patch(
                    window.route('user.settings.update'),
                    this.form
                )
                    .then(response => {
                        if (response.status === 200) {
                            // Update the global YAFFA object with the new settings
                            window.YAFFA.language = response.data.data.language;
                            window.YAFFA.locale = response.data.data.locale;
                            window.YAFFA.start_date = response.data.data.start_date;
                            window.YAFFA.end_date = response.data.data.end_date;
                            window.YAFFA.account_details_date_range = response.data.data.account_details_date_range;

                            // Emit a custom event to global scope about the result
                            _vue.showToast(
                                __('Success'),
                               __('User settings updated'),
                                'bg-success'
                            );

                            // If the cached data is recalculated, emit an additional event
                            response.data.warnings.forEach(warning => {
                                _vue.showToast(
                                    __('Warning'),
                                    warning,
                                    'bg-warning'
                                );
                            });
                        }
                    })
                    .catch(error => {
                        if (error.response.status === 422) {
                            _vue.showToast(
                                __('Error'),
                                __('Validation failed. Please check the form for errors.'),
                                'bg-danger'
                            );
                        } else {
                            console.error(error);
                            _vue.showToast(
                                __('Error'),
                                __('An error occurred. Please try again later.'),
                                'bg-danger'
                            );
                        }
                    })
                    .finally(() => {
                        _vue.form.busy = false;
                    });
            },

            /**
             * Import the translation helper function.
             */
            __: function (string, replace) {
                return helpers.__(string, replace);
            },

            /**
             * Import the toast display helper function.
             */
            showToast: function (header, body, toastClass, otherProperties) {
                return helpers.showToast(header, body, toastClass, otherProperties);
            },
        }
    }
</script>
