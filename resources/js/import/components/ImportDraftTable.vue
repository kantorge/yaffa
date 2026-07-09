<template>
  <div class="card" id="import-draft-table-card">
    <div
      class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2"
    >
      <div class="card-title mb-0">{{ __('Parsed drafts') }}</div>
      <div class="d-flex align-items-center gap-3">
        <div id="import-draft-filters" class="d-flex gap-3">
          <div class="form-check form-check-inline mb-0">
            <input
              id="show-drafts-filter"
              v-model="showDraftRows"
              type="checkbox"
              class="form-check-input"
            />
            <label class="form-check-label small" for="show-drafts-filter">
              {{ __('Show drafts') }}
              <span v-if="draftCount" class="badge bg-info text-dark ms-1">{{
                draftCount
              }}</span>
            </label>
          </div>
          <div class="form-check form-check-inline mb-0">
            <input
              id="show-ignored-filter"
              v-model="showIgnoredRows"
              type="checkbox"
              class="form-check-input"
            />
            <label class="form-check-label small" for="show-ignored-filter">
              {{ __('Show ignored') }}
              <span v-if="ignoredCount" class="badge bg-secondary ms-1">{{
                ignoredCount
              }}</span>
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
              <span v-if="finalizedCount" class="badge bg-success ms-1">{{
                finalizedCount
              }}</span>
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
              <th class="col-expand"></th>
              <th
                class="col-type text-center"
                :title="__('Transaction type')"
              ></th>
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
                :class="['row-expandable', rowClass(draft.status)]"
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
                <td class="text-center">
                  <i
                    :class="transactionTypeIconClass(draft)"
                    :title="transactionTypeLabel(draft)"
                  ></i>
                </td>
                <td>#{{ draft.draft_index + 1 }}</td>
                <td class="text-nowrap">{{ formatDate(draft.date) }}</td>
                <td class="text-nowrap">{{ formatAmount(draft.amount) }}</td>
                <td>
                  <template v-if="draft.matched_payee">
                    {{ draft.matched_payee.name }}
                    <i
                      v-if="draft.matched_payee.similarity >= 0.9"
                      class="fa fa-check-circle text-success ms-1"
                      :title="
                        __('Matched database entity (:pct%)', {
                          pct: Math.round(draft.matched_payee.similarity * 100),
                        })
                      "
                    ></i>
                    <i
                      v-else-if="draft.matched_payee.similarity >= 0.8"
                      class="fa fa-check-double text-info ms-1"
                      :title="
                        __('High-confidence similarity match (:pct%)', {
                          pct: Math.round(draft.matched_payee.similarity * 100),
                        })
                      "
                    ></i>
                    <i
                      v-else
                      class="fa fa-circle-half-stroke text-warning ms-1"
                      :title="
                        __('Similarity match (~:pct%)', {
                          pct: Math.round(draft.matched_payee.similarity * 100),
                        })
                      "
                    ></i>
                    <div v-if="draft.payee" class="text-muted small mt-1">
                      {{ draft.payee_cleaned || draft.payee }}
                    </div>
                  </template>
                  <template v-else-if="draft.payee">
                    {{ draft.payee_cleaned || draft.payee }}
                    <span
                      class="badge bg-secondary ms-1"
                      :title="__('No database match found')"
                      >{{ __('Text') }}</span
                    >
                  </template>
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
                    v-if="visibleDuplicates(draft).length"
                    class="badge me-1"
                    :class="duplicateBadgeClass(draft)"
                    :title="__('Potential duplicates')"
                  >
                    <i class="fa fa-copy"></i>
                    {{ visibleDuplicates(draft).length }}
                  </span>
                  <span
                    v-if="visibleSchedules(draft).length"
                    class="badge me-1"
                    :class="scheduleBadgeClass(draft)"
                    :title="__('Matching scheduled transactions')"
                  >
                    <i class="fa fa-calendar-check"></i>
                    {{ visibleSchedules(draft).length }}
                  </span>
                  <span
                    v-if="visibleAiDocuments(draft).length"
                    class="badge bg-info text-dark"
                    :title="__('Related AI documents')"
                  >
                    <i class="fa fa-file-lines"></i>
                    {{ visibleAiDocuments(draft).length }}
                  </span>
                  <span
                    v-if="
                      !draft.warnings?.length &&
                      !visibleDuplicates(draft).length &&
                      !visibleSchedules(draft).length &&
                      !visibleAiDocuments(draft).length
                    "
                    class="text-muted"
                    >—</span
                  >
                </td>
                <td class="text-nowrap" @click.stop>
                  <button
                    v-if="draft.status === 'pending_review'"
                    class="btn btn-sm btn-success me-1"
                    type="button"
                    :title="__('Finalize this draft as a transaction')"
                    @click="$emit('finalize-draft', draft.draft_index)"
                  >
                    <i class="fa fa-pencil"></i>
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
                <td colspan="9" class="bg-body-secondary p-3">
                  <div class="row g-3">
                    <!-- Left column: structured raw entry + warnings -->
                    <div class="col-12 col-lg-5">
                      <div class="fw-semibold mb-1">{{ __('Raw entry') }}</div>
                      <!-- CSV: key-value table -->
                      <table
                        v-if="
                          draft.source_type === 'csv' &&
                          csvRawFields(draft.raw_entry).length
                        "
                        class="table table-sm table-bordered mb-0 small"
                      >
                        <tbody>
                          <tr
                            v-for="[key, val] in csvRawFields(draft.raw_entry)"
                            :key="key"
                          >
                            <th
                              class="bg-body-tertiary fw-semibold text-nowrap"
                              style="width: 40%"
                            >
                              {{ key }}
                            </th>
                            <td>
                              {{ val !== null && val !== '' ? val : '—' }}
                            </td>
                          </tr>
                        </tbody>
                      </table>
                      <!-- QIF: marker table -->
                      <table
                        v-else-if="
                          draft.source_type === 'qif' &&
                          qifRawFields(draft.raw_entry).length
                        "
                        class="table table-sm table-bordered mb-0 small"
                      >
                        <tbody>
                          <tr
                            v-for="(field, fi) in qifRawFields(draft.raw_entry)"
                            :key="fi"
                          >
                            <th
                              class="bg-body-tertiary text-center fw-bold text-nowrap"
                              style="width: 2rem"
                            >
                              {{ field.marker }}
                            </th>
                            <td
                              class="text-muted text-nowrap"
                              style="width: 30%"
                            >
                              {{ field.label }}
                            </td>
                            <td>{{ field.value || '—' }}</td>
                          </tr>
                        </tbody>
                      </table>
                      <!-- Fallback -->
                      <pre v-else class="mb-0 small text-wrap">{{
                        draft.raw_entry || __('Not available')
                      }}</pre>

                      <!-- Warnings -->
                      <div
                        v-if="draft.warnings && draft.warnings.length"
                        class="mt-3"
                      >
                        <div class="fw-semibold mb-1">{{ __('Warnings') }}</div>
                        <ul class="mb-0 ps-3 small">
                          <li
                            v-for="(warning, warningIndex) in draft.warnings"
                            :key="`${draft.draft_index}-warning-${warningIndex}`"
                          >
                            {{ warning }}
                          </li>
                        </ul>
                      </div>
                    </div>

                    <!-- Right column: stacked insight panels, aligned consistently for CSV and QIF -->
                    <div class="col-12 col-lg-7 d-flex flex-column gap-3">
                      <div v-if="visibleDuplicates(draft).length">
                        <DuplicateCandidatesPanel
                          :candidates="visibleDuplicates(draft)"
                          :draft-amount="draft.amount"
                          :account-currency="accountCurrency"
                          @dismiss="dismissCandidate('duplicate', draft.draft_index, $event)"
                        />
                      </div>
                      <div v-if="visibleSchedules(draft).length">
                        <ScheduleCandidatesPanel
                          :candidates="visibleSchedules(draft)"
                          :draft-amount="draft.amount"
                          :account-currency="accountCurrency"
                          @enter-schedule="$emit('enter-schedule-draft', draft.draft_index)"
                          @dismiss="dismissCandidate('schedule', draft.draft_index, $event)"
                        />
                      </div>
                      <div v-if="visibleAiDocuments(draft).length">
                        <RelatedAiDocumentsPanel
                          :candidates="visibleAiDocuments(draft)"
                          :account-currency="accountCurrency"
                          @dismiss="dismissCandidate('aiDocument', draft.draft_index, $event)"
                        />
                      </div>
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
  import { __, toFormattedCurrency, toFormattedDate } from '@/shared/lib/i18n';
  import DuplicateCandidatesPanel from './DuplicateCandidatesPanel.vue';
  import ScheduleCandidatesPanel from './ScheduleCandidatesPanel.vue';
  import RelatedAiDocumentsPanel from './RelatedAiDocumentsPanel.vue';

  export default {
    name: 'ImportDraftTable',
    components: {
      DuplicateCandidatesPanel,
      ScheduleCandidatesPanel,
      RelatedAiDocumentsPanel,
    },
    props: {
      drafts: {
        type: Array,
        required: true,
      },
      accountCurrency: {
        type: Object,
        default: null,
      },
    },
    emits: ['ignore-draft', 'finalize-draft', 'enter-schedule-draft'],
    data() {
      return {
        expandedRows: new Set(),
        showDraftRows: true,
        showIgnoredRows: false,
        showFinalizedRows: false,
        // Session-only dismissal of insight suggestions (duplicates/schedules/AI documents).
        // Nothing is persisted; this simply hides a card for the current review session.
        dismissedKeys: new Set(),
      };
    },
    watch: {
      drafts() {
        this.expandedRows = new Set();
        this.dismissedKeys = new Set();
      },
    },
    computed: {
      visibleDrafts() {
        return this.drafts.filter((draft) => {
          if (draft.status === 'pending_review' && !this.showDraftRows) {
            return false;
          }
          if (draft.status === 'ignored' && !this.showIgnoredRows) {
            return false;
          }
          if (draft.status === 'finalized' && !this.showFinalizedRows) {
            return false;
          }
          return true;
        });
      },
      draftCount() {
        return this.drafts.filter((d) => d.status === 'pending_review').length;
      },
      ignoredCount() {
        return this.drafts.filter((d) => d.status === 'ignored').length;
      },
      finalizedCount() {
        return this.drafts.filter((d) => d.status === 'finalized').length;
      },
    },
    methods: {
      transactionTypeIconClass(draft) {
        const type = draft.transaction_type;
        if (type === 'withdrawal')
          return ['fa', 'fa-circle-minus', 'text-danger'];
        if (type === 'deposit') return ['fa', 'fa-circle-plus', 'text-success'];
        if (type === 'transfer')
          return ['fa', 'fa-exchange-alt', 'text-primary'];
        // For QIF: infer from amount sign when type is generic/other
        if (draft.amount !== null && draft.amount !== undefined) {
          return Number(draft.amount) >= 0
            ? ['fa', 'fa-circle-plus', 'text-success']
            : ['fa', 'fa-circle-minus', 'text-danger'];
        }
        return ['fa', 'fa-circle', 'text-muted'];
      },
      transactionTypeLabel(draft) {
        const type = draft.transaction_type;
        if (type === 'withdrawal') return __('Withdrawal');
        if (type === 'deposit') return __('Deposit');
        if (type === 'transfer') return __('Transfer');
        if (draft.amount !== null && draft.amount !== undefined) {
          return Number(draft.amount) >= 0 ? __('Deposit') : __('Withdrawal');
        }
        return __('Unknown type');
      },
      toggleRaw(draftIndex) {
        if (this.expandedRows.has(draftIndex)) {
          this.expandedRows.delete(draftIndex);
        } else {
          this.expandedRows.add(draftIndex);
        }
        // Trigger reactivity for the Set mutation
        this.expandedRows = new Set(this.expandedRows);
      },
      isRawVisible(draftIndex) {
        return this.expandedRows.has(draftIndex);
      },
      candidateKey(type, draftIndex, candidateId) {
        return `${type}:${draftIndex}:${candidateId}`;
      },
      dismissCandidate(type, draftIndex, candidateId) {
        this.dismissedKeys.add(this.candidateKey(type, draftIndex, candidateId));
        // Trigger reactivity for the Set mutation
        this.dismissedKeys = new Set(this.dismissedKeys);
      },
      visibleDuplicates(draft) {
        return (draft.duplicate_candidates || []).filter(
          (candidate) =>
            !this.dismissedKeys.has(
              this.candidateKey('duplicate', draft.draft_index, candidate.transaction_id),
            ),
        );
      },
      visibleSchedules(draft) {
        return (draft.schedule_candidates || []).filter(
          (candidate) =>
            !this.dismissedKeys.has(
              this.candidateKey('schedule', draft.draft_index, candidate.transaction_id),
            ),
        );
      },
      visibleAiDocuments(draft) {
        return (draft.related_ai_documents || []).filter(
          (candidate) =>
            !this.dismissedKeys.has(
              this.candidateKey('aiDocument', draft.draft_index, candidate.ai_document_id),
            ),
        );
      },
      strongestConfidence(candidates) {
        if (!candidates.length) return null;
        return Math.max(...candidates.map((c) => c.confidence_score));
      },
      duplicateBadgeClass(draft) {
        const score = this.strongestConfidence(this.visibleDuplicates(draft));
        if (score === null) return 'bg-danger';
        if (score >= 0.9) return 'bg-danger';
        if (score >= 0.7) return 'bg-warning text-dark';
        return 'bg-secondary';
      },
      scheduleBadgeClass(draft) {
        const score = this.strongestConfidence(this.visibleSchedules(draft));
        if (score === null) return 'bg-success';
        if (score >= 0.9) return 'bg-success';
        if (score >= 0.7) return 'bg-warning text-dark';
        return 'bg-secondary';
      },
      statusLabel(status) {
        const labels = {
          pending_review: __('Pending review'),
          ignored: __('Ignored'),
          finalized: __('Finalized'),
          failed_validation: __('Failed validation'),
        };
        return labels[status] || status;
      },
      statusClass(status) {
        const classes = {
          pending_review: 'badge text-bg-info',
          ignored: 'badge text-bg-secondary',
          finalized: 'badge text-bg-success',
          failed_validation: 'badge text-bg-danger',
        };
        return classes[status] || 'badge text-bg-secondary';
      },
      formatDate(dateString) {
        if (!dateString) {
          return __('Invalid');
        }
        return toFormattedDate(
          dateString,
          window.YAFFA?.userSettings?.locale || undefined,
          dateString,
          true,
          { year: 'numeric', month: 'short', day: 'numeric' },
        );
      },
      formatAmount(amount) {
        if (amount === null || amount === undefined) {
          return __('Invalid');
        }
        const value = Number(amount);
        if (Number.isNaN(value)) {
          return __('Invalid');
        }
        return toFormattedCurrency(
          value,
          window.YAFFA?.userSettings?.locale || undefined,
          this.accountCurrency,
        );
      },
      rowClass(status) {
        if (status === 'ignored') {
          return 'opacity-50';
        }
        if (status === 'finalized') {
          return 'table-success';
        }
        return '';
      },
      csvRawFields(rawEntry) {
        try {
          const obj = JSON.parse(rawEntry || '{}');
          return Object.entries(obj).filter(([key]) => key !== '');
        } catch {
          return [];
        }
      },
      qifRawFields(rawEntry) {
        const markerLabels = {
          D: __('Date'),
          T: __('Amount'),
          P: __('Payee'),
          M: __('Memo'),
          L: __('Category'),
          N: __('Reference'),
          S: __('Split Category'),
          E: __('Split Memo'),
          $: __('Split Amount'),
          '^': __('Entry End'),
        };
        return (rawEntry || '')
          .split('\n')
          .filter((line) => line.trim() !== '')
          .map((line) => ({
            marker: line[0] || '',
            label: markerLabels[line[0]] || '',
            value: line.slice(1),
          }));
      },
      __,
    },
  };
</script>

<style scoped>
  .col-expand {
    width: 32px;
  }
  .col-type {
    width: 28px;
  }
  .row-expandable {
    cursor: pointer;
  }
</style>
