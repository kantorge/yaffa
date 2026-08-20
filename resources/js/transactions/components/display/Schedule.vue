<template>
  <div class="card mb-3" v-show="isVisible">
    <div class="card-header">
      <div class="card-title">
        {{ __('Schedule') }}
      </div>
    </div>
    <div class="card-body">
      <div class="schedule-groups">
        <div class="p-3 rounded border">
          <h6 class="text-muted text-uppercase small mb-2">{{ __('Pattern') }}</h6>
          <dl class="field-grid mb-0">
            <div>
              <dt class="mb-1">{{ __('Repeats every') }}</dt>
              <dd class="mb-0">{{ schedule.interval }} {{ frequencyLabel }}</dd>
            </div>
            <div v-if="showPattern">
              <dt class="mb-1">{{ __('Pattern') }}</dt>
              <dd class="mb-0">{{ patternDescription }}</dd>
            </div>
          </dl>
        </div>

        <div class="p-3 rounded border">
          <h6 class="text-muted text-uppercase small mb-2">{{ __('Range') }}</h6>
          <dl class="field-grid field-grid-2col mb-0">
            <div>
              <dt class="mb-1">{{ __('Start date') }}</dt>
              <dd class="mb-0">{{ formattedDate(schedule.start_date) }}</dd>
            </div>
            <div v-if="isSchedule">
              <dt class="mb-1">{{ __('Next date') }}</dt>
              <dd class="mb-0">
                <span v-if="schedule.next_date">{{
                  formattedDate(schedule.next_date)
                }}</span>
                <span v-else class="text-muted text-italic">{{
                  __('Not set')
                }}</span>
              </dd>
            </div>
            <div>
              <dt class="mb-1">{{ __('Count') }}</dt>
              <dd class="mb-0">
                <span
                  v-if="
                    typeof schedule.count !== 'undefined' &&
                    schedule.count !== null
                  "
                >
                  {{ schedule.count }}
                </span>
                <span v-else class="text-muted text-italic">{{
                  __('Not set')
                }}</span>
              </dd>
            </div>
            <div>
              <dt class="mb-1">{{ __('End date') }}</dt>
              <dd class="mb-0">
                <span v-if="schedule.end_date">{{
                  formattedDate(schedule.end_date)
                }}</span>
                <span v-else class="text-muted text-italic">{{
                  __('Not set')
                }}</span>
              </dd>
            </div>
          </dl>
        </div>

        <div class="p-3 rounded border" v-if="isSchedule">
          <h6 class="text-muted text-uppercase small mb-2">{{ __('Behavior') }}</h6>
          <dl class="field-grid mb-0">
            <div>
              <dt class="mb-1">{{ __('Automatic recording') }}</dt>
              <dd class="mb-0">
                <span v-if="schedule.automatic_recording">
                  <i class="fa fa-check text-success" :title="__('Yes')"></i>
                </span>
                <span v-else
                  ><i class="fa fa-ban text-danger" :title="__('No')"></i
                ></span>
              </dd>
            </div>
          </dl>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
  import { __, toFormattedDate } from '@/shared/lib/i18n';
  import {
    ordinalLabels,
    weekdayLabels,
    monthLabels,
    scheduleStartDateParts,
  } from '@/shared/lib/helpers';

  // Unit nouns for the "Repeats every :interval :unit" line, matching the
  // options in the schedule edit form's frequency select (TransactionSchedule.vue).
  const frequencyLabels = {
    DAILY: __('schedule.daily'),
    WEEKLY: __('schedule.weekly'),
    MONTHLY: __('schedule.monthly'),
    YEARLY: __('schedule.yearly'),
  };

  /**
   * @property {Object} schedule
   * @property {String} schedule.frequency
   * @property {Number} schedule.interval
   * @property {Number} schedule.count
   * @property {Date} schedule.start_date
   * @property {Date} schedule.next_date
   * @property {Date} schedule.end_date
   * @property {Boolean} schedule.automatic_recording
   * @property {String|null} schedule.by_day RFC5545 ordinal weekday token, e.g. "1WE", "-1FR"
   * @property {Number|null} schedule.by_month 1-12, pins a YEARLY by_day rule to a month
   * @property {Object} window.YAFFA
   */
  export default {
    components: {},

    props: {
      isVisible: Boolean,
      isSchedule: Boolean,
      schedule: Object,
      locale: {
        type: String,
        default: window.YAFFA.userSettings.locale,
      },
    },

    data() {
      return {
        // Shared with the schedule edit form (TransactionSchedule.vue) so the
        // same by_day/by_month values read the same way here.
        ordinalLabels,
        weekdayLabels,
        monthLabels,
      };
    },

    computed: {
      frequencyLabel() {
        return frequencyLabels[this.schedule.frequency] ?? this.schedule.frequency;
      },

      // The pattern row only means anything for MONTHLY/YEARLY (by_day can
      // only be set for those), matching the form's showPatternPicker.
      showPattern() {
        return ['MONTHLY', 'YEARLY'].includes(this.schedule.frequency);
      },

      // e.g. "First Wednesday" or, for a yearly rule pinned to a month,
      // "Last Friday of November". Falls back to the default day-of-month
      // description (matching the form's dayOfMonthPatternLabel) when the
      // schedule doesn't use an ordinal-weekday rule (by_day empty).
      patternDescription() {
        if (!this.schedule.by_day) {
          const day = scheduleStartDateParts(this.schedule.start_date)?.day;

          return day
            ? __('On day :day of the month', { day })
            : __('On the same day each month');
        }

        const ordinal = this.schedule.by_day.slice(0, -2);
        const weekday = this.schedule.by_day.slice(-2);

        const ordinalLabel = this.ordinalLabels[ordinal] ?? ordinal;
        const weekdayLabel = this.weekdayLabels[weekday] ?? weekday;

        if (this.schedule.by_month && this.monthLabels[this.schedule.by_month]) {
          return __(':ordinal :weekday of :month', {
            ordinal: ordinalLabel,
            weekday: weekdayLabel,
            month: this.monthLabels[this.schedule.by_month],
          });
        }

        return __(':ordinal :weekday', {
          ordinal: ordinalLabel,
          weekday: weekdayLabel,
        });
      },
    },

    methods: {
      formattedDate(date) {
        if (typeof date === 'undefined') {
          return;
        }

        return toFormattedDate(date, this.locale, '', true);
      },
      __,
    },
  };
</script>

<style scoped>
  .schedule-groups {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
    gap: 1rem;
  }

  .field-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(9rem, 1fr));
    gap: 1rem;
    align-items: start;
  }

  /* Range's four fields (start/next/count/end) always read as a 2x2 block,
     rather than auto-fit letting a third column squeeze in and leave one
     field wrapped onto its own row. Matches TransactionSchedule.vue. */
  .field-grid-2col {
    grid-template-columns: repeat(2, 1fr);
  }
</style>
