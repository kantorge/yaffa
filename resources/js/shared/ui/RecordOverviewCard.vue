<template>
  <div class="card mb-3">
    <div class="card-header">
      <div
        class="card-title collapse-control"
        data-coreui-toggle="collapse"
        data-coreui-target="#cardOverview"
      >
        <i class="fa fa-angle-down"></i>
        {{ __('Overview') }}
      </div>
    </div>
    <div id="cardOverview" class="collapse card-body show" aria-expanded="true">
      <dl class="row mb-0">
        <template v-for="row in headerRows" :key="row.label">
          <dt class="col-6">{{ row.label }}</dt>
          <dd class="col-6">{{ row.value }}</dd>
        </template>
        <dt class="col-6">{{ __('Number of records') }}</dt>
        <dd class="col-6">{{ records.length }}</dd>
        <dt class="col-6">{{ __('First available data') }}</dt>
        <dd v-if="records.length > 0" class="col-6">
          {{ formatDate(records[0][dateField]) }}
        </dd>
        <dd v-else class="col-6 text-italic text-muted">
          {{ __('No data') }}
        </dd>
        <dt class="col-6">{{ __('Last available data') }}</dt>
        <dd v-if="records.length > 0" class="col-6">
          {{ formatDate(records[records.length - 1][dateField]) }}
        </dd>
        <dd v-else class="col-6 text-italic text-muted">
          {{ __('No data') }}
        </dd>
        <dt class="col-6">{{ lastValueLabel }}</dt>
        <dd v-if="records.length > 0" class="col-6">
          <slot name="last-value" :record="records[records.length - 1]" />
        </dd>
        <dd v-else class="col-6 text-italic text-muted">
          {{ __('No data') }}
        </dd>
      </dl>
    </div>
  </div>
</template>

<script>
import { __, toFormattedDate } from '@/shared/lib/i18n';

// Shared skeleton behind CurrencyRateOverview.vue and InvestmentPriceOverview.vue (see T-14
// in .ai/docs/specifications/frontend-review/tasks.md): a collapsible "Overview" card
// showing record count, first/last available dates, and a last-known-value row. The leading
// identifying rows (e.g. From/To vs. Investment) and the last-value formatting are the real
// per-feature differences, so they're passed in as `headerRows` and the `last-value` slot;
// everything else is identical between the two originals and lives here once.
export default {
  name: 'RecordOverviewCard',
  props: {
    headerRows: {
      type: Array,
      default: () => [],
    },
    records: {
      type: Array,
      required: true,
    },
    dateField: {
      type: String,
      default: 'date',
    },
    lastValueLabel: {
      type: String,
      required: true,
    },
  },
  data() {
    return {
      locale: window.YAFFA.userSettings.locale,
    };
  },
  methods: {
    formatDate(date) {
      return toFormattedDate(date, this.locale, '');
    },
    __,
  },
};
</script>
