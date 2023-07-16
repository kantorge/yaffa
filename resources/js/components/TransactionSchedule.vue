<template>
    <div class="card mb-3">
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
                        class="col-6 col-sm-4 mb-2"
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
                        class="col-6 col-sm-4 mb-2"
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
                        class="col-6 col-sm-4 mb-2"
                        :class="{'has-error' : form.errors.has('schedule_config.start_date')}"
                >
                    <label
                            :for="'schedule_start_' + this.$.vnode.key" class="control-label">
                        {{ __('Start date') }}
                    </label>
                    <Datepicker
                            :id="'schedule_start_' + this.$.vnode.key"
                            v-model="schedule.start_date"
                            :disabled="!allowCustomizationData"
                            autoApply
                            format="yyyy. MM. dd."
                            :enableTimePicker="false"
                            utc="preserve"
                    ></Datepicker>
                </div>
                <div
                        class="col-6 col-sm-4 mb-2"
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
                    <Datepicker
                            id="schedule_next"
                            v-model="schedule.next_date"
                            :disabled="!allowCustomizationData"
                            autoApply
                            format="yyyy. MM. dd."
                            :enableTimePicker="false"
                            utc="preserve"
                    ></Datepicker>
                </div>
                <div
                    class="col-6 col-sm-4 mb-2"
                    :class="form.errors.has('schedule_config.count') ? 'has-error' : ''"
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
                        class="col-6 col-sm-4 mb-2"
                        :class="{'has-error' : form.errors.has('schedule_config.end_date')}"
                >
                    <label for="schedule_end" class="control-label">
                        {{ __('End date') }}
                    </label>
                    <Datepicker
                            id="schedule_end"
                            v-model="schedule.end_date"
                            :disabled="!allowCustomizationData"
                            autoApply
                            format="yyyy. MM. dd."
                            :enableTimePicker="false"
                            utc="preserve"
                    ></Datepicker>
                </div>
                <div
                        class="col-6 col-sm-4 mb-2"
                        :class="{'has-error' : form.errors.has('schedule_config.inflation')}"
                        v-if="isBudget"
                >
                    <label for="schedule_inflation" class="control-label">
                        {{ __('Budget inflation, %') }}
                    </label>
                    <input
                            class="form-control"
                            id="schedule_inflation"
                            v-model="schedule.inflation"
                            type="number"
                            step=".01"
                            :disabled="!allowCustomizationData"
                    >
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import Datepicker from '@vuepic/vue-datepicker';
import '@vuepic/vue-datepicker/dist/main.css'
import MathInput from './MathInput.vue'

export default {
    components: {
        Datepicker,
        MathInput,
    },

    props: {
        isSchedule: Boolean,
        isBudget: Boolean,
        schedule: Object,
        form: Object,
        title: {
            type: String,
            default: 'Schedule',
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
}
</script>
