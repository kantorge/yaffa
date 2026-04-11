<template>
  <div class="card mb-4" id="widgetScheduleCalendar">
    <div class="card-header d-flex justify-content-between">
      <div class="card-title">
        {{ __('widget.scheduleCalendar.cardTitle') }}
      </div>
      <div>
        <button
          type="button"
          class="btn-close"
          aria-label="Close"
          @click="hide"
          :disabled="busy"
        ></button>
      </div>
    </div>
    <div class="card-body">
      <p aria-hidden="true" v-if="busy" class="placeholder-glow">
        <span class="placeholder col-12"></span>
      </p>
      <Calendar
        class="custom-calendar"
        :masks="masks"
        :attributes="transactions"
        :first-day-of-week="2"
        :min-date="minDate"
        :max-date="maxDate"
        disable-page-swipe
        expanded
        trim-weeks
        @transition-end="refreshTooltip"
        v-if="!busy"
        :locale="language"
      >
        <template v-slot:day-content="{ day, attributes }">
          <div>
            <span class="day-label text-sm">{{ day.day }}</span>
            <div class="vc-day-custom-content">
              <a
                v-for="item in attributes"
                :key="item.key"
                style="margin: 0 1px"
                :href="getTransactionLink(item.customData?.id || 0)"
                v-html="getTransactionTypeIcon(item.customData)"
              ></a>
            </div>
          </div>
        </template>
      </Calendar>
    </div>
  </div>
</template>

<script>
  import { transactionTypeIcon } from '@/shared/lib/datatable';
  import { initializeBootstrapTooltips } from '@/shared/lib/helpers';
  import { __, toFormattedCurrency } from '@/shared/lib/i18n';
  import { Calendar } from 'v-calendar';

  export default {
    components: {
      Calendar,
    },

    props: {
      locale: {
        type: String,
        default: window.YAFFA.userSettings.locale,
      },
      language: {
        type: String,
        default: window.YAFFA.userSettings.language,
      },
    },

    methods: {
      getTransactionTypeIcon: function (transaction) {
        if (!transaction) {
          return '';
        }

        return transactionTypeIcon(
          transaction.transaction_type,
          this.getTransactionLabel(transaction),
        );
      },
      getTransactionLink: function (id) {
        return this.route('transaction.open', {
          transaction: id,
          action: 'enter',
        });
      },
      getTransactionLabel: function (transaction) {
        if (!transaction) {
          return '';
        }

        if (transaction.config_type === 'standard') {
          // Capitalize first letter of transaction type
          const type =
            transaction.transaction_type.charAt(0).toUpperCase() +
            transaction.transaction_type.slice(1);
          // Return constructed label
          return this.__('widget.scheduleCalendar.transactionLabel', {
            type: __(type), // Type itself is also translated before being inserted into the label
            amount: toFormattedCurrency(
              transaction.config.amount_to,
              this.locale,
              transaction.transaction_currency,
            ),
            fromAccount: transaction.config.account_from.name,
            toAccount: transaction.config.account_to.name,
          });
        }
      },
      refreshTooltip: function () {
        initializeBootstrapTooltips();
      },
      __,
      toFormattedCurrency,
      hide() {
        $('#widgetScheduleCalendar').hide();
      },
    },

    data() {
      return {
        busy: false,
        transactions: [],
        masks: {
          weekdays: 'WWW',
        },
        minDate: null,
        maxDate: null,
      };
    },

    created() {
      this.busy = true;
      let vue = this;

      axios
        .get('/api/v1/transactions/scheduled-items?type=schedule')
        .then(function (response) {
          vue.transactions = response.data.transactions
            // Keep only the transactions with a next date set.
            // Note: the date values are not converted to JavaScript Date objects in general.
            // Note: at this point, all items should have a schedule and next date, but a double-check is performed
            .filter(
              (transaction) =>
                transaction.transaction_schedule &&
                transaction.transaction_schedule.next_date,
            )
            // Map the data to the format required by the calendar component
            .map(function (transaction, index) {
              return {
                key: index + 1,
                customData: transaction,
                dates: new Date(transaction.transaction_schedule.next_date),
              };
            });

          // Set min and max dates or fall back to current month
          if (vue.transactions.length > 1) {
            const minDate = vue.transactions
              .map((transaction) => transaction.dates)
              .reduce(function (a, b) {
                return a < b ? a : b;
              });

            // Set the minDate to the first day of the same month
            vue.minDate = new Date(
              minDate.getFullYear(),
              minDate.getMonth(),
              1,
            );

            const maxDate = vue.transactions
              .map((transaction) => transaction.dates)
              .reduce(function (a, b) {
                return a > b ? a : b;
              });

            // Set the maxDate to the last day of the same month
            vue.maxDate = new Date(
              maxDate.getFullYear(),
              maxDate.getMonth() + 1,
              0,
            );
          } else if (vue.transactions.length === 1) {
            const date = new Date(vue.transactions[0].dates);
            vue.minDate = new Date(date.getFullYear(), date.getMonth(), 1);
            vue.maxDate = new Date(date.getFullYear(), date.getMonth() + 1, 0);
          } else {
            const date = new Date();
            vue.minDate = new Date(date.getFullYear(), date.getMonth(), 1);
            vue.maxDate = new Date(date.getFullYear(), date.getMonth() + 1, 0);
          }
        })
        .finally(function () {
          vue.busy = false;
          setTimeout(() => vue.refreshTooltip(), 1000);
        });
    },
  };
</script>

<style>
  .custom-calendar.vc-container {
    border-radius: 0;
    max-width: 100%;
  }

  .custom-calendar .vc-header {
    margin-bottom: 10px;
  }

  .custom-calendar .vc-weeks {
    padding: 0;
  }

  .custom-calendar .vc-weekday {
    background-color: #f8fafc;
    border-bottom: 1px solid #eaeaea;
    border-top: 1px solid #eaeaea;
    padding: 5px 0;
  }

  .custom-calendar .vc-day {
    border: 1px solid #b8c2cc;
    padding: 0 5px 3px 5px;
    text-align: left;
    height: 65px;
    min-width: 45px;
    background-color: white;
  }

  .custom-calendar .vc-day-custom-content {
    line-height: normal;
  }
</style>
