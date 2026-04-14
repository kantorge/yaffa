<template>
  <div v-if="candidates && candidates.length" class="mt-2">
    <div class="fw-semibold text-info mb-1 small">
      <i class="fa fa-file-lines me-1"></i>
      {{ __('Related AI documents') }} ({{ candidates.length }})
    </div>
    <div class="list-group list-group-sm">
      <a
        v-for="(candidate, index) in candidates"
        :key="index"
        :href="documentUrl(candidate.ai_document_id)"
        target="_blank"
        rel="noopener noreferrer"
        class="list-group-item list-group-item-action list-group-item-info py-1 px-2"
      >
        <div class="d-flex justify-content-between align-items-start small">
          <div class="flex-grow-1 me-2">
            <div class="fw-semibold text-break">
              {{ candidate.summary.merchant || __('Unknown merchant') }}
            </div>
            <div
              v-if="candidate.matched_on && candidate.matched_on.length"
              class="mt-1 d-flex flex-wrap gap-1"
            >
              <span
                v-for="signal in candidate.matched_on"
                :key="signal"
                class="badge bg-secondary"
              >
                {{ signal }}: {{ signalValue(candidate, signal) }}
              </span>
            </div>
          </div>
          <div class="text-end flex-shrink-0">
            <div>{{ formatAmount(candidate.summary.total_amount) }}</div>
            <div class="d-flex align-items-center gap-1 mt-1">
              <span class="badge bg-info text-dark">
                {{ Math.round(candidate.confidence_score * 100) }}%
                {{ __('match') }}
              </span>
              <i
                class="fa fa-external-link text-info fs-6"
                :title="__('Open AI document')"
              ></i>
            </div>
          </div>
        </div>
      </a>
    </div>
    <div class="mt-1 text-end">
      <i
        class="fa fa-circle-info text-muted"
        :title="
          __(
            'Open an AI document link above to use the AI finalization flow instead of creating a manual transaction.',
          )
        "
      ></i>
    </div>
  </div>
</template>

<script>
  import { __ } from '@/shared/lib/i18n';

  export default {
    name: 'RelatedAiDocumentsPanel',
    props: {
      candidates: {
        type: Array,
        required: true,
      },
    },
    setup() {
      const documentUrl = (aiDocumentId) => {
        if (!aiDocumentId || !window.route) {
          return '#';
        }

        return window.route('ai-documents.show', {
          aiDocument: aiDocumentId,
        });
      };

      const formatDate = (dateString) => {
        if (!dateString) {
          return __('Unknown');
        }

        try {
          const parts = dateString.split('-');
          if (parts.length === 3) {
            const date = new Date(
              Number(parts[0]),
              Number(parts[1]) - 1,
              Number(parts[2]),
            );
            return date.toLocaleDateString(
              window.YAFFA?.userSettings?.locale || undefined,
              { year: 'numeric', month: 'short', day: 'numeric' },
            );
          }
        } catch {
          // fall through
        }

        return dateString;
      };

      const formatAmount = (amount) => {
        if (amount === null || amount === undefined) {
          return __('Unknown');
        }

        const value = Number(amount);
        if (Number.isNaN(value)) {
          return __('Unknown');
        }

        return value.toLocaleString(
          window.YAFFA?.userSettings?.locale || undefined,
          { minimumFractionDigits: 2, maximumFractionDigits: 2 },
        );
      };

      const signalValue = (candidate, signal) => {
        const map = {
          amount: formatAmount(candidate.summary?.total_amount),
          date: formatDate(candidate.summary?.document_date),
          payee: candidate.summary?.merchant || '—',
          merchant: candidate.summary?.merchant || '—',
        };

        return map[signal] ?? signal;
      };

      return { documentUrl, formatDate, formatAmount, signalValue, __ };
    },
  };
</script>
