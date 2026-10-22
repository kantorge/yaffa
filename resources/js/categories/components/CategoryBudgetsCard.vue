<template>
  <div v-if="budgets.length > 0" id="categoryBudgetsCard" class="card mb-3">
    <div class="card-header">
      <div class="card-title">{{ __('Budgets') }}</div>
    </div>
    <ul class="list-group list-group-flush">
      <li v-for="budget in budgets" :key="budget.id" class="list-group-item">
        <div class="d-flex justify-content-between">
          <span>{{ formatAmount(budget) }}</span>
          <span class="text-muted">{{ recurrence(budget) }}</span>
        </div>
        <div class="small text-muted">
          <template v-if="budget.next_occurrence">
            {{ __('Next occurrence') }}:
            {{ formatDate(budget.next_occurrence) }}
          </template>
          <template v-else>{{ __('No upcoming occurrence') }}</template>
        </div>
      </li>
    </ul>
  </div>
</template>

<script>
  import { __, toFormattedCurrency, toFormattedDate } from '@/shared/lib/i18n';

  const FREQUENCY_LABELS = {
    DAILY: 'Daily',
    WEEKLY: 'Weekly',
    MONTHLY: 'Monthly',
    YEARLY: 'Yearly',
  };

  export default {
    name: 'CategoryBudgetsCard',
    props: {
      budgets: { type: Array, required: true },
      baseCurrency: { type: Object, default: null },
    },
    data() {
      return { locale: window.YAFFA.userSettings.locale };
    },
    methods: {
      formatDate(date) {
        return toFormattedDate(date, this.locale, '');
      },
      // An account-bound budget is in that account's currency
      formatAmount(budget) {
        return toFormattedCurrency(
          budget.amount,
          this.locale,
          budget.account?.config?.currency ?? this.baseCurrency,
        );
      },
      recurrence(budget) {
        const label = this.__(
          FREQUENCY_LABELS[budget.frequency] || budget.frequency,
        );

        return budget.interval > 1 ? `${label} ×${budget.interval}` : label;
      },
      __,
    },
  };
</script>
