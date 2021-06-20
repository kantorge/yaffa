<template>
    <!-- schedule settings -->
    <div class="box" id="schedule_container" v-show="isVisible">
        <div class="box-header with-border">
            <h3 class="box-title">Schedule</h3>
        </div>
        <!-- /.box-header -->
        <div class="box-body" id="">
            <div class="row">
                <div class="col-md-4">
                    <label for="schedule_frequency" class="control-label">Frequency</label>
                    <select
                        class="form-control valid"
                        id="schedule_frequency"
                        name="schedule_frequency"
                        v-model="schedule.frequency"
                    >
                        <option value="DAILY">Daily</option>
                        <option value="WEEKLY">Weekly</option>
                        <option value="MONTHLY">Monthly</option>
                        <option value="YEARLY">Yearly</option>
                </select>
                </div>
                <div class="col-md-4">
                    <label for="schedule_count" class="control-label">Count</label>
                    <input
                        class="form-control"
                        id="schedule_count"
                        name="schedule_count"
                        type="text"
                        v-model="schedule.count"
                    >
                </div>
                <div class="col-md-4 form-group">
                    <label for="schedule_interval" class="control-label">Count</label>
                    <input
                        class="form-control"
                        id="schedule_interval"
                        name="schedule_interval"
                        type="text"
                        v-model="schedule.interval"
                    >
                </div>
            </div>

            <div class="row">
                <div class="col-md-4">
                    <label for="schedule_start" class="control-label">Start date</label>
                    <input
                        class="form-control"
                        id="schedule_start"
                        name="schedule_start"
                        type="text"
                        v-model="schedule.start_date"
                    >
                </div>
                <div class="col-md-4 form-group">
                    <label for="schedule_next" class="control-label">Next date</label>
                    <input
                        class="form-control"
                        id="schedule_next"
                        name="schedule_next"
                        type="text"
                        v-model="schedule.next_date"
                    >
                </div>
                <div class="col-md-4">
                    <label for="schedule_end" class="control-label">End (last) date</label>
                    <input
                        class="form-control"
                        id="schedule_end"
                        name="schedule_end"
                        type="text"
                        v-model="schedule.end_date"
                    >
                </div>
            </div>
        </div>
        <!-- /.box-body -->

    </div>
    <!-- /.box -->
</template>

<script>
    require('daterangepicker');

    //TODO: make it common accross components
    const datePickerStandardSettings = {
        singleDatePicker: true,
        showDropdowns: true,
        locale: {
            format: 'YYYY-MM-DD'
        },
        autoApply: true
    };

    export default {
        props: [
            'isVisible',
        ],

        data() {
            return {
                schedule: {

                },
            }
        },

        mounted() {
            // Initialize date fields
            $('#schedule_start').daterangepicker(datePickerStandardSettings);
            $('#schedule_next').daterangepicker(datePickerStandardSettings);
            $('#schedule_end').daterangepicker(datePickerStandardSettings);

        }
    }
</script>
