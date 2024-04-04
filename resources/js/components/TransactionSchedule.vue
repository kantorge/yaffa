<template>
    <div class="card mb-3" dusk="card-transaction-schedule">
        <div class="card-header d-flex justify-content-between">
            <div
                    class="card-title"
                    v-html="title"></div>
            <div v-if="withCheckbox">
                <div class="checkbox">
                    <label>
                        <input
                                type="checkbox"
                                value="1"
                                v-model="allowCustomizationData"
                        >
                        {{ __('Customize') }}
                    </label>
                </div>
            </div>
        </div>
        <div class="card-body">
            <div class="row">
                <div
                        class="col-lg-1 col-6 mb-2"
                        :class="form.errors.has('schedule_config.frequency') ? 'has-error' : ''"
                >
                    <label for="schedule_frequency" class="control-label">
                        {{ __('Frequency') }}
                    </label>
                    <select
                            class="form-select"
                            id="schedule_frequency"
                            v-model="schedule.frequency"
                            :disabled="!allowCustomizationData"
                    >
                        <option value="DAILY">{{ __('Daily') }}</option>
                        <option value="WEEKLY">{{ __('Weekly') }}</option>
                        <option value="MONTHLY">{{ __('Monthly') }}</option>
                        <option value="YEARLY">{{ __('Yearly') }}</option>
                    </select>
                </div>
                <div
                        class="col-lg-1 col-6 mb-2"
                        :class="form.errors.has('schedule_config.interval') ? 'has-error' : ''"
                >
                    <label for="schedule_interval" class="control-label">
                        {{ __('Interval') }}
                    </label>
                    <MathInput
                            :disabled="!allowCustomizationData"
                            class="form-control"
                            id="schedule_interval"
                            v-model="schedule.interval"
                    ></MathInput>
                </div>
                <div
                        class="col-lg-2 col-6 mb-2"
                        :class="{'has-error' : form.errors.has('schedule_config.start_date')}"
                >
                    <label
                            :for="'schedule_start_' + this.$.vnode.key" class="control-label">
                        {{ __('Start date') }}
                    </label>
                    <DatePicker
                            v-model.string="schedule.start_date"
                            :disabled="!allowCustomizationData"
                            mode="date"
                            is-required
                            :popover="{ visibility: 'click' }"
                            :masks="{
                                L: 'YYYY-MM-DD',
                                modelValue: 'YYYY-MM-DD'
                            }"
                    >
                        <template #default="{inputValue, inputEvents}">
                            <input
                                    class="form-control"
                                    :id="'schedule_start_' + this.$.vnode.key"
                                    :value="inputValue"
                                    v-on="inputEvents"
                            >
                        </template>
                    </DatePicker>
                </div>
                <div
                        class="col-lg-2 col-6 mb-2"
                        :class="{'has-error' : form.errors.has('schedule_config.next_date')}"
                        v-if="isSchedule"
                >
                    <label for="schedule_next" class="control-label">
                        {{ __('Next date') }}
                        <span
                                class="fa"
                                :class="!schedule.next_date ? 'fa-warning text-warning' : 'fa-info-circle text-info'"
                                :title="__('If next date is empty, then this schedule is considered to be finished')"
                        ></span>
                    </label>
                    <DatePicker
                            v-model.string="schedule.next_date"
                            :disabled="!allowCustomizationData"
                            mode="date"
                            :popover="{ visibility: 'click' }"
                            :masks="{
                                L: 'YYYY-MM-DD',
                                modelValue: 'YYYY-MM-DD'
                            }"
                    >
                        <template #default="{inputValue, inputEvents}">
                            <input
                                    class="form-control"
                                    :id="'schedule_next' + this.$.vnode.key"
                                    :value="inputValue"
                                    v-on="inputEvents"
                            >
                        </template>
                    </DatePicker>
                </div>
                <div
                        class="col-lg-2 col-6 mb-2"
                        :class="{'has-error' : form.errors.has('schedule_config.automatic_recording')}"
                        v-if="isSchedule"
                >
                    <div class="form-check">
                        <br>
                        <input
                                class="form-check-input"
                                dusk="checkbox-schedule-automatic-recording"
                                type="checkbox"
                                value="1"
                                v-model="schedule.automatic_recording"
                                id="schedule_automatic_recording"
                                :disabled="!allowCustomizationData"
                        >
                        <label class="form-check-label" for="schedule_automatic_recording">
                            {{ __('Automatic recording') }}
                            <i
                                    class="fa fa-info-circle text-primary"
                                    :title="__('The transaction is automatically entered on the next date.')"
                            ></i>
                        </label>
                    </div>
                </div>
                <div
                        class="col-lg-1 col-6 mb-2"
                        :class="{ 'has-error' : form.errors.has('schedule_config.count')}"
                >
                    <label for="schedule_count" class="control-label">
                        {{ __('Count') }}
                    </label>
                    <MathInput
                            class="form-control"
                            id="schedule_count"
                            v-model="schedule.count"
                            :disabled="!allowCustomizationData"
                    ></MathInput>
                </div>
                <div
                        class="col-lg-2 col-6 mb-2"
                        :class="{'has-error' : form.errors.has('schedule_config.end_date')}"
                >
                    <label for="schedule_end" class="control-label">
                        {{ __('End date') }}
                    </label>
                    <DatePicker
                            v-model.string="schedule.end_date"
                            :disabled="!allowCustomizationData"
                            mode="date"
                            :popover="{ visibility: 'click' }"
                            :masks="{
                                L: 'YYYY-MM-DD',
                                modelValue: 'YYYY-MM-DD'
                            }"
                    >
                        <template #default="{inputValue, inputEvents}">
                            <input
                                    class="form-control"
                                    :id="'schedule_end' + this.$.vnode.key"
                                    :value="inputValue"
                                    v-on="inputEvents"
                            >
                        </template>
                    </DatePicker>
                </div>
                <div
                        class="col-lg-1 col-6 mb-2"
                        :class="{'has-error' : form.errors.has('schedule_config.inflation')}"
                        v-if="isBudget"
                >
                    <label for="schedule_inflation" class="control-label">
                        {{ __('Budget inflation') }}
                    </label>
                    <div class="input-group">
                        <input
                                class="form-control"
                                id="schedule_inflation"
                                v-model="schedule.inflation"
                                type="number"
                                step=".01"
                                :disabled="!allowCustomizationData"
                        >
                        <span class="input-group-text">%</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import {DatePicker} from 'v-calendar';
import MathInput from './MathInput.vue'
import * as helpers from '../helpers';

export default {
    components: {
        DatePicker,
        MathInput,
    },

    props: {
        isSchedule: Boolean,
        isBudget: Boolean,
        schedule: Object,
        form: Object,
        title: {
            type: String,
            default: __('Schedule'),
        },
        withCheckbox: {
            type: Boolean,
            default: false,
        },
        allowCustomization: {
            type: Boolean,
            default: true,
        },
    },

    data() {
        let data = {};

        data.allowCustomizationData = this.allowCustomization;

        // Date picker settings
        data.dataPickerLanguage = {
            formatLocale: {
                firstDayOfWeek: 1,
            },
            monthBeforeYear: false,
        };
        return data;
    },

    methods: {
        /**
         * Import the translation helper function.
         */
        __: function (string, replace) {
            return helpers.__(string, replace);
        },
    },
}
</script>
