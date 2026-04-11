<template>
  <div class="card mb-3">
    <div class="card-header">
      <div
        class="card-title collapse-control"
        :class="{ collapsed: !expanded }"
        data-coreui-toggle="collapse"
        :data-coreui-target="'#' + cardId"
      >
        <i class="fa fa-angle-down"></i>
        {{ title }}
      </div>
    </div>
    <div
      class="card-body collapse"
      :class="{ show: expanded }"
      :aria-expanded="expanded"
      :id="cardId"
    >
      <div :id="dateRangePickerId">
        <div class="row">
          <div class="col-6">
            <label :for="dateFromId" class="form-label">{{
              __('Date from')
            }}</label>
            <input
              type="text"
              class="form-control"
              :name="dateFromId"
              :id="dateFromId"
              :placeholder="__('Select date')"
              autocomplete="off"
            />
          </div>
          <div class="col-6">
            <label :for="dateToId" class="form-label">{{
              __('Date to')
            }}</label>
            <input
              type="text"
              class="form-control"
              :name="dateToId"
              :id="dateToId"
              :placeholder="__('Select date')"
              autocomplete="off"
            />
          </div>
        </div>
        <div class="row mt-2">
          <div class="col-12">
            <select :id="presetsId" class="form-select">
              <option value="none">{{ __('Select preset') }}</option>
              <optgroup
                v-for="(group, index) in datePresets"
                :key="index"
                :label="__(group.label)"
              >
                <option
                  v-for="option in group.options"
                  :key="option.value"
                  :value="option.value"
                >
                  {{ __(option.label) }}
                </option>
              </optgroup>
            </select>
          </div>
        </div>
      </div>
    </div>
    <div class="card-footer text-end">
      <button class="btn btn-sm btn-outline-dark" @click="clearDates">
        {{ __('Clear selection') }}
      </button>
      <button
        v-if="showUpdateButton"
        type="button"
        class="btn btn-sm btn-primary ms-2"
        :id="updateButtonId"
        @click="updateDates"
      >
        {{ __('Update') }}
      </button>
    </div>
  </div>
</template>

<script setup>
  import { onMounted, ref } from 'vue';
  import { __ } from '@/shared/lib/i18n';
  import DateRangePicker from 'vanillajs-datepicker/DateRangePicker';
  import presetCalculators from '@/shared/lib/date/presetDates';

  const props = defineProps({
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
  });

  const emit = defineEmits(['update']);

  const cardId = `card${props.componentId}`;
  const dateRangePickerId = `${props.componentId}Picker`;
  const dateFromId = `${props.componentId}_from`;
  const dateToId = `${props.componentId}_to`;
  const presetsId = `${props.componentId}Presets`;
  const updateButtonId = `${props.componentId}Update`;

  const datePresets = window.YAFFA?.config?.datePresets || [];
  const isPresetChange = ref(false);
  let dateRangePicker = null;

  onMounted(() => {
    // Initialize date range picker
    dateRangePicker = new DateRangePicker(
      document.getElementById(dateRangePickerId),
      {
        allowOneSidedRange: true,
        weekStart: 1,
        todayBtn: true,
        todayBtnMode: 1,
        todayHighlight: true,
        language: window.YAFFA.userSettings.language,
        format: 'yyyy-mm-dd',
        autohide: true,
        buttonClass: 'btn',
      },
    );

    // Attach event listeners
    document
      .getElementById(dateFromId)
      .addEventListener('changeDate', handleDateChange);
    document
      .getElementById(dateToId)
      .addEventListener('changeDate', handleDateChange);

    // Listener for the date range presets
    document
      .getElementById(presetsId)
      .addEventListener('change', handlePresetChange);

    let shouldEmitOnMount = false;

    // Set initial dates if provided
    if (props.initialDateFrom || props.initialDateTo) {
      const start = props.initialDateFrom
        ? props.initialDateFrom
        : { clear: true };
      const end = props.initialDateTo ? props.initialDateTo : { clear: true };

      dateRangePicker.setDates(start, end);
      shouldEmitOnMount = true;
    } else if (props.initialPreset && props.initialPreset !== 'none') {
      // If date preset is set, apply it
      document.getElementById(presetsId).value = props.initialPreset;
      const event = new Event('change');
      document.getElementById(presetsId).dispatchEvent(event);
      shouldEmitOnMount = !props.showUpdateButton;
    }

    if (shouldEmitOnMount) {
      emitDates();
    }
  });

  const handleDateChange = () => {
    if (!isPresetChange.value) {
      document.getElementById(presetsId).value = 'none';
    }

    if (props.updateUrl) {
      rebuildUrl({ usePreset: false });
    }

    if (!props.showUpdateButton) {
      emitDates();
    }
  };

  const handlePresetChange = (event) => {
    isPresetChange.value = true;
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

    dateRangePicker.setDates(start, end);

    isPresetChange.value = false;

    if (props.updateUrl) {
      rebuildUrl({ usePreset: true });
    }

    if (!props.showUpdateButton) {
      emitDates();
    }
  };

  const clearDates = () => {
    dateRangePicker.setDates({ clear: true }, { clear: true });
    document.getElementById(presetsId).value = 'none';

    if (props.updateUrl) {
      rebuildUrl({ usePreset: false });
    }

    emitDates();
  };

  const updateDates = () => {
    emitDates();
  };

  const emitDates = () => {
    const dates = dateRangePicker.getDates('yyyy-mm-dd');
    const preset = document.getElementById(presetsId).value;

    emit('update', {
      dateFrom: dates[0] || null,
      dateTo: dates[1] || null,
      preset: preset !== 'none' ? preset : null,
    });
  };

  const rebuildUrl = ({ usePreset }) => {
    const params = [];
    const preset = document.getElementById(presetsId).value;

    if (usePreset && preset && preset !== 'none') {
      params.push('date_preset=' + preset);
    } else {
      const dates = dateRangePicker.getDates('yyyy-mm-dd');
      // Date from
      if (dates[0]) {
        params.push('date_from=' + dates[0]);
      }

      // Date to
      if (dates[1]) {
        params.push('date_to=' + dates[1]);
      }
    }

    window.history.pushState(
      '',
      '',
      window.location.origin +
        window.location.pathname +
        (params.length ? '?' + params.join('&') : ''),
    );
  };

  defineExpose({ emitDates });
</script>
