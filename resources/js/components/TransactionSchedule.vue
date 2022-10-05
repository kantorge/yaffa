<template>
    <div class="box">
        <div class="box-header with-border">
            <h3 class="box-title" v-html="title"></h3>
            <div class="box-tools pull-right" v-if="withCheckbox">
                <div class="checkbox">
                    <label>
                        <input
                            type="checkbox"
                            value="1"
                            v-model="allowCustomization"
                        >
                        Customize
                    </label>
                </div>
            </div>
        </div>
        <!-- /.box-header -->
        <div class="box-body" id="">
            <div class="row">
                <div
                    class="col-xs-6 col-sm-4 form-group"
                    :class="form.errors.has('schedule_config.frequency') ? 'has-error' : ''"
                >
                    <label for="schedule_frequency" class="control-label">Frequency</label>
                    <select
                        class="form-control"
                        id="schedule_frequency"
                        v-model="schedule.frequency"
                        :disabled="!allowCustomization"
                    >
                        <option value="DAILY">Daily</option>
                        <option value="WEEKLY">Weekly</option>
                        <option value="MONTHLY">Monthly</option>
                        <option value="YEARLY">Yearly</option>
                    </select>
                </div>
                <div
                    class="col-xs-6 col-sm-4 form-group"
                    :class="form.errors.has('schedule_config.interval') ? 'has-error' : ''"
                >
                    <label for="schedule_interval" class="control-label">Interval</label>
                    <MathInput
                        :disabled="!allowCustomization"
                        class="form-control"
                        id="schedule_interval"
                        v-model="schedule.interval"
                    ></MathInput>
                </div>
                <div
                    class="col-xs-6 col-sm-4 form-group"
                    :class="form.errors.has('schedule_config.start_date') ? 'has-error' : ''"
                >
                    <label
                        :for="'schedule_start_' + this.$.vnode.key" class="control-label">Start date</label>
                    <Datepicker
                        :id="'schedule_start_' + this.$.vnode.key"
                        v-model="schedule.start_date"
                        :disabled="!allowCustomization"
                        autoApply
                        format="yyyy. MM. dd."
                        :enableTimePicker="false"
                        utc="preserve"
                    ></Datepicker>
                </div>
                <div
                    class="col-xs-6 col-sm-4 form-group"
                    :class="form.errors.has('schedule_config.next_date') ? 'has-error' : ''"
                    v-if="isSchedule"
                >
                    <label for="schedule_next" class="control-label">
                            Next date
                            <span
                                class="fa"
                                :class="!schedule.next_date ? 'fa-warning text-warning' : 'fa-info-circle text-info'"
                                title="If next date is empty, then this schedule is considered to be finished"></span>
                    </label>
                    <Datepicker
                        id="schedule_next"
                        v-model="schedule.next_date"
                        :disabled="!allowCustomization"
                        autoApply
                        format="yyyy. MM. dd."
                        :enableTimePicker="false"
                        utc="preserve"
                    ></Datepicker>
                </div>
                <div
                    class="col-xs-6 col-sm-4 form-group"
                    :class="form.errors.has('schedule_config.count') ? 'has-error' : ''"
                >
                    <label for="schedule_count" class="control-label">Count</label>
                    <MathInput
                        class="form-control"
                        id="schedule_count"
                        v-model="schedule.count"
                        :disabled="!allowCustomization"
                    ></MathInput>
                </div>
                <div
                    class="col-xs-6 col-sm-4 form-group"
                    :class="form.errors.has('schedule_config.end_date') ? 'has-error' : ''"
                >
                    <label for="schedule_end" class="control-label">End date</label>
                    <Datepicker
                        id="schedule_end"
                        v-model="schedule.end_date"
                        :disabled="!allowCustomization"
                        autoApply
                        format="yyyy. MM. dd."
                        :enableTimePicker="false"
                        utc="preserve"
                    ></Datepicker>
                </div>
                <div
                    class="col-xs-6 col-sm-4 form-group"
                    :class="form.errors.has('schedule_config.inflation') ? 'has-error' : ''"
                    v-if="isBudget"
                >
                    <label for="schedule_inflation" class="control-label">Budget inflation, %</label>
                    <input
                        class="form-control"
                        id="schedule_inflation"
                        v-model="schedule.inflation"
                        type="number"
                        step=".01"
                        :disabled="!allowCustomization"
                    >
                </div>
            </div>
        </div>
        <!-- /.box-body -->
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
