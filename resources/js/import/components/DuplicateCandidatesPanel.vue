<template>
  <div v-if="candidates && candidates.length">
    <div class="fw-semibold mb-2 d-flex align-items-center gap-2">
      <i class="fa fa-copy text-danger"></i>
      <span>{{ __('Potential duplicates') }} ({{ candidates.length }})</span>
    </div>
    <div class="duplicate-list d-flex flex-column gap-2">
      <div
        v-for="(candidate, index) in candidates"
        :key="index"
        class="duplicate-card border rounded p-2"
        :class="confidenceCardClass(candidate.confidence_score)"
      >
        <!-- Header row: confidence + amount -->
        <div class="d-flex justify-content-between align-items-start mb-1">
          <span
            class="badge duplicate-confidence-badge"
            :class="confidenceBadgeClass(candidate.confidence_score)"
          >
            {{ Math.round(candidate.confidence_score * 100) }}%
            {{ __('match') }}
          </span>
          <span class="fw-bold fs-6 text-nowrap">
            {{ formatAmount(candidate.summary.amount) }}
          </span>
        </div>

        <!-- Amount mismatch explanation -->
        <div
          v-if="hasDraftAmount && amountMismatch(candidate)"
          class="text-muted small mb-1"
        >
          <i class="fa fa-info-circle me-1"></i>
          {{
            __('Draft is :amount; existing is :existing', {
              amount: formatAmount(draftAmount),
              existing: formatAmount(candidate.summary.amount),
            })
          }}
        </div>

        <!-- Date -->
        <div class="text-muted small mb-1">
          <i class="fa fa-calendar me-1"></i>
          {{ formatDate(candidate.summary.date) }}
        </div>

        <!-- Comment (if present) -->
        <div v-if="candidate.summary.comment" class="small text-break mb-1">
          {{ candidate.summary.comment }}
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

        <!-- View button -->
        <button
          type="button"
          class="btn btn-sm btn-outline-secondary w-100"
          :disabled="loadingTransactionId === candidate.transaction_id"
          @click="viewTransaction(candidate.transaction_id)"
        >
          <span
            v-if="loadingTransactionId === candidate.transaction_id"
            class="spinner-border spinner-border-sm me-1"
          ></span>
          <i v-else class="fa fa-eye me-1"></i>
          {{ __('View existing transaction') }}
        </button>
      </div>
    </div>
  </div>
</template>

<script>
  import axios from 'axios';
  import { __, toFormattedCurrency, toFormattedDate } from '@/shared/lib/i18n';

  export default {
    name: 'DuplicateCandidatesPanel',
    props: {
      candidates: {
        type: Array,
        required: true,
      },
      draftAmount: {
        type: [Number, String],
        default: null,
      },
      accountCurrency: {
        type: Object,
        default: null,
      },
    },
    data() {
      return {
        loadingTransactionId: null,
      };
    },
    computed: {
      hasDraftAmount() {
        return this.draftAmount !== null && this.draftAmount !== undefined;
      },
    },
    methods: {
      __,
      transactionUrl(transactionId) {
        if (!transactionId || !window.route) {
          return '#';
        }
        return window.route('transaction.open', {
          transaction: transactionId,
          action: 'show',
        });
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
      amountMismatch(candidate) {
        if (!this.hasDraftAmount) return false;
        const draft = Math.abs(Number(this.draftAmount));
        const existing = Math.abs(Number(candidate.summary?.amount));
        return Math.abs(draft - existing) > 0.005;
      },
      signalLabel(signal) {
        const labels = {
          amount: __('amount'),
          date: __('date'),
          payee: __('payee'),
          comment: __('comment'),
          account_from: __('account from'),
          account_to: __('account to'),
        };
        return labels[signal] ?? signal;
      },
      confidenceCardClass(score) {
        if (score >= 0.9) return 'border-danger bg-danger-subtle';
        if (score >= 0.7) return 'border-warning bg-warning-subtle';
        return 'border-secondary bg-light';
      },
      confidenceBadgeClass(score) {
        if (score >= 0.9) return 'bg-danger';
        if (score >= 0.7) return 'bg-warning text-dark';
        return 'bg-secondary';
      },
      async viewTransaction(transactionId) {
        if (!transactionId || this.loadingTransactionId) return;
        this.loadingTransactionId = transactionId;
        try {
          const response = await axios.get(
            `/api/v1/transactions/${transactionId}`,
          );
          const transaction = response.data.transaction;
          if (transaction?.date) transaction.date = new Date(transaction.date);
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
            this.transactionUrl(transactionId),
            '_blank',
            'noopener,noreferrer',
          );
        } finally {
          this.loadingTransactionId = null;
        }
      },
    },
  };
</script>

<style scoped>
  .duplicate-card {
    font-size: 0.875rem;
  }

  .duplicate-confidence-badge {
    font-size: 0.8rem;
  }

  :global([data-coreui-theme='dark'] .duplicate-card.bg-danger-subtle) {
    background-color: rgba(var(--cui-danger-rgb), 0.15) !important;
  }

  :global([data-coreui-theme='dark'] .duplicate-card.bg-warning-subtle) {
    background-color: rgba(var(--cui-warning-rgb), 0.12) !important;
  }

  :global([data-coreui-theme='dark'] .duplicate-card.bg-light) {
    background-color: var(--cui-secondary-bg) !important;
  }
</style>
