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
    <div id="cardOverview" class="collapse card-body show" aria-expanded="true">
      <dl class="row mb-0">
        <dt class="col-6">{{ __('From') }}</dt>
        <dd class="col-6">{{ from.name }}</dd>
        <dt class="col-6">{{ __('To') }}</dt>
        <dd class="col-6">{{ to.name }}</dd>
        <dt class="col-6">{{ __('Number of records') }}</dt>
        <dd class="col-6">{{ currencyRates.length }}</dd>
        <dt class="col-6">{{ __('First available data') }}</dt>
        <dd v-if="currencyRates.length > 0" class="col-6">
          {{ formatDate(currencyRates[0].date) }}
        </dd>
        <dd v-else class="col-6 text-italic text-muted">
          {{ __('No data') }}
        </dd>
        <dt class="col-6">{{ __('Last available data') }}</dt>
        <dd v-if="currencyRates.length > 0" class="col-6">
          {{ formatDate(currencyRates[currencyRates.length - 1].date) }}
        </dd>
        <dd v-else class="col-6 text-italic text-muted">
          {{ __('No data') }}
        </dd>
        <dt class="col-6">{{ __('Last known rate') }}</dt>
        <dd v-if="currencyRates.length > 0" class="col-6">
          {{ toFormattedCurrency(1, locale, from, 'detailed') }}
          =
          {{
            toFormattedCurrency(
              currencyRates[currencyRates.length - 1].rate,
              locale,
              to,
              'detailed',
            )
          }}
        </dd>
        <dd v-else class="col-6 text-italic text-muted">
          {{ __('No data') }}
        </dd>
      </dl>
    </div>
  </div>
</template>

<script>
  import { __, toFormattedCurrency, toFormattedDate } from '@/shared/lib/i18n';

  export default {
    props: {
      from: Object,
      to: Object,
      currencyRates: Array,
    },
    data() {
      return {
        locale: window.YAFFA.userSettings.locale,
      };
    },
    methods: {
      formatDate(date) {
        return toFormattedDate(date, this.locale, '');
      },
      toFormattedCurrency,
      __,
    },
  };
</script>
