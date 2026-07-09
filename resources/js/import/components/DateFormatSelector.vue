<template>
  <div :class="bordered ? 'mt-1 ms-2 ps-2 border-start border-2 border-primary' : ''">
    <!-- Sample values with parsed result (suppressed when shown inline in the mapping table) -->
    <div v-if="showSamples && previewSamples.length" class="mb-2">
      <div class="small text-muted mb-1">{{ __('Sample values:') }}</div>
      <div
        v-for="(sample, i) in previewSamples"
        :key="i"
        class="d-flex align-items-center gap-2 small font-monospace mb-1"
      >
        <span class="text-secondary">{{ sample }}</span>
        <template v-if="modelValue">
          <span class="text-muted">→</span>
          <span :class="parsedSample(sample) ? 'text-success' : 'text-danger'">
            {{ parsedSample(sample) || __('(no match)') }}
          </span>
        </template>
      </div>
    </div>

    <!-- Format candidates: auto-detected (✓), locale suggestions, and base generic options -->
    <div class="small text-muted mb-1">{{ __('Format:') }}</div>
    <div class="d-flex flex-wrap gap-2 mb-2">
      <div
        v-for="cand in allCandidates"
        :key="cand.format"
        class="form-check form-check-inline mb-0"
      >
        <input
          :id="`df-${uid}-${cand.format.replace(/[^a-z0-9]/gi, '_')}`"
          type="radio"
          class="form-check-input"
          :value="cand.format"
          :checked="modelValue === cand.format"
          @change="$emit('update:modelValue', cand.format)"
        />
        <label
          :for="`df-${uid}-${cand.format.replace(/[^a-z0-9]/gi, '_')}`"
          class="form-check-label font-monospace small"
          :title="cand.example ? cand.format + ' → ' + cand.example : cand.format"
        >
          {{ cand.format }}<span v-if="cand.detected" class="text-success ms-1">✓</span>
        </label>
      </div>
    </div>

    <!-- Custom format input -->
    <div class="d-flex align-items-center gap-2">
      <input
        type="text"
        class="form-control form-control-sm font-monospace"
        style="max-width: 130px"
        :value="isCustom ? modelValue : ''"
        :placeholder="__('Custom…')"
        :title="customInputTitle"
        @input="$emit('update:modelValue', $event.target.value)"
      />
      <small class="text-muted">{{ __('or custom PHP format') }}</small>
      <a
        href="https://www.php.net/manual/en/datetime.format.php"
        target="_blank"
        rel="noopener"
        class="small text-info"
        :title="__('PHP date format reference')"
      >?</a>
    </div>
  </div>
</template>

<script>
  import { __ } from '@/shared/lib/i18n';
  import { DATE_PATTERNS, tryParseDate } from '../utils/dateFormatUtils.js';

  // Locale prefix → ordered list of locally common PHP date formats
  const LOCALE_FORMAT_MAP = {
    hu:      ['Y.m.d.', 'Y-m-d'],
    de:      ['d.m.Y',  'Y-m-d'],
    at:      ['d.m.Y',  'Y-m-d'],
    pl:      ['d.m.Y',  'Y-m-d'],
    cs:      ['d.m.Y',  'Y-m-d'],
    sk:      ['d.m.Y',  'Y-m-d'],
    'en-us': ['m/d/Y',  'Y-m-d'],
    'en-gb': ['d/m/Y',  'Y-m-d'],
    en:      ['d/m/Y',  'Y-m-d'],
    fr:      ['d/m/Y',  'Y-m-d'],
    es:      ['d/m/Y',  'Y-m-d'],
    it:      ['d/m/Y',  'Y-m-d'],
    pt:      ['d/m/Y',  'Y-m-d'],
    nl:      ['d-m-Y',  'Y-m-d'],
  };

  // Always-visible generic options (filled in after locale and detected ones)
  const BASE_FORMATS = ['Y-m-d', 'd/m/Y', 'd.m.Y', 'Y.m.d.'];

  // Tooltip example outputs per format string
  const FORMAT_EXAMPLES = {
    'Y-m-d':  '2026-03-27',
    'Y.m.d.': '2026.03.27.',
    'Y/m/d':  '2026/03/27',
    'd.m.Y':  '27.03.2026',
    'd/m/Y':  '27/03/2026',
    'm/d/Y':  '03/27/2026',
    'd-m-Y':  '27-03-2026',
    'd.m.y':  '27.03.26',
    'd/m/y':  '27/03/26',
    'm/d/y':  '03/27/26',
  };

  function getLocaleFormats() {
    const lang = (navigator.language || 'en').toLowerCase();
    if (LOCALE_FORMAT_MAP[lang]) return LOCALE_FORMAT_MAP[lang];
    const prefix = lang.split('-')[0];
    return LOCALE_FORMAT_MAP[prefix] || ['d/m/Y', 'Y-m-d'];
  }

  let _uid = 0;

  export default {
    name: 'DateFormatSelector',

    props: {
      sampleValues: {
        type: Array,
        default: () => [],
      },
      modelValue: {
        type: String,
        default: '',
      },
      /** Set to false when used inline in the mapping table (data cells below already show parsed values). */
      showSamples: {
        type: Boolean,
        default: true,
      },
      /** Set to false when used as a standalone section (removes left-border indent). */
      bordered: {
        type: Boolean,
        default: true,
      },
    },

    emits: ['update:modelValue'],

    data() {
      return { uid: ++_uid };
    },

    computed: {
      previewSamples() {
        return this.sampleValues.filter(Boolean).slice(0, 3);
      },

      detectedFormats() {
        const seen = new Set();
        const result = [];
        for (const p of DATE_PATTERNS) {
          if (seen.has(p.format)) continue;
          const anyMatch = this.sampleValues.some((v) => v && p.regex.test(String(v).trim()));
          if (anyMatch) {
            result.push(p.format);
            seen.add(p.format);
          }
        }
        return result.slice(0, 5);
      },

      allCandidates() {
        const seen = new Set();
        const result = [];

        // 1. Auto-detected from sample values (marked ✓)
        for (const f of this.detectedFormats) {
          result.push({ format: f, detected: true, example: FORMAT_EXAMPLES[f] || null });
          seen.add(f);
        }

        // 2. Locale-specific suggestions not already listed
        for (const f of getLocaleFormats()) {
          if (!seen.has(f)) {
            result.push({ format: f, detected: false, example: FORMAT_EXAMPLES[f] || null });
            seen.add(f);
          }
        }

        // 3. Generic base formats to ensure at least a few options are always visible
        for (const f of BASE_FORMATS) {
          if (!seen.has(f)) {
            result.push({ format: f, detected: false, example: FORMAT_EXAMPLES[f] || null });
            seen.add(f);
          }
        }

        return result;
      },

      isCustom() {
        if (!this.modelValue) return false;
        return !this.allCandidates.some((c) => c.format === this.modelValue);
      },

      customInputTitle() {
        return __('PHP date format string. Trailing text after the date (e.g. a weekday name) is ignored. Examples: Y.m.d. → 2026.03.27.  |  d/m/Y → 27/03/2026  |  Y-m-d → 2026-03-27');
      },
    },

    methods: {
      __,
      parsedSample(value) {
        return tryParseDate(value, this.modelValue);
      },
    },
  };
</script>
