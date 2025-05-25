<template>
  <div class="card mb-3">
    <div class="card-header d-flex justify-content-between">
      <div class="card-title">
        {{ __('Results') }}
      </div>
      <div>
        <button class="btn btn-sm btn-primary" @click="resetDates">
          {{ __('Reset dates') }}
        </button>
      </div>
    </div>
    <div class="card-body">
      <div class="row mb-3">
        <label for="date_from" class="col-6 col-sm-2 col-form-label">
          {{ __('Date from') }}
        </label>
        <div class="col-6 col-sm-4">
          <input type="date" class="form-control" v-model="dateFromString" />
        </div>
        <label for="date_to" class="col-6 col-sm-2 col-form-label">
          {{ __('Date to') }}
        </label>
        <div class="col-6 col-sm-4">
          <input type="date" class="form-control" v-model="dateToString" />
        </div>
      </div>
      <div class="row mb-0">
        <div class="col-sm-6">
          <dl class="row mb-0">
            <dt class="col-6">{{ __('Buying cost') }}</dt>
            <dd class="col-6">
              {{
                toFormattedCurrency(summary.Buying, locale, investment.currency)
              }}
            </dd>
            <dt class="col-6">{{ __('Added quantity') }}</dt>
            <dd class="col-6">{{ formatQuantity(summary.Added) }}</dd>
            <dt class="col-6">{{ __('Removed quantity') }}</dt>
            <dd class="col-6">{{ formatQuantity(summary.Removed) }}</dd>
            <dt class="col-6">{{ __('Selling revenue') }}</dt>
            <dd class="col-6">
              {{
                toFormattedCurrency(
                  summary.Selling,
                  locale,
                  investment.currency
                )
              }}
            </dd>
            <dt class="col-6">{{ __('Dividend') }}</dt>
            <dd class="col-6">
              {{
                toFormattedCurrency(
                  summary.Dividend,
                  locale,
                  investment.currency
                )
              }}
            </dd>
            <dt class="col-6">{{ __('Commissions') }}</dt>
            <dd class="col-6">
              {{
                toFormattedCurrency(
                  summary.Commission,
                  locale,
                  investment.currency
                )
              }}
            </dd>
            <dt class="col-6">{{ __('Taxes') }}</dt>
            <dd class="col-6">
              {{
                toFormattedCurrency(summary.Taxes, locale, investment.currency)
              }}
            </dd>
            <dt class="col-6">{{ __('Quantity') }}</dt>
            <dd class="col-6">{{ formatQuantity(summary.Quantity) }}</dd>
            <dt class="col-6">{{ __('Value') }}</dt>
            <dd class="col-6">
              {{
                toFormattedCurrency(summary.Value, locale, investment.currency)
              }}
            </dd>
          </dl>
        </div>
        <div class="col-sm-6">
          <dl class="row mb-0">
            <dt class="col-6">{{ __('Result') }}</dt>
            <dd class="col-6">
              {{
                toFormattedCurrency(summary.Result, locale, investment.currency)
              }}
            </dd>
            <dt class="col-6">{{ __('ROI') }}</dt>
            <dd class="col-6">{{ roiString }}</dd>
            <dt class="col-6">{{ __('Annualized ROI') }}</dt>
            <dd class="col-6">{{ aroiString }}</dd>
          </dl>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
  import { toFormattedCurrency } from '../../helpers';

  export default {
    name: 'ResultsCard',
    props: {
      investment: { type: Object, required: true },
      transactions: { type: Array, required: true },
      prices: { type: Array, required: true },
      dateFrom: { type: Date, default: null },
      dateTo: { type: Date, default: null },
    },
    emits: ['update:date-from', 'update:date-to'],
    data() {
      const allDates = this.transactions.map((t) => new Date(t.date));
      const minDate = allDates.length
        ? new Date(Math.min(...allDates))
        : new Date();
      const maxDate = allDates.length
        ? new Date(Math.max(...allDates))
        : new Date();
      return {
        locale: window.YAFFA ? window.YAFFA.locale : navigator.language,
        internalDateFrom: this.dateFrom || minDate,
        internalDateTo: this.dateTo || maxDate,
      };
    },
    watch: {
      dateFrom(val) {
        if (val) this.internalDateFrom = val;
      },
      dateTo(val) {
        if (val) this.internalDateTo = val;
      },
    },
    computed: {
      dateFromString: {
        get() {
          return this.internalDateFrom
            ? this.internalDateFrom.toISOString().slice(0, 10)
            : '';
        },
        set(val) {
          if (!val) return;
          const d = new Date(val);
          this.internalDateFrom = d;
          this.$emit('update:date-from', d);
        },
      },
      dateToString: {
        get() {
          return this.internalDateTo
            ? this.internalDateTo.toISOString().slice(0, 10)
            : '';
        },
        set(val) {
          if (!val) return;
          const d = new Date(val);
          this.internalDateTo = d;
          this.$emit('update:date-to', d);
        },
      },
      filteredTransactions() {
        return this.transactions.filter((trx) => {
          const d = new Date(trx.date);
          return d >= this.internalDateFrom && d <= this.internalDateTo;
        });
      },
      summary() {
        const filtered = this.filteredTransactions;
        const getSum = (arr, fn) => arr.reduce((sum, trx) => sum + fn(trx), 0);
        const getQty = (arr, type) =>
          getSum(
            arr.filter((trx) => trx.transaction_type.name === type),
            (trx) => trx.config.quantity || 0
          );
        const getVal = (arr, type) =>
          getSum(
            arr.filter((trx) => trx.transaction_type.name === type),
            (trx) => (trx.config.price || 0) * (trx.config.quantity || 0)
          );
        const getField = (arr, field) =>
          getSum(arr, (trx) => trx.config[field] || 0);
        const getQtyMult = (arr) =>
          getSum(
            arr,
            (trx) =>
              (trx.transaction_type.quantity_multiplier || 0) *
              (trx.config.quantity || 0)
          );
        let lastPrice = 1;
        if (this.prices.length > 0) {
          lastPrice = this.prices[this.prices.length - 1].price;
        } else {
          const priceTrx = filtered
            .filter((trx) => !isNaN(trx.price))
            .sort((a, b) => new Date(b.date) - new Date(a.date));
          if (priceTrx.length > 0) lastPrice = priceTrx[0].price;
        }
        const quantity = getQtyMult(filtered);
        const value = quantity * lastPrice;
        const buying = getVal(filtered, 'Buy');
        const selling = getVal(filtered, 'Sell');
        const added = getQty(filtered, 'Add');
        const removed = getQty(filtered, 'Remove');
        const dividend = getField(filtered, 'dividend');
        const commission = getField(filtered, 'commission');
        const taxes = getField(filtered, 'tax');
        const result = selling + dividend + value - buying - commission - taxes;
        return {
          Buying: buying,
          Selling: selling,
          Added: added,
          Removed: removed,
          Dividend: dividend,
          Commission: commission,
          Taxes: taxes,
          Quantity: quantity,
          Value: value,
          Result: result,
        };
      },
      roi() {
        return this.summary.Buying === 0
          ? 0
          : this.summary.Result / this.summary.Buying;
      },
      roiString() {
        return (this.roi * 100).toFixed(2) + '%';
      },
      aroi() {
        const years = this.calculateYears(
          this.internalDateTo,
          this.internalDateFrom
        );
        return years > 0 ? Math.pow(1 + this.roi, 1 / years) - 1 : 0;
      },
      aroiString() {
        return (this.aroi * 100).toFixed(2) + '%';
      },
    },
    methods: {
      toFormattedCurrency,
      formatQuantity(value) {
        if (value === 0) return '0';
        return value.toLocaleString(this.locale, {
          minimumFractionDigits: 0,
          maximumFractionDigits: 4,
        });
      },
      resetDates() {
        const allDates = this.transactions.map((t) => new Date(t.date));
        this.internalDateFrom = allDates.length
          ? new Date(Math.min(...allDates))
          : new Date();
        this.internalDateTo = allDates.length
          ? new Date(Math.max(...allDates))
          : new Date();
      },
      calculateYears(to, from) {
        const diffMs = to - from;
        const diffDate = new Date(diffMs);
        return Math.abs(diffDate.getUTCFullYear() - 1970);
      },
    },
  };
</script>
