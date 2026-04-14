<template>
  <div class="card" id="import-draft-table-card">
    <div
      class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2"
    >
      <div class="card-title mb-0">{{ __('Parsed drafts') }}</div>
      <div class="d-flex align-items-center gap-3">
        <div class="d-flex gap-3">
          <div class="form-check form-check-inline mb-0">
            <input
              id="show-ignored-filter"
              v-model="showIgnoredRows"
              type="checkbox"
              class="form-check-input"
            />
            <label class="form-check-label small" for="show-ignored-filter">
              {{ __('Show ignored') }}
              <span v-if="ignoredCount" class="badge bg-secondary ms-1">{{ ignoredCount }}</span>
            </label>
          </div>
          <div class="form-check form-check-inline mb-0">
            <input
              id="show-finalized-filter"
              v-model="showFinalizedRows"
              type="checkbox"
              class="form-check-input"
            />
            <label class="form-check-label small" for="show-finalized-filter">
              {{ __('Show finalized') }}
              <span v-if="finalizedCount" class="badge bg-success ms-1">{{ finalizedCount }}</span>
            </label>
          </div>
        </div>
        <span class="badge bg-secondary">{{ drafts.length }}</span>
      </div>
    </div>

    <div class="card-body p-0">
      <div v-if="!drafts.length" class="p-3 text-muted">
        {{ __('No drafts parsed yet. Upload a file to begin review.') }}
      </div>

      <div v-else-if="!visibleDrafts.length" class="p-3 text-muted">
        {{ __('All drafts are hidden by the current filters.') }}
      </div>

      <div v-else class="table-responsive">
        <table class="table table-striped table-hover mb-0">
          <thead>
            <tr>
              <th style="width: 32px"></th>
              <th class="text-nowrap">{{ __('Draft') }}</th>
              <th class="text-nowrap">{{ __('Date') }}</th>
              <th class="text-nowrap">{{ __('Amount') }}</th>
              <th>{{ __('Payee') }}</th>
              <th class="text-nowrap">{{ __('Status') }}</th>
              <th class="text-center text-nowrap">{{ __('Insights') }}</th>
              <th class="text-nowrap">{{ __('Actions') }}</th>
            </tr>
          </thead>
          <tbody>
            <template v-for="draft in visibleDrafts" :key="draft.draft_index">
              <tr
                :class="rowClass(draft.status)"
                style="cursor: pointer"
                @click="toggleRaw(draft.draft_index)"
              >
                <td class="text-center">
                  <i
                    :class="
                      isRawVisible(draft.draft_index)
                        ? 'fa fa-chevron-up text-primary'
                        : 'fa fa-chevron-down text-muted'
                    "
                  ></i>
                </td>
                <td>#{{ draft.draft_index + 1 }}</td>
                <td class="text-nowrap">{{ formatDate(draft.date) }}</td>
                <td class="text-nowrap">{{ formatAmount(draft.amount) }}</td>
                <td>
                  <span v-if="draft.payee">{{ draft.payee }}</span>
                  <span v-else class="text-muted fst-italic">{{
                    __('Not set')
                  }}</span>
                </td>
                <td>
                  <span :class="statusClass(draft.status)">
                    {{ statusLabel(draft.status) }}
                  </span>
                </td>
                <td class="text-center text-nowrap">
                  <span
                    v-if="draft.warnings && draft.warnings.length"
                    class="badge bg-warning text-dark me-1"
                    :title="__('Warnings')"
                  >
                    <i class="fa fa-warning"></i> {{ draft.warnings.length }}
                  </span>
                  <span
                    v-if="draft.duplicate_candidates && draft.duplicate_candidates.length"
                    class="badge bg-danger me-1"
                    :title="__('Potential duplicates')"
                  >
                    <i class="fa fa-copy"></i> {{ draft.duplicate_candidates.length }}
                  </span>
                  <span
                    v-if="draft.related_ai_documents && draft.related_ai_documents.length"
                    class="badge bg-info text-dark"
                    :title="__('Related AI documents')"
                  >
                    <i class="fa fa-file-lines"></i> {{ draft.related_ai_documents.length }}
                  </span>
                  <span
                    v-if="!draft.warnings?.length && !draft.duplicate_candidates?.length && !draft.related_ai_documents?.length"
                    class="text-muted"
                  >—</span>
                </td>
                <td class="text-nowrap" @click.stop>
                  <button
                    v-if="draft.status === 'pending_review'"
                    class="btn btn-sm btn-success me-1"
                    type="button"
                    :title="__('Finalize this draft as a transaction')"
                    @click="$emit('finalize-draft', draft.draft_index)"
                  >
                    <i class="fa fa-check"></i>
                  </button>
                  <button
                    v-if="draft.status === 'pending_review'"
                    class="btn btn-sm btn-outline-secondary"
                    type="button"
                    :title="__('Ignore this draft')"
                    @click="$emit('ignore-draft', draft.draft_index)"
                  >
                    <i class="fa fa-times"></i>
                  </button>
                </td>
              </tr>
              <tr v-if="isRawVisible(draft.draft_index)">
                <td colspan="8" class="bg-light p-3">
                  <div class="row g-3">
                    <div class="col-12 col-lg-6">
                      <div
                        v-if="draft.warnings && draft.warnings.length"
                        class="mb-2"
                      >
                        <div class="fw-semibold">{{ __('Warnings') }}</div>
                        <ul class="mb-0 ps-3">
                          <li
                            v-for="(warning, warningIndex) in draft.warnings"
                            :key="`${draft.draft_index}-warning-${warningIndex}`"
                          >
                            {{ warning }}
                          </li>
                        </ul>
                      </div>
                      <div class="fw-semibold">{{ __('Raw entry') }}</div>
                      <pre class="mb-0 small text-wrap">{{
                        draft.raw_entry || __('Not available')
                      }}</pre>
                    </div>
                    <div
                      v-if="
                        draft.duplicate_candidates &&
                        draft.duplicate_candidates.length
                      "
                      class="col-12 col-md-6 col-lg-3"
                    >
                      <DuplicateCandidatesPanel
                        :candidates="draft.duplicate_candidates"
                      />
                    </div>
                    <div
                      v-if="
                        draft.related_ai_documents &&
                        draft.related_ai_documents.length
                      "
                      class="col-12 col-md-6 col-lg-3"
                    >
                      <RelatedAiDocumentsPanel
                        :candidates="draft.related_ai_documents"
                      />
                    </div>
                  </div>
                </td>
              </tr>
            </template>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>

