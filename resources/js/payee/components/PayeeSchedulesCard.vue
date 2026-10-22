<template>
  <div id="payeeSchedulesCard" class="card mb-3">
    <div class="card-header">
      <div class="card-title">{{ __('Upcoming scheduled transactions') }}</div>
    </div>
    <div v-if="busy" class="card-body text-muted">
      <i class="fa fa-spinner fa-spin me-1"></i>{{ __('Loading...') }}
    </div>
    <div v-else-if="failed" class="card-body text-danger">
      {{ __('Error while loading scheduled transactions') }}
    </div>
    <div v-else-if="schedules.length === 0" class="card-body text-muted">
      {{ __('No upcoming scheduled transactions') }}
    </div>
    <ul v-else class="list-group list-group-flush">
      <li
        v-for="schedule in schedules"
        :key="schedule.id"
        class="list-group-item d-flex justify-content-between align-items-center"
      >
        <a
          :href="
            route('transaction.open', {
              transaction: schedule.id,
              action: 'show',
            })
          "
        >
          {{ formatDate(schedule.transaction_schedule.next_date) }}
        </a>
        <span>{{ formatAmount(schedule) }}</span>
      </li>
    </ul>
  </div>
</template>

<script>
  import { __, toFormattedCurrency, toFormattedDate } from '@/shared/lib/i18n';

  export default {
    name: 'PayeeSchedulesCard',
    props: {
      payeeId: { type: Number, required: true },
    },
    data() {
      return {
        busy: true,
        failed: false,
        rows: [],
        locale: window.YAFFA.userSettings.locale,
      };
    },
    computed: {
      // Schedules without a next occurrence are finished
      schedules() {
        return this.rows
          .filter((row) => row.transaction_schedule?.next_date)
          .sort((a, b) =>
            a.transaction_schedule.next_date.localeCompare(
              b.transaction_schedule.next_date,
            ),
          );
      },
    },
    mounted() {
      window.axios
        .get('/api/v1/transactions/scheduled-items', {
          params: {
            type: 'schedule',
            accountSelection: 'selected',
            accountEntity: this.payeeId,
          },
        })
        .then((response) => {
          this.rows = response.data.transactions;
        })
        .catch(() => {
          this.failed = true;
        })
        .finally(() => {
          this.busy = false;
        });
    },
    methods: {
      formatDate(date) {
        return toFormattedDate(date, this.locale, '');
      },
      // The payee's side of the transaction, in the transaction's own currency
      formatAmount(schedule) {
        const amount =
          schedule.transaction_type === 'withdrawal'
            ? schedule.config.amount_to
            : schedule.config.amount_from;

        return toFormattedCurrency(amount, this.locale, schedule.currency);
      },
      __,
    },
  };
</script>
