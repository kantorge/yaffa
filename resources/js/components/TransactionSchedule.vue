<template>
    <div class="box" v-show="isVisible">
        <div class="box-header with-border">
            <h3 class="box-title">Schedule</h3>
        </div>
        <!-- /.box-header -->
        <div class="box-body" id="">
            <div class="row">
                <div
                    class="col-md-4 form-group"
                    :class="form.errors.has('schedule_config.frequency') ? 'has-error' : ''"
                >
                    <label for="schedule_frequency" class="control-label">Frequency</label>
                    <select
                        class="form-control"
                        id="schedule_frequency"
                        v-model="schedule.frequency"
                    >
                        <option value="DAILY">Daily</option>
                        <option value="WEEKLY">Weekly</option>
                        <option value="MONTHLY">Monthly</option>
                        <option value="YEARLY">Yearly</option>
                </select>
                </div>
                <div
                    class="col-md-4 form-group"
                    :class="form.errors.has('schedule_config.count') ? 'has-error' : ''"
                >
                    <label for="schedule_count" class="control-label">Count</label>
                    <MathInput
                        class="form-control"
                        id="schedule_count"
                        v-model="schedule.count"
                    ></MathInput>
                </div>
                <div
                    class="col-md-4 form-group"
                    :class="form.errors.has('schedule_config.interval') ? 'has-error' : ''"
                >
                    <label for="schedule_interval" class="control-label">Interval</label>
                    <MathInput
                        class="form-control"
                        id="schedule_interval"
                        v-model="schedule.interval"
                    ></MathInput>
                </div>
            </div>

            <div class="row">
                <div
                    class="col-md-4 form-group"
                    :class="form.errors.has('schedule_config.start_date') ? 'has-error' : ''"
                >
                    <label for="schedule_start" class="control-label">Start date</label>
                    <date-picker
                        format="YYYY-MM-DD"
                        id="schedule_start"
                        style="width: 100%;"
                        type="date"
                        value-type="format"
                        v-model="schedule.start_date"
                    ></date-picker>
                </div>
                <div
                    class="col-md-4 form-group"
                    :class="form.errors.has('schedule_config.next_date') ? 'has-error' : ''"
                >
                    <label for="schedule_next" class="control-label">Next date</label>
                    <date-picker
                        format="YYYY-MM-DD"
                        id="schedule_next"
                        style="width: 100%;"
                        type="date"
                        value-type="format"
                        v-model="schedule.next_date"
                    ></date-picker>
                </div>
                <div
                    class="col-md-4 form-group"
                    :class="form.errors.has('schedule_config.end_date') ? 'has-error' : ''"
                >
                    <label for="schedule_end" class="control-label">End (last) date</label>
                    <date-picker
                        format="YYYY-MM-DD"
                        id="schedule_end"
                        style="width: 100%;"
                        type="date"
                        value-type="format"
                        v-model="schedule.end_date"
                    ></date-picker>
                </div>
            </div>
        </div>
        <!-- /.box-body -->
    </div>
</template>

<script>
    import DatePicker from 'vue2-datepicker';
    import 'vue2-datepicker/index.css';
    import MathInput from './MathInput.vue'

    export default {
        components: {
            DatePicker,
            MathInput,
        },

        props: {
            isVisible: Boolean,
            schedule: Object,
            form: Object,
        },

        data() {
            return {}
        },
    }
</script>
