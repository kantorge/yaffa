<template>
  <div class="mt-1 ms-2 ps-2 border-start border-2 border-warning">
    <div class="small d-flex align-items-center gap-2">
      <span class="text-muted">{{ __('Preview:') }}</span>
      <template v-if="rawValue">
        <span class="font-monospace text-secondary">{{ rawValue }}</span>
        <span class="text-muted">→</span>
        <span v-if="parsedValue !== null" class="font-monospace text-success fw-medium">
          {{ parsedValue }}
        </span>
        <span v-else class="text-danger">{{ __('Cannot parse') }}</span>
      </template>
      <span v-else class="text-muted fst-italic">{{ __('No sample value') }}</span>
    </div>
  </div>
</template>

<script>
  import { __ } from '@/shared/lib/i18n';

  export default {
    name: 'AmountFormatPreview',
    props: {
      rawValue: {
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
    },
    computed: {
      parsedValue() {
        if (!this.rawValue) {
          return null;
        }
        // Strip trailing 3-letter uppercase currency code (e.g. "HUF", "EUR") — mirrors backend logic
        let cleaned = this.rawValue.trim().replace(/\s*[A-Z]{3}$/, '').trimEnd();

        if (this.thousandSeparator) {
          cleaned = cleaned.split(this.thousandSeparator).join('');
        }

        if (this.decimalSeparator && this.decimalSeparator !== '.') {
          cleaned = cleaned.replace(this.decimalSeparator, '.');
        }

        // Remove any remaining whitespace then strictly validate
        cleaned = cleaned.replace(/\s+/g, '');
        if (!/^-?\d+(\.\d+)?$/.test(cleaned)) {
          return null;
        }

        return parseFloat(cleaned);
      },
    },
    methods: { __ },
  };
</script>
