<template>
  <div class="card" id="import-draft-table-card">
    <div class="card-header d-flex justify-content-between align-items-center">
      <div class="card-title mb-0">{{ __('Parsed drafts') }}</div>
      <span class="badge bg-secondary">{{ drafts.length }}</span>
    </div>

    <div class="card-body p-0">
      <div v-if="!drafts.length" class="p-3 text-muted">
        {{ __('No drafts parsed yet. Upload a file to begin review.') }}
      </div>

      <div v-else class="table-responsive">
        <table class="table table-striped table-hover mb-0">
          <thead>
            <tr>
              <th class="text-nowrap">{{ __('Draft') }}</th>
              <th class="text-nowrap">{{ __('Date') }}</th>
              <th class="text-nowrap">{{ __('Amount') }}</th>
              <th>{{ __('Payee') }}</th>
              <th class="text-nowrap">{{ __('Status') }}</th>
              <th class="text-nowrap">{{ __('Warnings') }}</th>
              <th class="text-nowrap">{{ __('Raw entry') }}</th>
            </tr>
          </thead>
          <tbody>
            <template v-for="draft in drafts" :key="draft.draft_index">
              <tr>
                <td>#{{ draft.draft_index + 1 }}</td>
                <td>{{ draft.date || __('Invalid') }}</td>
                <td>{{ formatAmount(draft.amount) }}</td>
                <td>{{ draft.payee || __('Not set') }}</td>
                <td>
                  <span :class="statusClass(draft.status)">
                    {{ statusLabel(draft.status) }}
                  </span>
                </td>
                <td>
                  <span
                    v-if="draft.warnings && draft.warnings.length"
                    class="badge bg-warning text-dark"
                  >
                    {{ draft.warnings.length }}
                  </span>
                  <span v-else class="text-muted">{{ __('None') }}</span>
                </td>
                <td>
                  <button
                    class="btn btn-sm btn-outline-secondary"
                    type="button"
                    @click="toggleRaw(draft.draft_index)"
                  >
                    {{
                      isRawVisible(draft.draft_index) ? __('Hide') : __('Show')
                    }}
                  </button>
                </td>
              </tr>
              <tr v-if="isRawVisible(draft.draft_index)">
                <td colspan="7" class="bg-light">
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
  import { ref } from 'vue';
  import { __ } from '@/shared/lib/i18n';

  export default {
    name: 'ImportDraftTable',
    props: {
      drafts: {
        type: Array,
        required: true,
      },
    },
    setup() {
      const expandedRows = ref(new Set());

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

      const formatAmount = (amount) => {
        if (amount === null || amount === undefined) {
          return __('Invalid');
        }

        const value = Number(amount);
        if (Number.isNaN(value)) {
          return __('Invalid');
        }

        return value.toLocaleString(
          window.YAFFA?.userSettings?.locale || undefined,
          {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
          },
        );
      };

      return {
        expandedRows,
        toggleRaw,
        isRawVisible,
        statusLabel,
        statusClass,
        formatAmount,
      };
    },
  };
</script>
