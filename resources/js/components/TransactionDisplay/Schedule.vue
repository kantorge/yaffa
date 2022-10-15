<template>
    <div class="box" v-show="isVisible">
        <div class="box-header with-border">
            <h3 class="box-title">
                {{ __('Schedule') }}
            </h3>
        </div>
        <!-- /.box-header -->
        <div class="box-body" id="">
            <div class="row">
                <div class="col-xs-6 col-sm-4 form-group">
                    <label for="schedule_frequency" class="control-label">
                        {{ __('Frequency') }}
                    </label>
                    {{ schedule.frequency }}
                </div>
                <div class="col-xs-6 col-sm-4 form-group">
                    <label for="schedule_interval" class="control-label">
                        {{ __('Interval') }}
                    </label>
                    {{ schedule.interval }}
                </div>
                <div class="col-xs-6 col-sm-4 form-group">
                    <label for="schedule_start" class="control-label">
                        {{ __('Start date') }}
                    </label>
                    {{ formattedDate(schedule.start_date) }}
                </div>
                <div
                    class="col-xs-6 col-sm-4 form-group"
                    v-if="isSchedule"
                >
                    <label for="schedule_next" class="control-label">
                        {{ __('Next date') }}
                    </label>

                    <span v-if="schedule.next_date">{{ formattedDate(schedule.next_date) }}</span>
                    <span v-else class="text-muted text-italic">{{ __('Not set') }}</span>
                </div>
                <div
                    class="col-xs-6 col-sm-4 form-group"
                >
                    <label for="schedule_count" class="control-label">
                        {{ __('Count') }}
                    </label>
                    {{ schedule.count }}
                </div>
                <div
                    class="col-xs-6 col-sm-4 form-group"
                >
                    <label for="schedule_end" class="control-label">
                        {{ __('End date') }}
                    </label>

                    <span v-if="schedule.end_date">{{ formattedDate(schedule.end_date) }}</span>
                    <span v-else class="text-muted text-italic">{{ __('Not set') }}</span>
                </div>
                <div
                    class="col-xs-6 col-sm-4 form-group"
                    v-if="isBudget"
                >
                    <label for="schedule_inflation" class="control-label">
                        {{ __('Budget inflation') }}
                    </label>
                    <span v-if="schedule.inflation">{{ schedule.inflation }} <span v-show="schedule.inflation">%</span></span>
                    <!-- TODO: account for 0 value -->
                    <span v-else class="text-muted text-italic">{{ __('Not set') }}</span>
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

        methods: {
            formattedDate(date) {
                if (typeof date === 'undefined') {
                    return;
                }

                const newDate = new Date(date);

                return newDate.toLocaleDateString('Hu-hu');
            },
        }
    }
</script>
