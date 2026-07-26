<template>
  <div class="card mb-3">
    <div class="card-header">
      <div
        class="card-title collapse-control"
        :class="{ collapsed: !isExpanded }"
        data-coreui-toggle="collapse"
        :data-coreui-target="'#' + cardBodyId"
      >
        <i class="fa fa-angle-down"></i>
        {{ title }}
      </div>
    </div>
    <div
      class="card-body collapse"
      :class="{ show: isExpanded }"
      :id="cardBodyId"
    >
      <div class="row">
        <div class="col-6">
          <label class="form-label">{{ __('Date from') }}</label>
          <DatePicker
            v-model.string="dateFromBinding"
            mode="date"
            :max-date="constraintDateTo"
            :is-dark="isDarkMode"
            :locale="locale"
            :first-day-of-week="2"
            :masks="{ L: 'YYYY-MM-DD', modelValue: 'YYYY-MM-DD' }"
            :popover="{ visibility: 'click', showDelay: 0, hideDelay: 0 }"
          >
            <template #default="{ inputValue, inputEvents }">
              <input
                type="text"
                class="form-control"
                :id="componentId + '_from'"
                :value="inputValue"
                v-on="inputEvents"
                :placeholder="__('Select date')"
                autocomplete="off"
              />
            </template>
          </DatePicker>
        </div>
        <div class="col-6">
          <label class="form-label">{{ __('Date to') }}</label>
          <DatePicker
            v-model.string="dateToBinding"
            mode="date"
            :min-date="constraintDateFrom"
            :is-dark="isDarkMode"
            :locale="locale"
            :first-day-of-week="2"
            :masks="{ L: 'YYYY-MM-DD', modelValue: 'YYYY-MM-DD' }"
            :popover="{ visibility: 'click', showDelay: 0, hideDelay: 0 }"
          >
            <template #default="{ inputValue, inputEvents }">
              <input
                type="text"
                class="form-control"
                :id="componentId + '_to'"
                :value="inputValue"
                v-on="inputEvents"
                :placeholder="__('Select date')"
                autocomplete="off"
              />
            </template>
          </DatePicker>
        </div>
      </div>
      <div class="row mt-2">
        <div class="col-12">
          <select
            class="form-select"
            :id="componentId + 'Presets'"
            v-model="selectedPreset"
            @change="onPresetChange"
          >
            <option value="none">{{ __('Select preset') }}</option>
            <optgroup
              v-for="group in presetGroups"
              :key="group.label"
              :label="__(group.label)"
            >
              <option
                v-for="opt in group.options"
                :key="opt.value"
                :value="opt.value"
              >
                {{ __(opt.label) }}
              </option>
            </optgroup>
          </select>
        </div>
      </div>
    </div>
    <div class="card-footer text-end">
      <button
        class="btn btn-sm btn-outline-dark"
        :id="componentId + 'Clear'"
        @click="clearDates"
      >
        {{ __('Clear selection') }}
      </button>
      <button
        v-if="showUpdateButton"
        type="button"
        class="btn btn-sm btn-primary ms-2"
        :id="componentId + 'Update'"
        @click="emitDates"
      >
        {{ __('Update') }}
      </button>
    </div>
  </div>
</template>

