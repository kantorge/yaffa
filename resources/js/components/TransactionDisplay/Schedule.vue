<template>
    <div class="card mb-3" v-show="isVisible">
        <div class="card-header">
            <div class="card-title">
                {{ __('Schedule') }}
            </div>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-6 col-sm-4 mb-2">
                    <label for="schedule_frequency" class="control-label">
                        {{ __('Frequency') }}
                    </label>
                    {{ schedule.frequency }}
                </div>
                <div class="col-6 col-sm-4 mb-2">
                    <label for="schedule_interval" class="control-label">
                        {{ __('Interval') }}
                    </label>
                    {{ schedule.interval }}
                </div>
                <div class="col-6 col-sm-4 mb-2">
                    <label for="schedule_start" class="control-label">
                        {{ __('Start date') }}
                    </label>
                    {{ formattedDate(schedule.start_date) }}
                </div>
                <div
                    class="col-6 col-sm-4 mb-2"
                    v-if="isSchedule"
                >
                    <label for="schedule_next" class="control-label">
                        {{ __('Next date') }}
                    </label>

                    <span v-if="schedule.next_date">{{ formattedDate(schedule.next_date) }}</span>
                    <span v-else class="text-muted text-italic">{{ __('Not set') }}</span>
                </div>
                <div
                    class="col-6 col-sm-4 mb-2"
                >
                    <label for="schedule_count" class="control-label">
                        {{ __('Count') }}
                    </label>
                    {{ schedule.count }}
                </div>
                <div
                    class="col-6 col-sm-4 mb-2"
                >
                    <label for="schedule_end" class="control-label">
                        {{ __('End date') }}
                    </label>

                    <span v-if="schedule.end_date">{{ formattedDate(schedule.end_date) }}</span>
                    <span v-else class="text-muted text-italic">{{ __('Not set') }}</span>
                </div>
                <div
                    class="col-6 col-sm-4 mb-2"
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
            locale: {
                type: String,
                default: window.YAFFA.locale,
            }
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

                return newDate.toLocaleDateString(this.locale);
            },
        }
    }
</script>
