<template>
  <div v-if="candidates && candidates.length" class="mt-2">
    <div class="fw-semibold text-warning mb-1 small">
      <i class="fa fa-warning me-1"></i>
      {{ __('Potential duplicates') }} ({{ candidates.length }})
    </div>
    <div class="list-group list-group-sm">
      <button
        v-for="(candidate, index) in candidates"
        :key="index"
        type="button"
        class="list-group-item list-group-item-action list-group-item-warning py-1 px-2 text-start"
        :disabled="loadingTransactionId === candidate.transaction_id"
        :title="__('Click to view transaction details')"
        @click="viewTransaction(candidate.transaction_id)"
      >
        <div
          class="d-flex justify-content-between align-items-center small gap-2"
        >
          <div class="flex-grow-1 min-w-0">
            <div
              class="d-flex justify-content-between align-items-baseline gap-1"
            >
              <span class="text-muted text-nowrap">
                {{ formatDate(candidate.summary.date) }}
              </span>
              <span class="fw-semibold text-nowrap">
                {{ formatAmount(candidate.summary.amount) }}
              </span>
            </div>
            <div v-if="candidate.summary.comment" class="text-muted text-break">
              {{ candidate.summary.comment }}
            </div>
            <div class="d-flex flex-wrap gap-1 mt-1 align-items-center">
              <span class="badge bg-warning text-dark">
                {{ Math.round(candidate.confidence_score * 100) }}%
              </span>
              <span
                v-for="signal in nonDateSignals(candidate)"
                :key="signal"
                class="badge bg-secondary"
              >
                {{ signalLabel(signal) }}: {{ signalValue(candidate, signal) }}
              </span>
              <span v-if="loadingTransactionId === candidate.transaction_id">
                <span
                  class="spinner-border spinner-border-sm text-secondary"
                ></span>
              </span>
              <i v-else class="fa fa-eye text-muted ms-auto"></i>
            </div>
          </div>
        </div>
      </button>
    </div>
  </div>
</template>

<script>
  import axios from 'axios';
  import { ref } from 'vue';
  import { __ } from '@/shared/lib/i18n';

  export default {
    name: 'DuplicateCandidatesPanel',
    props: {
      candidates: {
        type: Array,
        required: true,
      },
    },
    setup() {
      const loadingTransactionId = ref(null);

      const transactionUrl = (transactionId) => {
        if (!transactionId || !window.route) {
          return '#';
        }

        return window.route('transaction.open', {
          transaction: transactionId,
          action: 'show',
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

      const signalLabel = (signal) => {
        const labels = {
          amount: __('amount'),
          date: __('date'),
          payee: __('payee'),
          comment: __('comment'),
          account_from: __('account from'),
          account_to: __('account to'),
        };

        return labels[signal] ?? signal;
      };

      const nonDateSignals = (candidate) => {
        if (!candidate.matched_on?.length) {
          return [];
        }

        return candidate.matched_on.filter((s) => s !== 'date');
      };

      const signalValue = (candidate, signal) => {
        if (signal === 'amount') {
          return formatAmount(candidate.summary?.amount);
        }

        if (signal === 'date') {
          return formatDate(candidate.summary?.date);
        }

        if (signal === 'payee') {
          return candidate.summary?.payee || '—';
        }

        if (signal === 'comment') {
          return candidate.summary?.comment || '—';
        }

        // account_from and account_to: no name in summary, just indicate it matched
        return '✓';
      };

      const viewTransaction = async (transactionId) => {
        if (!transactionId || loadingTransactionId.value) {
          return;
        }

        loadingTransactionId.value = transactionId;

        try {
          const response = await axios.get(
            `/api/v1/transactions/${transactionId}`,
          );

          const transaction = response.data.transaction;

          if (transaction?.date) {
            transaction.date = new Date(transaction.date);
          }

          if (transaction?.transaction_schedule) {
            if (transaction.transaction_schedule.start_date) {
              transaction.transaction_schedule.start_date = new Date(
                transaction.transaction_schedule.start_date,
              );
            }

            if (transaction.transaction_schedule.end_date) {
              transaction.transaction_schedule.end_date = new Date(
                transaction.transaction_schedule.end_date,
              );
            }

            if (transaction.transaction_schedule.next_date) {
              transaction.transaction_schedule.next_date = new Date(
                transaction.transaction_schedule.next_date,
              );
            }
          }

          window.dispatchEvent(
            new CustomEvent('showTransactionQuickViewModal', {
              detail: {
                transaction,
                controls: {
                  show: true,
                  edit: false,
                  clone: false,
                  skip: false,
                  enter: false,
                  delete: false,
                },
              },
            }),
          );
        } catch {
          window.open(
            transactionUrl(transactionId),
            '_blank',
            'noopener,noreferrer',
          );
        } finally {
          loadingTransactionId.value = null;
        }
      };

      return {
        loadingTransactionId,
        formatDate,
        formatAmount,
        nonDateSignals,
        signalLabel,
        signalValue,
        viewTransaction,
        __,
      };
    },
  };
</script>
