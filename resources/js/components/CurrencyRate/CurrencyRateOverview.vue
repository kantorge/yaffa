<template>
  <div class="card mb-3">
    <div class="card-header">
      <div
        class="card-title collapse-control"
        data-coreui-toggle="collapse"
        data-coreui-target="#cardOverview"
      >
        <i class="fa fa-angle-down"></i>
        {{ __('Overview') }}
      </div>
    </div>
    <div class="collapse card-body show" aria-expanded="true" id="cardOverview">
      <dl class="row mb-0">
        <dt class="col-6">{{ __('From') }}</dt>
        <dd class="col-6">{{ from.name }}</dd>
        <dt class="col-6">{{ __('To') }}</dt>
        <dd class="col-6">{{ to.name }}</dd>
        <dt class="col-6">{{ __('Number of records') }}</dt>
        <dd class="col-6">{{ currencyRates.length }}</dd>
        <dt class="col-6">{{ __('First available data') }}</dt>
        <dd class="col-6" v-if="currencyRates.length > 0">
          {{ formatDate(currencyRates[0].date) }}
        </dd>
        <dd class="col-6 text-italic text-muted" v-else>
          {{ __('No data') }}
        </dd>
        <dt class="col-6">{{ __('Last available data') }}</dt>
        <dd class="col-6" v-if="currencyRates.length > 0">
          {{ formatDate(currencyRates[currencyRates.length - 1].date) }}
        </dd>
        <dd class="col-6 text-italic text-muted" v-else>
          {{ __('No data') }}
        </dd>
        <dt class="col-6">{{ __('Last known rate') }}</dt>
        <dd class="col-6" v-if="currencyRates.length > 0">
          {{ toFormattedCurrency(1, locale, from) }}
          =
          {{
            toFormattedCurrency(
              currencyRates[currencyRates.length - 1].rate,
              locale,
              to,
            )
          }}
        </dd>
        <dd class="col-6 text-italic text-muted" v-else>
          {{ __('No data') }}
        </dd>
      </dl>
    </div>
  </div>
</template>

<script>
  import { __, toFormattedCurrency } from '../../helpers';

  export default {
    name: 'CurrencyRateOverview',
    props: {
      from: {
        type: Object,
        required: true,
      },
      to: {
        type: Object,
        required: true,
      },
      currencyRates: {
        type: Array,
        required: true,
      },
    },
    data() {
      return {
        locale: window.YAFFA.locale,
      };
    },
    methods: {
      formatDate(date) {
        date = new Date(date);
        return date.toLocaleDateString(this.locale);
      },
      toFormattedCurrency(value, locale, currency) {
        return toFormattedCurrency(value, locale, currency);
      },
      __,
    },
  };
</script>
