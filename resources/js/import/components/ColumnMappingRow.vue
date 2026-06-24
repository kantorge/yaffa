<template>
  <div class="mb-3">
    <div class="d-flex align-items-start gap-2 flex-wrap">
      <!-- Column header label -->
      <div
        class="small fw-medium text-nowrap pt-1"
        style="min-width: 140px; max-width: 200px; overflow: hidden; text-overflow: ellipsis"
        :title="header"
      >
        {{ header }}
      </div>

      <!-- Canonical field dropdown -->
      <select
        v-model="localMapping"
        class="form-select form-select-sm"
        style="max-width: 170px"
        @change="onMappingChange"
      >
        <option value="ignore">{{ __('— Ignore —') }}</option>
        <option value="date">{{ __('date') }}</option>
        <option value="amount">{{ __('amount') }}</option>
        <option value="payee">{{ __('payee') }}</option>
        <option value="comment">{{ __('comment') }}</option>
        <option value="reference">{{ __('reference') }}</option>
        <option value="category">{{ __('category') }}</option>
      </select>

      <!-- First sample value preview -->
      <div
        v-if="sampleValues.length && localMapping !== 'ignore'"
        class="small text-muted font-monospace text-truncate pt-1"
        style="max-width: 180px"
        :title="sampleValues[0]"
      >
        {{ sampleValues[0] }}
      </div>

      <!-- Duplicate mapping warning badge -->
      <span
        v-if="validationWarning"
        class="badge bg-warning text-dark small align-self-center"
      >
        {{ validationWarning }}
      </span>
    </div>

    <!-- Date format selector appears when column is mapped to 'date' -->
    <DateFormatSelector
      v-if="localMapping === 'date'"
      :sample-values="sampleValues"
      :model-value="dateFormat"
      @update:model-value="$emit('update:dateFormat', $event)"
    />

    <!-- Amount parsing preview appears when column is mapped to 'amount' -->
    <AmountFormatPreview
      v-if="localMapping === 'amount'"
      :raw-value="sampleValues[0] || ''"
      :decimal-separator="decimalSeparator"
      :thousand-separator="thousandSeparator"
    />

    <!-- AI confidence note -->
    <div v-if="confidenceNote" class="small text-info mt-1 ms-2 ps-1">
      <i class="fa fa-info-circle me-1"></i>{{ confidenceNote }}
    </div>
  </div>
</template>

<script>
  import { __ } from '@/shared/lib/i18n';
  import DateFormatSelector from './DateFormatSelector.vue';
  import AmountFormatPreview from './AmountFormatPreview.vue';

  export default {
    name: 'ColumnMappingRow',
    components: { DateFormatSelector, AmountFormatPreview },
    props: {
      header: {
        type: String,
        required: true,
      },
      sampleValues: {
        type: Array,
        default: () => [],
      },
      modelValue: {
        type: String,
        default: 'ignore',
      },
      dateFormat: {
        type: String,
        default: '',
      },
      decimalSeparator: {
        type: String,
        default: '.',
      },
      thousandSeparator: {
        type: String,
        default: '',
      },
      validationWarning: {
        type: String,
        default: '',
      },
      confidenceNote: {
        type: String,
        default: '',
      },
    },
    emits: ['update:modelValue', 'update:dateFormat'],
    data() {
      return {
        localMapping: this.modelValue,
      };
    },
    watch: {
      modelValue(val) {
        this.localMapping = val;
      },
    },
    methods: {
      __,
      onMappingChange() {
        this.$emit('update:modelValue', this.localMapping);
      },
    },
  };
</script>
