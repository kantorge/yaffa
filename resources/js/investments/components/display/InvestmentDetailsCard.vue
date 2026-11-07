<template>
  <div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
      <div class="card-title">
        {{ __('Investment details') }}
      </div>
      <div>
        <a
          :href="getEditUrl()"
          class="btn btn-sm btn-outline-primary"
          :title="__('Edit investment')"
        >
          <i class="fa fa-edit"></i>
        </a>
      </div>
    </div>
    <div class="card-body">
      <dl class="row mb-0">
        <dt class="col-4">{{ __('Name') }}</dt>
        <dd class="col-8 text-break">{{ investment.name }}</dd>
        <dt class="col-4">{{ __('Symbol') }}</dt>
        <dd class="col-8">{{ investment.symbol }}</dd>
        <dt class="col-4">{{ __('ISIN number') }}</dt>
        <dd class="col-8 text-break" :class="{ 'not-set': !investment.isin }">
          {{ investment.isin || __('Not set') }}
        </dd>
        <dt class="col-4">{{ __('Active') }}</dt>
        <dd class="col-8">
          <i
            v-if="investment.active"
            class="fa fa-check-square text-success"
            :title="__('Yes')"
          ></i>
          <i v-else class="fa fa-square text-danger" :title="__('No')"></i>
        </dd>
        <dt class="col-4">{{ __('Group') }}</dt>
        <dd class="col-8">{{ investment.investment_group.name }}</dd>
        <dt class="col-4">{{ __('Currency') }}</dt>
        <dd class="col-8">{{ investment.currency.name }}</dd>
        <template v-if="investment.comment">
          <dt class="col-4">{{ __('Comment') }}</dt>
          <dd class="col-8 text-break">{{ investment.comment }}</dd>
        </template>
        <dt class="col-4">{{ __('Automatic update') }}</dt>
        <dd class="col-8">
          <i
            v-if="investment.auto_update"
            class="fa fa-check-square text-success"
            :title="__('Yes')"
          ></i>
          <i v-else class="fa fa-square text-danger" :title="__('No')"></i>
        </dd>
        <template v-if="investment.auto_update">
          <dt class="col-4">{{ __('Price provider') }}</dt>
          <dd
            class="col-8"
            :class="{ 'not-set': !investment.investment_price_provider_name }"
          >
            {{ investment.investment_price_provider_name || __('Not set') }}
          </dd>
        </template>
      </dl>
      <h6 class="mt-3 mb-2 pt-3 border-top">{{ __('Current assets') }}</h6>
      <dl class="row mb-0">
        <dt class="col-7 col-sm-8">{{ __('Owned quantity') }}</dt>
        <dd class="col-5 col-sm-4 text-end text-break">
          {{ formatQuantity(investment.current_quantity) }}
        </dd>
        <dt class="col-7 col-sm-8">{{ __('Latest price') }}</dt>
        <dd
          class="col-5 col-sm-4 text-end text-break"
          :class="{ 'not-set': investment.latest_price == null }"
        >
          <template v-if="investment.latest_price == null">
            {{ __('Not set') }}
          </template>
          <template v-else>
            {{
              toFormattedCurrency(
                investment.latest_price,
                locale,
                investment.currency,
                'detailed',
              )
            }}
          </template>
        </dd>
        <dt class="col-7 col-sm-8">{{ __('Latest owned value') }}</dt>
        <dd
          class="col-5 col-sm-4 text-end text-break"
          :class="{ 'not-set': investment.latest_price == null }"
        >
          <template v-if="investment.latest_price == null">
            {{ __('Not set') }}
          </template>
          <template v-else>
            {{
              toFormattedCurrency(
                investment.current_quantity * investment.latest_price,
                locale,
                investment.currency,
              )
            }}
          </template>
        </dd>
      </dl>
    </div>
  </div>
</template>

<script>
  import {
    __,
    toFormattedCurrency,
    toFormattedNumber,
  } from '@/shared/lib/i18n';

  export default {
    name: 'InvestmentDetailsCard',
    props: {
      investment: {
        type: Object,
        required: true,
      },
    },
    data() {
      return {
        locale: window.YAFFA.userSettings.locale,
      };
    },
    methods: {
      toFormattedCurrency,
      formatQuantity(value) {
        if (value === 0) {
          return '0';
        }
        return toFormattedNumber(value, this.locale, {
          minimumFractionDigits: 0,
          maximumFractionDigits: 4,
        });
      },
      __,
      getEditUrl() {
        return this.route('investments.edit', {
          investment: this.investment.id,
        });
      },
    },
  };
</script>
