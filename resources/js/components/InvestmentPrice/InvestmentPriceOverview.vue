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
        <dt class="col-6">{{ __('Investment') }}</dt>
        <dd class="col-6">{{ investment.name }}</dd>
        <dt class="col-6">{{ __('Number of records') }}</dt>
        <dd class="col-6">{{ investmentPrices.length }}</dd>
        <dt class="col-6">{{ __('First available data') }}</dt>
        <dd class="col-6" v-if="investmentPrices.length > 0">
          {{ formatDate(investmentPrices[0].date) }}
        </dd>
        <dd class="col-6 text-italic text-muted" v-else>
          {{ __('No data') }}
        </dd>
        <dt class="col-6">{{ __('Last available data') }}</dt>
        <dd class="col-6" v-if="investmentPrices.length > 0">
          {{ formatDate(investmentPrices[investmentPrices.length - 1].date) }}
        </dd>
        <dd class="col-6 text-italic text-muted" v-else>
          {{ __('No data') }}
        </dd>
        <dt class="col-6">{{ __('Last known price') }}</dt>
        <dd class="col-6" v-if="investmentPrices.length > 0">
          {{
            toFormattedCurrency(
              investmentPrices[investmentPrices.length - 1].price,
              locale,
              {
                iso_code: investment.currency.iso_code,
              },
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
    name: 'InvestmentPriceOverview',
    props: {
      investment: {
        type: Object,
        required: true,
      },
      // Note, that we assume that investmentPrices are sorted by date ascending
      investmentPrices: {
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
      toFormattedCurrency,
      __,
    },
  };
</script>
