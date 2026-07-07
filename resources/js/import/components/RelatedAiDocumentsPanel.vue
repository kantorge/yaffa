<template>
  <div v-if="candidates && candidates.length">
    <div class="fw-semibold mb-2 d-flex align-items-center gap-2">
      <i class="fa fa-file-lines text-info"></i>
      <span>{{ __('Related AI documents') }} ({{ candidates.length }})</span>
    </div>
    <div class="d-flex flex-column gap-2">
      <a
        v-for="candidate in candidates"
        :key="candidate.ai_document_id"
        :href="documentUrl(candidate.ai_document_id)"
        target="_blank"
        rel="noopener noreferrer"
        class="ai-doc-card border border-info rounded p-2 text-decoration-none text-body"
      >
        <!-- Header: merchant + amount -->
        <div class="d-flex justify-content-between align-items-start mb-1">
          <span class="fw-semibold text-break me-2">
            {{ candidate.summary.merchant || __('Unknown merchant') }}
          </span>
          <span class="fw-bold text-nowrap">
            {{ formatAmount(candidate.summary.total_amount) }}
          </span>
        </div>

        <!-- Date -->
        <div
          v-if="candidate.summary.document_date"
          class="text-muted small mb-1"
        >
          <i class="fa fa-calendar me-1"></i>
          {{ formatDate(candidate.summary.document_date) }}
        </div>

        <!-- Matched signals -->
        <div
          v-if="candidate.matched_on && candidate.matched_on.length"
          class="d-flex flex-wrap gap-1 mb-2"
        >
          <span
            v-for="signal in candidate.matched_on"
            :key="signal"
            class="badge bg-secondary"
          >
            {{ signalLabel(signal) }}
          </span>
        </div>

        <!-- Footer: confidence + open link -->
        <div class="d-flex justify-content-between align-items-center">
          <span class="badge bg-info text-dark">
            {{ Math.round(candidate.confidence_score * 100) }}%
            {{ __('match') }}
          </span>
          <span class="small text-info">
            <i class="fa fa-external-link me-1"></i>{{ __('Open AI document') }}
          </span>
        </div>
      </a>
    </div>
  </div>
</template>

<script>
  import { __, toFormattedCurrency, toFormattedDate } from '@/shared/lib/i18n';

  export default {
    name: 'RelatedAiDocumentsPanel',
    props: {
      candidates: {
        type: Array,
        required: true,
      },
      accountCurrency: {
        type: Object,
        default: null,
      },
    },
    methods: {
      __,
      documentUrl(aiDocumentId) {
        if (!aiDocumentId || !window.route) return '#';
        return window.route('ai-documents.show', { aiDocument: aiDocumentId });
      },
      formatDate(dateString) {
        return toFormattedDate(
          dateString,
          window.YAFFA?.userSettings?.locale || undefined,
          __('Unknown'),
          true,
          { year: 'numeric', month: 'short', day: 'numeric' },
        );
      },
      formatAmount(amount) {
        return toFormattedCurrency(
          amount,
          window.YAFFA?.userSettings?.locale || undefined,
          this.accountCurrency,
        );
      },
      signalLabel(signal) {
        const labels = {
          amount: __('amount'),
          date: __('date'),
          payee: __('payee'),
          merchant: __('merchant'),
        };
        return labels[signal] ?? signal;
      },
    },
  };
</script>

<style scoped>
  .ai-doc-card {
    font-size: 0.875rem;
    transition: box-shadow 0.15s ease;
    background-color: rgba(var(--cui-info-rgb, 13, 202, 240), 0.08);
  }
  .ai-doc-card:hover {
    box-shadow: 0 0 0 2px rgba(var(--cui-info-rgb, 13, 202, 240), 0.4);
  }
</style>