<script>
  import { computed, ref } from 'vue';
  import { __ } from '@/shared/lib/i18n';
  import DuplicateCandidatesPanel from './DuplicateCandidatesPanel.vue';
  import RelatedAiDocumentsPanel from './RelatedAiDocumentsPanel.vue';

  export default {
    name: 'ImportDraftTable',
    components: {
      DuplicateCandidatesPanel,
      RelatedAiDocumentsPanel,
    },
    props: {
      drafts: {
        type: Array,
        required: true,
      },
      accountCurrency: {
        type: String,
        default: null,
      },
    },
    emits: ['ignore-draft', 'finalize-draft'],
    setup(props) {
      const expandedRows = ref(new Set());

      const showIgnoredRows = ref(false);
      const showFinalizedRows = ref(false);

      const visibleDrafts = computed(() =>
        props.drafts.filter((draft) => {
          if (draft.status === 'ignored' && !showIgnoredRows.value) {
            return false;
          }
          if (draft.status === 'finalized' && !showFinalizedRows.value) {
            return false;
          }
          return true;
        }),
      );

      const ignoredCount = computed(
        () => props.drafts.filter((d) => d.status === 'ignored').length,
      );

      const finalizedCount = computed(
        () => props.drafts.filter((d) => d.status === 'finalized').length,
      );

      const toggleRaw = (draftIndex) => {
        if (expandedRows.value.has(draftIndex)) {
          expandedRows.value.delete(draftIndex);
          return;
        }

        expandedRows.value.add(draftIndex);
      };

      const isRawVisible = (draftIndex) => {
        return expandedRows.value.has(draftIndex);
      };

      const statusLabel = (status) => {
        const labels = {
          pending_review: __('Pending review'),
          ignored: __('Ignored'),
          finalized: __('Finalized'),
          failed_validation: __('Failed validation'),
        };

        return labels[status] || status;
      };

      const statusClass = (status) => {
        const classes = {
          pending_review: 'badge bg-info text-dark',
          ignored: 'badge bg-secondary',
          finalized: 'badge bg-success',
          failed_validation: 'badge bg-danger',
        };

        return classes[status] || 'badge bg-light text-dark';
      };

      const formatDate = (dateString) => {
        if (!dateString) {
          return __('Invalid');
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
          return __('Invalid');
        }

        const value = Number(amount);
        if (Number.isNaN(value)) {
          return __('Invalid');
        }

        const locale = window.YAFFA?.userSettings?.locale || undefined;

        if (props.accountCurrency) {
          try {
            return value.toLocaleString(locale, {
              style: 'currency',
              currency: props.accountCurrency,
              minimumFractionDigits: 2,
              maximumFractionDigits: 2,
            });
          } catch {
            // fall through to plain format
          }
        }

        return value.toLocaleString(locale, {
          minimumFractionDigits: 2,
          maximumFractionDigits: 2,
        });
      };

      const rowClass = (status) => {
        if (status === 'ignored') {
          return 'opacity-50';
        }
        if (status === 'finalized') {
          return 'table-success';
        }
        return '';
      };

      return {
        expandedRows,
        showIgnoredRows,
        showFinalizedRows,
        visibleDrafts,
        ignoredCount,
        finalizedCount,
        toggleRaw,
        isRawVisible,
        statusLabel,
        statusClass,
        formatDate,
        formatAmount,
        rowClass,
      };
    },
  };
</script>
