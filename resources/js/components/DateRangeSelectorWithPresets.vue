<template>
  <div class="card mb-3">
    <div class="card-header">
      <div class="card-title">
        {{ __('Date') }}
      </div>
    </div>
    <div class="card-body" ref="dateRangePicker">
      <div class="row">
        <div class="col-6">
          <label for="date_from" class="form-label">{{ __('Date from') }}</label>
          <input
            type="text"
            class="form-control"
            name="date_from"
            id="date_from"
            :placeholder="__('Select date')"
            autocomplete="off"
          >
        </div>
        <div class="col-6">
          <label for="date_to" class="form-label">{{ __('Date to') }}</label>
          <input
            type="text"
            class="form-control"
            name="date_to"
            id="date_to"
            :placeholder="__('Select date')"
            autocomplete="off"
          >
        </div>
      </div>
      <div class="row mt-2">
        <div class="col-12">
          <select
            id="dateRangePickerPresets"
            class="form-select"
            @change="onPresetSelect"
          >
            <option value="none">{{ __('Select preset') }}</option>
            <optgroup
              v-for="group in presetGroups"
              :key="group.label"
              :label="__(group.label)"
            >
              <option
                v-for="option in group.options"
                :key="option.value"
                :value="option.value"
              >{{ __(option.label) }}</option>
            </optgroup>
          </select>
        </div>
      </div>
    </div>
    <div class="card-footer text-end">
      <button
        class="btn btn-sm btn-outline-dark"
        @click="clearSelection"
      >{{ __('Clear selection') }}</button>
      <button
        v-if="showUpdateButton"
        type="button"
        class="btn btn-sm btn-primary ms-2"
        @click="triggerUpdate"
      >{{ __('Update') }}</button>
    </div>
  </div>
</template>

<script>
import DateRangePicker from 'vanillajs-datepicker/DateRangePicker';
import presetCalculators from '../presetDates';

export default {
  name: 'DateRangeSelectorWithPresets',
  emits: ['update', 'dateChange'],
  props: {
    initialDateFrom: {
      type: String,
      default: null,
    },
    initialDateTo: {
      type: String,
      default: null,
    },
    initialPreset: {
      type: String,
      default: 'none',
    },
    showUpdateButton: {
      type: Boolean,
      default: false,
    },
    presetGroups: {
      type: Array,
      default: () => [
        {
          label: 'Current period',
          options: [
            { value: 'thisMonth', label: 'This month' },
            { value: 'thisQuarter', label: 'This quarter' },
            { value: 'thisYear', label: 'This year' },
            { value: 'thisMonthToDate', label: 'This month to date' },
            { value: 'thisQuarterToDate', label: 'This quarter to date' },
            { value: 'thisYearToDate', label: 'This year to date' },
          ],
        },
        {
          label: 'Recent periods',
          options: [
            { value: 'yesterday', label: 'Yesterday' },
            { value: 'previous7Days', label: 'Previous 7 days' },
            { value: 'previous30Days', label: 'Previous 30 days' },
            { value: 'previous90Days', label: 'Previous 90 days' },
            { value: 'previous180Days', label: 'Previous 180 days' },
            { value: 'previous365Days', label: 'Previous 365 days' },
          ],
        },
        {
          label: 'Previous period',
          options: [
            { value: 'previousMonth', label: 'Previous month' },
            { value: 'previousMonthToDate', label: 'Previous month to date' },
          ],
        },
      ],
    },
  },
  data() {
    return {
      dateFrom: this.initialDateFrom,
      dateTo: this.initialDateTo,
      dateRangePicker: null,
      isPresetChange: false,
    };
  },
  mounted() {
    this.dateRangePicker = new DateRangePicker(
      this.$refs.dateRangePicker,
      {
        allowOneSidedRange: true,
        weekStart: 1,
        todayBtn: true,
        todayBtnMode: 1,
        todayHighlight: true,
        language: window.YAFFA ? window.YAFFA.language : 'en',
        format: 'yyyy-mm-dd',
        autohide: true,
        buttonClass: 'btn',
      }
    );

    // Set initial dates if provided
    if (this.initialDateFrom || this.initialDateTo) {
      const start = this.initialDateFrom || { clear: true };
      const end = this.initialDateTo || { clear: true };
      this.dateRangePicker.setDates(start, end);
    }

    // Set initial preset if provided
    if (this.initialPreset && this.initialPreset !== 'none') {
      const selectElement = document.getElementById('dateRangePickerPresets');
      if (selectElement) {
        selectElement.value = this.initialPreset;
        this.isPresetChange = true;
        this.onPresetSelect({ target: selectElement });
        this.isPresetChange = false;
      }
    }

    // Attach event listeners to date inputs
    const dateFromInput = this.$refs.dateRangePicker.querySelector('[name="date_from"]');
    const dateToInput = this.$refs.dateRangePicker.querySelector('[name="date_to"]');

    if (dateFromInput && dateToInput) {
      dateFromInput.addEventListener('changeDate', this.onDateChange);
      dateToInput.addEventListener('changeDate', this.onDateChange);
    }
  },
  beforeUnmount() {
    if (this.dateRangePicker) {
      this.dateRangePicker.destroy();
    }
  },
  methods: {
    clearSelection() {
      this.dateRangePicker.setDates({ clear: true }, { clear: true });
      this.dateFrom = null;
      this.dateTo = null;

      // Reset the preset select
      const select = document.getElementById('dateRangePickerPresets');
      if (select) {
        select.value = 'none';
      }

      this.emitUpdate();
    },
    onDateChange() {
      if (!this.dateRangePicker) {
        return;
      }

      // If not a preset change, reset the preset selector
      if (!this.isPresetChange) {
        const select = document.getElementById('dateRangePickerPresets');
        if (select) {
          select.value = 'none';
        }
      }

      const dates = this.dateRangePicker.getDates('yyyy-mm-dd');
      this.dateFrom = dates[0] || null;
      this.dateTo = dates[1] || null;

      if (!this.showUpdateButton) {
        this.emitUpdate();
      }

      this.$emit('dateChange', {
        dateFrom: this.dateFrom,
        dateTo: this.dateTo,
        preset: this.isPresetChange ? document.getElementById('dateRangePickerPresets')?.value : 'none',
      });
    },
    onPresetSelect(event) {
      this.isPresetChange = true;
      const preset = event.target.value;
      const date = new Date();
      let start;
      let end;

      // Get the start and end dates based on the selected preset
      const calculator = presetCalculators[preset];
      if (calculator) {
        const dates = calculator(date);
        start = dates.start;
        end = dates.end;
      } else {
        start = { clear: true };
        end = { clear: true };
      }

      this.dateRangePicker.setDates(start, end);
      this.isPresetChange = false;

      // Manually trigger onDateChange after setting dates
      this.onDateChange();
    },
    triggerUpdate() {
      this.emitUpdate();
    },
    emitUpdate() {
      this.$emit('update', {
        dateFrom: this.dateFrom,
        dateTo: this.dateTo,
        preset: document.getElementById('dateRangePickerPresets')?.value || 'none',
      });
    },
    getDates() {
      if (this.dateRangePicker) {
        return this.dateRangePicker.getDates('yyyy-mm-dd');
      }
      return [this.dateFrom, this.dateTo];
    },
    __: function (string, replace) {
      return window.__(string, replace);
    },
  },
};
</script>