<script>
  import { DatePicker } from 'v-calendar';
  import { __ } from '@/shared/lib/i18n';
  import { colorModeMixin } from '@/shared/lib/ui/colorModeMixin';
  import presetCalculators from '@/shared/lib/date/presetDates';

  function parseDate(str) {
    if (!str) return null;
    const [y, m, d] = str.split('-').map(Number);
    return new Date(y, m - 1, d);
  }

  function formatDate(date) {
    if (!date) return null;
    const d = date instanceof Date ? date : new Date(date);
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${y}-${m}-${day}`;
  }

  function findPresetOption(groups, preset) {
    if (!preset || preset === 'none') {
      return null;
    }

    return (groups || [])
      .flatMap((group) => group.options || [])
      .find((option) => option.value === preset) || null;
  }

  function resolvePresetDates(preset, groups) {
    const option = findPresetOption(groups, preset);
    if (option?.date_from && option?.date_to) {
      return {
        start: parseDate(option.date_from),
        end: parseDate(option.date_to),
      };
    }

    const calculator = presetCalculators[preset];
    return calculator ? calculator(new Date()) : null;
  }

  export default {
    name: 'DateRangeFilterCard',
    components: { DatePicker },
    mixins: [colorModeMixin],
    emits: ['update'],
    props: {
      expanded: {
        type: Boolean,
        default: true,
      },
      showUpdateButton: {
        type: Boolean,
        default: false,
      },
      componentId: {
        type: String,
        default: 'dateRangeFilter',
      },
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
        default: null,
      },
      updateUrl: {
        type: Boolean,
        default: false,
      },
      title: {
        type: String,
        default: () => __('Date'),
      },
      presetGroups: {
        type: Array,
        default: () => window.YAFFA?.config?.datePresets || [],
      },
    },
    data() {
      const preset =
        this.initialPreset && this.initialPreset !== 'none'
          ? this.initialPreset
          : 'none';
      let dateFrom = this.initialDateFrom;
      let dateTo = this.initialDateTo;

      if (!dateFrom && !dateTo && preset !== 'none') {
        const dates = resolvePresetDates(preset, this.presetGroups);
        if (dates) {
          dateFrom = formatDate(dates.start);
          dateTo = formatDate(dates.end);
        }
      }

      return {
        dateFrom,
        dateTo,
        selectedPreset: preset,
        isExpanded: this.expanded,
      };
    },
    computed: {
      cardBodyId() {
        return `card${this.componentId}`;
      },
      locale() {
        return window.YAFFA?.userSettings?.language || 'en';
      },
      // Fresh Date objects derived from strings for v-calendar min/max constraints.
      // Returned from a computed getter (not stored in reactive data) so Vue does
      // not deep-proxy them — native Date methods remain intact in v-calendar.
      constraintDateFrom() {
        return parseDate(this.dateFrom);
      },
      constraintDateTo() {
        return parseDate(this.dateTo);
      },
      // Computed setter for the start date.
      // The setter fires only on user interaction (calendar click or typed input),
      // not on programmatic changes from preset selection, clear, or initial load.
      dateFromBinding: {
        get() {
          return this.dateFrom;
        },
        set(val) {
          const normalized = val || null;
          // v-calendar echoes update:modelValue when its :modelValue prop changes
          // programmatically (e.g. from onPresetChange). Skip if the value is
          // unchanged so we don't accidentally clear selectedPreset.
          if (normalized === this.dateFrom) return;
          this.dateFrom = normalized;
          // New start is after the current end: clear end (start is authoritative)
          if (this.dateFrom && this.dateTo && this.dateFrom > this.dateTo) {
            this.dateTo = null;
          }
          this.selectedPreset = 'none';
          if (this.updateUrl) this.rebuildUrl();
          if (!this.showUpdateButton) this.emitDates();
        },
      },
      // Computed setter for the end date.
      dateToBinding: {
        get() {
          return this.dateTo;
        },
        set(val) {
          const normalized = val || null;
          // Same echo-guard as dateFromBinding.
          if (normalized === this.dateTo) return;
          this.dateTo = normalized;
          // New end is before the current start: clear start (end is authoritative)
          if (this.dateFrom && this.dateTo && this.dateTo < this.dateFrom) {
            this.dateFrom = null;
          }
          this.selectedPreset = 'none';
          if (this.updateUrl) this.rebuildUrl();
          if (!this.showUpdateButton) this.emitDates();
        },
      },
    },
    mounted() {
      if (this.dateFrom || this.dateTo || this.selectedPreset !== 'none') {
        this.emitDates();
      }
    },
    methods: {
      onPresetChange() {
        const dates = resolvePresetDates(this.selectedPreset, this.presetGroups);
        if (dates) {
          this.dateFrom = formatDate(dates.start);
          this.dateTo = formatDate(dates.end);
        } else {
          this.dateFrom = null;
          this.dateTo = null;
        }
        if (this.updateUrl) this.rebuildUrl();
        if (!this.showUpdateButton) this.emitDates();
      },
      clearDates() {
        this.dateFrom = null;
        this.dateTo = null;
        this.selectedPreset = 'none';
        if (this.updateUrl) this.rebuildUrl();
        this.emitDates();
      },
      emitDates() {
        this.$emit('update', {
          dateFrom: this.dateFrom,
          dateTo: this.dateTo,
          preset: this.selectedPreset !== 'none' ? this.selectedPreset : null,
        });
      },
      rebuildUrl() {
        const params = new URLSearchParams(window.location.search);
        if (this.selectedPreset && this.selectedPreset !== 'none') {
          params.set('date_preset', this.selectedPreset);
          params.delete('date_from');
          params.delete('date_to');
        } else if (this.dateFrom || this.dateTo) {
          params.delete('date_preset');
          if (this.dateFrom) params.set('date_from', this.dateFrom);
          else params.delete('date_from');
          if (this.dateTo) params.set('date_to', this.dateTo);
          else params.delete('date_to');
        } else {
          params.delete('date_preset');
          params.delete('date_from');
          params.delete('date_to');
        }
        const qs = params.toString();
        window.history.pushState(
          '',
          '',
          window.location.origin +
            window.location.pathname +
            (qs ? '?' + qs : ''),
        );
      },
      __,
    },
  };
</script>
