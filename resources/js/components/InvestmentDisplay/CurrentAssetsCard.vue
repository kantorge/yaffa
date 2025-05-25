<template>
  <div class="card mb-3">
    <div class="card-header">
      <div class="card-title">
        {{ __('Current assets') }}
      </div>
    </div>
    <div class="card-body">
      <dl class="row mb-0">
        <dt class="col-8">{{ __('Owned quantity') }}</dt>
        <dd class="col-4">{{ formatQuantity(investment.current_quantity) }}</dd>
        <dt class="col-8">{{ __('Latest price') }}</dt>
        <dd class="col-4">
          {{
            toFormattedCurrency(
              investment.latest_price,
              locale,
              investment.currency
            )
          }}
        </dd>
        <dt class="col-8">{{ __('Latest owned value') }}</dt>
        <dd class="col-4">
          {{
            toFormattedCurrency(
              investment.current_quantity * investment.latest_price,
              locale,
              investment.currency
            )
          }}
        </dd>
      </dl>
    </div>
  </div>
</template>

<script>
  import { toFormattedCurrency } from '../../helpers';

  export default {
    name: 'CurrentAssetsCard',
    props: {
      investment: {
        type: Object,
        required: true,
      },
    },
    data() {
      return {
        locale: window.YAFFA.locale,
      };
    },
    methods: {
      toFormattedCurrency,
      formatQuantity(value) {
        if (value === 0) {
          return '0';
        }
        return value.toLocaleString(this.locale, {
          minimumFractionDigits: 0,
          maximumFractionDigits: 4,
        });
      },
    },
  };
</script>
