<template>
    <div class="box" v-show="isVisible">
        <div class="box-header with-border">
            <h3 class="box-title">Schedule</h3>
        </div>
        <!-- /.box-header -->
        <div class="box-body" id="">
            <div class="row">
                <div class="col-xs-6 col-sm-4 form-group">
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
                <div class="col-xs-6 col-sm-4 form-group">
                    <label for="schedule_interval" class="control-label">Interval</label>
                    {{ schedule.interval }}
                </div>
                <div class="col-xs-6 col-sm-4 form-group">
                    <label for="schedule_start" class="control-label">Start date</label>
                    {{ schedule.start_date }}
                </div>
                <div
                    class="col-xs-6 col-sm-4 form-group"
                    v-if="isSchedule"
                >
                    <label for="schedule_next" class="control-label">
                            Next date
                            <span
                                class="fa"
                                :class="!schedule.next_date ? 'fa-warning text-warning' : 'fa-info-circle text-info'"
                                title="If next date is empty, then this schedule is considered to be finished"></span>
                    </label>
                    {{ schedule.next_date }}
                </div>
                <div
                    class="col-xs-6 col-sm-4 form-group"
                >
                    <label for="schedule_count" class="control-label">Count</label>
                    {{ schedule.count }}
                </div>
                <div
                    class="col-xs-6 col-sm-4 form-group"
                >
                    <label for="schedule_end" class="control-label">End date</label>
                    {{ schedule.end_date }}
                </div>
                <div
                    class="col-xs-6 col-sm-4 form-group"
                    v-if="isBudget"
                >
                    <label for="schedule_inflation" class="control-label">Budget inflation, %</label>
                    <input
                        class="form-control"
                        id="schedule_inflation"
                        v-model="schedule.inflation"
                        type="number"
                        step=".01"
                    >
                </div>
            </div>
        </div>
        <!-- /.box-body -->
    </div>
</template>

<script>
    export default {
        components: {},

        props: {
            isVisible: Boolean,
            isSchedule: Boolean,
            isBudget: Boolean,
            schedule: Object,
        },

        data() {
            return {}
        },
    }
</script>
