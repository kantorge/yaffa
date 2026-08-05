<template>
  <div class="card mb-3" v-show="isVisible">
    <div class="card-header">
      <div class="card-title">
        {{ __('Schedule') }}
      </div>
    </div>
    <div class="card-body">
      <div class="row mb-0">
        <div class="col-12 col-md-6">
          <dl class="row mb-0">
            <dt class="col-6 mb-2">
              {{ __('Frequency') }}
            </dt>
            <dd class="col-6 mb-2">
              {{ schedule.frequency }}
            </dd>

            <dt class="col-6 mb-2">
              {{ __('Interval') }}
            </dt>
            <dd class="col-6 mb-2">
              {{ schedule.interval }}
            </dd>

            <dt class="col-6 mb-2" v-if="patternDescription">
              {{ __('Pattern') }}
            </dt>
            <dd class="col-6 mb-2" v-if="patternDescription">
              {{ patternDescription }}
            </dd>

            <dt class="col-6 mb-2">
              {{ __('Count') }}
            </dt>
            <dd class="col-6 mb-2">
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

            <dt class="col-6 mb-2" v-if="isBudget">
              {{ __('Budget inflation') }}
            </dt>
            <dd class="col-6 mb-2" v-if="isBudget">
              <span
                v-if="
                  typeof schedule.inflation !== 'undefined' &&
                  schedule.inflation !== null
                "
              >
                {{ schedule.inflation }}%
              </span>
              <span v-else class="text-muted text-italic">{{
                __('Not set')
              }}</span>
            </dd>
          </dl>
        </div>
        <div class="col-12 col-md-6">
          <dl class="row mb-0">
            <dt class="col-6 mb-2">
              {{ __('Start date') }}
            </dt>
            <dd class="col-6 mb-2">
              {{ formattedDate(schedule.start_date) }}
            </dd>

            <dt class="col-6 mb-2" v-if="isSchedule">
              {{ __('Next date') }}
            </dt>
            <dd class="col-6 mb-2" v-if="isSchedule">
              <span v-if="schedule.next_date">{{
                formattedDate(schedule.next_date)
              }}</span>
              <span v-else class="text-muted text-italic">{{
                __('Not set')
              }}</span>
            </dd>

            <dt class="col-6 mb-2" v-if="isSchedule">
              {{ __('Automatic recording') }}
            </dt>
            <dd class="col-6 mb-2" v-if="isSchedule">
              <span v-if="schedule.automatic_recording">
                <i class="fa fa-check text-success" :title="__('Yes')"></i>
              </span>
              <span v-else
                ><i class="fa fa-ban text-danger" :title="__('No')"></i
              ></span>
            </dd>

            <dt class="col-6 mb-2">
              {{ __('End date') }}
            </dt>
            <dd class="col-6 mb-2">
              <span v-if="schedule.end_date">{{
                formattedDate(schedule.end_date)
              }}</span>
              <span v-else class="text-muted text-italic">{{
                __('Not set')
              }}</span>
            </dd>
          </dl>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
  import { __, toFormattedDate } from '@/shared/lib/i18n';

  /**
   * @property {Object} schedule
   * @property {String} schedule.frequency
   * @property {Number} schedule.interval
   * @property {Number} schedule.count
   * @property {Date} schedule.start_date
   * @property {Date} schedule.next_date
   * @property {Date} schedule.end_date
   * @property {Number} schedule.inflation
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
      isBudget: Boolean,
      schedule: Object,
      locale: {
        type: String,
        default: window.YAFFA.userSettings.locale,
      },
    },

    data() {
      return {
        // Mirrors the option labels used by the schedule edit form
        // (TransactionSchedule.vue) so the same by_day/by_month values read
        // the same way here.
        ordinalLabels: {
          1: __('First'),
          2: __('Second'),
          3: __('Third'),
          4: __('Fourth'),
          '-1': __('Last'),
        },
        weekdayLabels: {
          SU: __('Sunday'),
          MO: __('Monday'),
          TU: __('Tuesday'),
          WE: __('Wednesday'),
          TH: __('Thursday'),
          FR: __('Friday'),
          SA: __('Saturday'),
        },
        monthLabels: {
          1: __('January'),
          2: __('February'),
          3: __('March'),
          4: __('April'),
          5: __('May'),
          6: __('June'),
          7: __('July'),
          8: __('August'),
          9: __('September'),
          10: __('October'),
          11: __('November'),
          12: __('December'),
        },
      };
    },

    computed: {
      // e.g. "First Wednesday" or, for a yearly rule pinned to a month,
      // "Last Friday of November". Null when the schedule uses the default
      // day-of-month pattern (by_day empty), so the row can be hidden.
      patternDescription() {
        if (!this.schedule.by_day) {
          return null;
        }

        const ordinal = this.schedule.by_day.slice(0, -2);
        const weekday = this.schedule.by_day.slice(-2);

        const ordinalLabel = this.ordinalLabels[ordinal] ?? ordinal;
        const weekdayLabel = this.weekdayLabels[weekday] ?? weekday;

        let description = `${ordinalLabel} ${weekdayLabel}`;

        if (this.schedule.by_month && this.monthLabels[this.schedule.by_month]) {
          description += ` ${__('of')} ${this.monthLabels[this.schedule.by_month]}`;
        }

        return description;
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
