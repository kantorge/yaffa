<template>
  <div
    :class="bare ? null : 'card mb-3'"
    dusk="card-transaction-schedule"
    :id="'transaction_schedule_' + this.$.vnode.key"
  >
    <div class="card-header d-flex justify-content-between" v-if="!bare">
      <div class="card-title">
        {{ title }}
      </div>
      <div v-if="withCheckbox">
        <div class="checkbox">
          <label>
            <input type="checkbox" value="1" v-model="allowCustomizationData" />
            {{ __('Customize') }}
          </label>
        </div>
      </div>
    </div>
    <div :class="bare ? null : 'card-body'">
      <div class="schedule-groups">
        <div class="p-3 rounded border">
          <h6 class="text-muted text-uppercase small mb-2">
            {{ __('Pattern') }}
          </h6>
          <div class="mb-3" :class="hasError('frequency') ? 'has-error' : ''">
            <label class="form-label d-block">
              {{ __('Repeats every') }}
            </label>
            <div class="d-flex gap-2">
              <input
                type="number"
                :disabled="!allowCustomizationData"
                class="form-control schedule-interval-input"
                :id="'schedule_interval_' + this.$.vnode.key"
                v-model="intervalInput"
                min="1"
                step="1"
              />
              <select
                class="form-select"
                :id="'schedule_frequency_' + this.$.vnode.key"
                v-model="schedule.frequency"
                :disabled="!allowCustomizationData"
              >
                <option value="DAILY">{{ __('schedule.daily') }}</option>
                <option value="WEEKLY">{{ __('schedule.weekly') }}</option>
                <option value="MONTHLY">{{ __('schedule.monthly') }}</option>
                <option value="YEARLY">{{ __('schedule.yearly') }}</option>
              </select>
            </div>
          </div>

          <!--
            Always rendered (not just for Monthly/Yearly) so the nth-weekday option
            is discoverable regardless of the currently selected frequency - it's
            simply disabled, with a tooltip, until a compatible frequency is chosen.
          -->
          <div
            class="p-2 rounded bg-body-tertiary"
            :class="{ 'has-error': hasError('by_day') }"
          >
            <div class="form-check mb-1">
              <input
                class="form-check-input"
                type="radio"
                value="dayOfMonth"
                v-model="patternMode"
                :id="'schedule_pattern_day_of_month_' + this.$.vnode.key"
                :disabled="!allowCustomizationData"
              />
              <label
                class="form-check-label"
                :for="'schedule_pattern_day_of_month_' + this.$.vnode.key"
              >
                {{ dayOfMonthPatternLabel }}
              </label>
            </div>
            <div class="d-flex flex-wrap align-items-center gap-2">
              <div class="form-check mb-0">
                <input
                  class="form-check-input"
                  type="radio"
                  value="weekday"
                  v-model="patternMode"
                  dusk="radio-schedule-pattern-weekday"
                  :id="'schedule_pattern_weekday_' + this.$.vnode.key"
                  :disabled="!allowCustomizationData || !showPatternPicker"
                  :title="
                    !showPatternPicker
                      ? __('Available for monthly or yearly frequency')
                      : ''
                  "
                />
                <label
                  class="form-check-label"
                  :for="'schedule_pattern_weekday_' + this.$.vnode.key"
                >
                  {{ weekdayPatternLabel }}
                </label>
              </div>
              <template v-if="patternMode === 'weekday' && showPatternPicker">
                <select
                  class="form-select schedule-ordinal-select"
                  dusk="select-schedule-by-day-ordinal"
                  :id="'schedule_by_day_ordinal_' + this.$.vnode.key"
                  v-model="byDayOrdinal"
                  :disabled="!allowCustomizationData"
                >
                  <option
                    v-for="option in ordinalOptions"
                    :key="option.value"
                    :value="option.value"
                  >
                    {{ option.label }}
                  </option>
                </select>
                <select
                  class="form-select schedule-weekday-select"
                  dusk="select-schedule-by-day-weekday"
                  :id="'schedule_by_day_weekday_' + this.$.vnode.key"
                  v-model="byDayWeekday"
                  :disabled="!allowCustomizationData"
                >
                  <option
                    v-for="option in weekdayOptions"
                    :key="option.value"
                    :value="option.value"
                  >
                    {{ option.label }}
                  </option>
                </select>
                <template v-if="showMonthPicker">
                  <span class="text-muted">{{ __('of') }}</span>
                  <select
                    class="form-select schedule-month-select"
                    dusk="select-schedule-by-month"
                    :id="'schedule_by_month_' + this.$.vnode.key"
                    v-model.number="schedule.by_month"
                    :disabled="!allowCustomizationData"
                  >
                    <option
                      v-for="option in monthOptions"
                      :key="option.value"
                      :value="option.value"
                    >
                      {{ option.label }}
                    </option>
                  </select>
                </template>
              </template>
            </div>
          </div>
        </div>

        <div class="p-3 rounded border">
          <h6 class="text-muted text-uppercase small mb-2">
            {{ __('Range') }}
          </h6>
          <div class="field-grid field-grid-2col">
            <div
              :class="{ 'has-error': hasError('start_date') || tooManyPeriods }"
            >
              <label
                :for="'schedule_start_' + this.$.vnode.key"
                class="form-label"
              >
                {{ __('Start date') }}
              </label>
              <input
                type="date"
                class="form-control"
                :id="'schedule_start_' + this.$.vnode.key"
                v-model="startDateInput"
                :disabled="!allowCustomizationData"
                required
              />
              <div class="form-text text-danger" v-if="tooManyPeriods">
                {{
                  __(
                    'This pattern spans too many periods (:count) to save. Pick a more recent start date or a less frequent recurrence.',
                    { count: estimatedPeriodCount },
                  )
                }}
              </div>
            </div>
            <div
              :class="{
                'has-error': hasError('next_date') || nextDateMismatch,
              }"
              v-if="isSchedule"
            >
              <label
                :for="'schedule_next_' + this.$.vnode.key"
                class="form-label"
              >
                {{ __('Next date') }}
                <span
                  class="fa"
                  :class="
                    !schedule.next_date
                      ? 'fa-warning text-warning'
                      : 'fa-info-circle text-info'
                  "
                  :title="
                    __(
                      'If next date is empty, then this schedule is considered to be finished',
                    )
                  "
                ></span>
              </label>
              <div class="input-group">
                <button
                  class="btn btn-outline-secondary"
                  type="button"
                  :disabled="!allowCustomizationData || !previousOccurrence"
                  :title="__('Move to previous occurrence')"
                  @click="stepNextDate('back')"
                >
                  <i class="fa fa-chevron-left"></i>
                </button>
                <input
                  type="date"
                  class="form-control"
                  :id="'schedule_next_' + this.$.vnode.key"
                  v-model="nextDateInput"
                  :disabled="!allowCustomizationData"
                />
                <button
                  class="btn btn-outline-secondary"
                  type="button"
                  :disabled="!allowCustomizationData || !nextOccurrence"
                  :title="__('Move to next occurrence')"
                  @click="stepNextDate('forward')"
                >
                  <i class="fa fa-chevron-right"></i>
                </button>
                <button
                  class="btn btn-outline-secondary"
                  type="button"
                  :disabled="!allowCustomizationData || !schedule.next_date"
                  :title="__('Clear date')"
                  @click="clearDate('next_date')"
                >
                  <i class="fa fa-times"></i>
                </button>
              </div>
              <div class="form-text text-danger" v-if="nextDateMismatch">
                {{ __('This date does not match the configured pattern') }}
              </div>
            </div>

            <!--
              Count and end date are mutually exclusive (backend enforces this via
              `prohibits`); neither is ever disabled - editing one just clears the
              other via the watchers below, and the placeholder explains why the
              other field went blank. The request validation is the actual source
              of truth, this is a UI aid only.
            -->
            <div :class="{ 'has-error': hasError('count') }">
              <label
                :for="'schedule_count_' + this.$.vnode.key"
                class="form-label"
              >
                {{ __('Count') }}
              </label>
              <input
                type="number"
                class="form-control"
                :id="'schedule_count_' + this.$.vnode.key"
                v-model="countInput"
                :disabled="!allowCustomizationData"
                :placeholder="
                  schedule.end_date ? __('Cleared (end date set)') : ''
                "
                min="1"
                step="1"
              />
            </div>
            <div :class="{ 'has-error': hasError('end_date') }">
              <label
                :for="'schedule_end_' + this.$.vnode.key"
                class="form-label"
              >
                {{ __('End date') }}
                <i
                  class="fa fa-info-circle text-info"
                  v-if="schedule.count"
                  :title="__('Cleared (count set)')"
                ></i>
              </label>
              <div class="input-group">
                <input
                  type="date"
                  class="form-control"
                  :id="'schedule_end_' + this.$.vnode.key"
                  v-model="endDateInput"
                  :disabled="!allowCustomizationData"
                />
                <button
                  class="btn btn-outline-secondary"
                  type="button"
                  :disabled="!allowCustomizationData || !schedule.end_date"
                  :title="__('Clear date')"
                  @click="clearDate('end_date')"
                >
                  <i class="fa fa-times"></i>
                </button>
              </div>
            </div>
          </div>
        </div>

        <div class="p-3 rounded border" v-if="isSchedule || isBudget">
          <h6 class="text-muted text-uppercase small mb-2">
            {{ __('Behavior') }}
          </h6>
          <div class="field-grid">
            <div
              :class="{ 'has-error': hasError('automatic_recording') }"
              v-if="isSchedule"
            >
              <div class="form-check">
                <input
                  class="form-check-input"
                  dusk="checkbox-schedule-automatic-recording"
                  type="checkbox"
                  value="1"
                  v-model="schedule.automatic_recording"
                  :id="'schedule_automatic_recording_' + this.$.vnode.key"
                  :disabled="!allowCustomizationData"
                />
                <label
                  class="form-check-label"
                  :for="'schedule_automatic_recording_' + this.$.vnode.key"
                >
                  {{ __('Automatic recording') }}
                  <i
                    class="fa fa-info-circle text-info"
                    :title="
                      __(
                        'The transaction is automatically entered on the next date.',
                      )
                    "
                  ></i>
                </label>
              </div>
            </div>
            <div :class="{ 'has-error': hasError('inflation') }">
              <label
                :for="'schedule_inflation_' + this.$.vnode.key"
                class="form-label"
              >
                {{ __('Yearly inflation') }}
              </label>
              <div class="input-group">
                <input
                  class="form-control"
                  :id="'schedule_inflation_' + this.$.vnode.key"
                  v-model="schedule.inflation"
                  type="number"
                  step=".01"
                  :disabled="!allowCustomizationData"
                />
                <span class="input-group-text">%</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
  import { RRule } from 'rrule';
  import { __ } from '@/shared/lib/i18n';
  import {
    byDayToRRuleWeekday,
    toDateInputValue,
    toRRuleDate,
    fromRRuleDate,
    ordinalOptions,
    weekdayOptions,
    monthOptions,
    scheduleStartDateParts,
  } from '@/shared/lib/helpers';

  // Mirrors ValidatesRecurrenceRule::MAX_RECURRENCE_PERIODS on the backend - see that
  // constant's doc comment for why 2000.
  const MAX_RECURRENCE_PERIODS = 2000;

  // Calendar-based month/year diffs, mirroring Carbon's diffInMonths()/diffInYears() used by
  // RecurrenceRuleService::estimatePeriodsBetween() on the backend - a whole month/year only
  // counts once the day-of-month has been reached, unlike a fixed 30/365-day average.
  function monthsBetween(start, end) {
    let months = (end.getFullYear() - start.getFullYear()) * 12 + (end.getMonth() - start.getMonth());
    if (end.getDate() < start.getDate()) {
      months -= 1;
    }
    return Math.max(months, 0);
  }

  function yearsBetween(start, end) {
    let years = end.getFullYear() - start.getFullYear();
    if (end.getMonth() < start.getMonth() || (end.getMonth() === start.getMonth() && end.getDate() < start.getDate())) {
      years -= 1;
    }
    return Math.max(years, 0);
  }

  export default {
    props: {
      isSchedule: Boolean,
      isBudget: Boolean,
      schedule: Object,
      form: Object,
      title: {
        type: String,
        default: __('Schedule'),
      },
      // Skips the outer card chrome (header/card-body wrapper) - for callers that already
      // provide their own container, e.g. BudgetForm.vue's modal body.
      bare: {
        type: Boolean,
        default: false,
      },
      withCheckbox: {
        type: Boolean,
        default: false,
      },
      allowCustomization: {
        type: Boolean,
        default: true,
      },
      // Which key under form.errors this instance's fields live at. Needed
      // because this same component is reused for the "replace" action's
      // original-schedule panel (bound to form.original_schedule_config), and
      // for BudgetForm (whose BudgetRequest validates the same fields as flat
      // top-level keys, so it passes an empty prefix).
      fieldPrefix: {
        type: String,
        default: 'schedule_config',
      },
    },

    data() {
      let data = {};

      data.allowCustomizationData = this.allowCustomization;

      data.ordinalOptions = ordinalOptions;
      data.weekdayOptions = weekdayOptions;
      data.monthOptions = monthOptions;

      return data;
    },

    computed: {
      showPatternPicker() {
        return ['MONTHLY', 'YEARLY'].includes(this.schedule.frequency);
      },

      showMonthPicker() {
        return (
          this.schedule.frequency === 'YEARLY' && this.patternMode === 'weekday'
        );
      },

      // Distinguishes the two patterns by what actually varies month to month:
      // a fixed day NUMBER vs. a weekday-based rule. Spells out the day number
      // (once known) so it doesn't read almost identically to the weekday option.
      dayOfMonthPatternLabel() {
        return this.startDateDayOfMonth
          ? __('On day :day of the month', { day: this.startDateDayOfMonth })
          : __('On the same day each month');
      },

      // The "weekday" radio's label doubles as a preview when it isn't selected
      // (the ordinal/weekday selects are hidden then, so the label needs to
      // describe what picking it would do) and reverts to the short form once
      // selected, since the selects themselves take over as the description.
      weekdayPatternLabel() {
        return this.patternMode === 'weekday'
          ? __('On the')
          : __('On a specific weekday (e.g. the first Monday)');
      },

      startDateParts() {
        return scheduleStartDateParts(this.schedule.start_date);
      },

      startDateDayOfMonth() {
        return this.startDateParts?.day ?? null;
      },

      startDateMonth() {
        return this.startDateParts?.month ?? null;
      },

      // Toggles between the default "same day of month as start date"
      // behavior (by_day empty) and an ordinal-weekday rule (by_day set).
      patternMode: {
        get() {
          return this.schedule.by_day ? 'weekday' : 'dayOfMonth';
        },
        set(value) {
          if (value === 'dayOfMonth') {
            this.schedule.by_day = null;
            this.schedule.by_month = null;
            return;
          }

          if (!this.schedule.by_day) {
            this.schedule.by_day = '1MO';
          }

          if (this.schedule.frequency === 'YEARLY' && !this.schedule.by_month) {
            this.schedule.by_month = this.startDateMonth;
          }
        },
      },

      // schedule.by_day is stored as a single RFC5545 token (e.g. "1WE",
      // "-1FR"); these split/join it for the two selects.
      byDayOrdinal: {
        get() {
          return this.schedule.by_day ? this.schedule.by_day.slice(0, -2) : '1';
        },
        set(value) {
          this.schedule.by_day = value + this.byDayWeekday;
        },
      },

      byDayWeekday: {
        get() {
          return this.schedule.by_day ? this.schedule.by_day.slice(-2) : 'MO';
        },
        set(value) {
          this.schedule.by_day = this.byDayOrdinal + value;
        },
      },

      // Native <input type="date"> elements require a 'YYYY-MM-DD' string,
      // while schedule.* fields may hold a Date object (see startDateParts
      // above) - these bridge the two without changing the stored type, so
      // RRule/watchers elsewhere keep working with whatever shape they wrote.
      startDateInput: {
        get() {
          return toDateInputValue(this.schedule.start_date);
        },
        set(value) {
          this.schedule.start_date = value || null;
        },
      },

      nextDateInput: {
        get() {
          return toDateInputValue(this.schedule.next_date);
        },
        set(value) {
          this.schedule.next_date = value || null;
        },
      },

      endDateInput: {
        get() {
          return toDateInputValue(this.schedule.end_date);
        },
        set(value) {
          this.schedule.end_date = value || null;
        },
      },

      // Native <input type="number"> reads/writes plain strings; normalize
      // an emptied field back to null (matching schedule.count/interval's
      // existing nullable-integer convention) instead of leaving ''.
      intervalInput: {
        get() {
          return this.schedule.interval ?? '';
        },
        set(value) {
          this.schedule.interval = value === '' ? null : Number(value);
        },
      },

      countInput: {
        get() {
          return this.schedule.count ?? '';
        },
        set(value) {
          this.schedule.count = value === '' ? null : Number(value);
        },
      },

      // Builds the rrule.js rule for the currently configured pattern, or null
      // if there isn't enough information yet (or the combination is invalid -
      // e.g. by_day set with an incompatible frequency). Shared by the
      // mismatch check below and the next/previous-occurrence step buttons,
      // so both always agree on what the "always active settings" actually are.
      scheduleRule() {
        if (!this.schedule.frequency || !this.schedule.start_date) {
          return null;
        }

        const start = toRRuleDate(this.schedule.start_date);
        if (!start) {
          return null;
        }

        try {
          return new RRule({
            freq: RRule[this.schedule.frequency],
            interval: this.schedule.interval || 1,
            dtstart: start,
            until: this.schedule.end_date
              ? toRRuleDate(this.schedule.end_date)
              : null,
            count: this.schedule.count || null,
            byweekday: this.schedule.by_day
              ? byDayToRRuleWeekday(this.schedule.by_day)
              : null,
            bymonth: this.schedule.by_month || null,
          });
        } catch {
          return null;
        }
      },

      // Client-side mirror of RecurrenceRuleService::estimatePeriodsBetween() on the backend -
      // the number of periods between start_date and today, at the configured
      // frequency/interval, is what drives the cost of every later recurrence calculation.
      // MONTHLY/YEARLY use monthsBetween/yearsBetween; DAILY/WEEKLY use fixed day-per-period
      // averages. This is a UI aid only; the backend validation remains the actual source of
      // truth, so a few periods of drift near the cap doesn't matter.
      estimatedPeriodCount() {
        if (!this.schedule.frequency || !this.schedule.start_date) {
          return 0;
        }

        const start = toRRuleDate(this.schedule.start_date);
        if (!start) {
          return 0;
        }

        const now = new Date();
        const diffDays = (now - start) / (1000 * 60 * 60 * 24);
        if (diffDays <= 0) {
          return 0;
        }

        const interval = this.schedule.interval || 1;

        if (this.schedule.frequency === 'MONTHLY') {
          return Math.floor(monthsBetween(start, now) / interval);
        }

        if (this.schedule.frequency === 'YEARLY') {
          return Math.floor(yearsBetween(start, now) / interval);
        }

        const periodDays = {
          DAILY: 1,
          WEEKLY: 7,
        }[this.schedule.frequency];
        if (!periodDays) {
          return 0;
        }

        return Math.floor(diffDays / (periodDays * interval));
      },

      tooManyPeriods() {
        return this.estimatedPeriodCount > MAX_RECURRENCE_PERIODS;
      },

      // Client-side mirror of TransactionSchedule::occursOn() on the backend:
      // next_date is trusted verbatim wherever a transaction actually gets
      // recorded (automatic recording, manual "enter"), so a next_date that
      // isn't a genuine occurrence of the configured rule is flagged here
      // before the request even round-trips. The backend validation remains
      // the actual source of truth; this is a UI aid only.
      nextDateMismatch() {
        const rule = this.scheduleRule;
        const next = toRRuleDate(this.schedule.next_date);
        if (!rule || !next) {
          return false;
        }

        try {
          return rule.between(next, next, true).length === 0;
        } catch {
          return false;
        }
      },

      // The occurrence immediately before/after the current next_date, per the
      // currently configured pattern. rrule.js naturally treats dtstart as a
      // lower bound and count/until as an upper bound, so these come back null
      // exactly when stepping would move before the start date or past the
      // last date - which is also what disables the step buttons below.
      previousOccurrence() {
        const rule = this.scheduleRule;
        const current = toRRuleDate(this.schedule.next_date);
        if (!rule || !current) {
          return null;
        }

        try {
          return rule.before(current, false);
        } catch {
          return null;
        }
      },

      // When next_date is already set, this is the occurrence right after it.
      // When it's empty (e.g. a brand new schedule), there's nothing to step
      // forward *from* yet, so this falls back to the rule's very first
      // occurrence instead - the start date itself if it matches the pattern,
      // otherwise the first real occurrence on/after it (same "inclusive"
      // lookup used to explain the day-of-month default elsewhere).
      nextOccurrence() {
        const rule = this.scheduleRule;
        if (!rule) {
          return null;
        }

        const current = toRRuleDate(this.schedule.next_date);

        try {
          return current
            ? rule.after(current, false)
            : rule.after(toRRuleDate(this.schedule.start_date), true);
        } catch {
          return null;
        }
      },
    },

    watch: {
      // Keeps the client from ever submitting a by_day/by_month combination
      // the backend would reject (ordinal weekday requires MONTHLY/YEARLY,
      // month only applies to YEARLY).
      'schedule.frequency'(newFrequency) {
        if (!['MONTHLY', 'YEARLY'].includes(newFrequency)) {
          this.schedule.by_day = null;
          this.schedule.by_month = null;
        } else if (newFrequency === 'MONTHLY') {
          this.schedule.by_month = null;
        }
      },

      // Count and end date are mutually exclusive (see the `prohibits` rules
      // in TransactionRequest); setting one here clears the other. Neither
      // field is ever disabled - see the template comment above the Count field.
      'schedule.count'(newCount) {
        if (newCount) {
          this.schedule.end_date = null;
        }
      },

      'schedule.end_date'(newEndDate) {
        if (newEndDate) {
          this.schedule.count = null;
        }
      },
    },

    methods: {
      __,
      hasError(field) {
        const key = this.fieldPrefix ? `${this.fieldPrefix}.${field}` : field;

        return this.form.errors.has(key);
      },
      clearDate(field) {
        this.schedule[field] = null;
      },
      stepNextDate(direction) {
        const target =
          direction === 'back' ? this.previousOccurrence : this.nextOccurrence;
        if (target) {
          this.schedule.next_date = fromRRuleDate(target);
        }
      },
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
     field wrapped onto its own row. */
  .field-grid-2col {
    grid-template-columns: repeat(2, 1fr);
  }

  .schedule-interval-input {
    width: 4.5rem;
  }

  .schedule-ordinal-select {
    width: 7rem;
  }

  .schedule-weekday-select,
  .schedule-month-select {
    width: 8.5rem;
  }
</style>
