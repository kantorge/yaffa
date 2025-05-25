<template>
  <div class="card mb-3">
    <div class="card-header d-flex justify-content-between">
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
        <dd class="col-8">{{ investment.name }}</dd>
        <dt class="col-4">{{ __('Symbol') }}</dt>
        <dd class="col-8">{{ investment.symbol }}</dd>
        <dt class="col-4">{{ __('ISIN number') }}</dt>
        <dd
          class="col-8"
          :class="{'text-muted text-italic': !investment.isin}"
        >
          {{ investment.isin || __('Not set') }}
        </dd>
        <dt class="col-4">{{ __('Active') }}</dt>
        <dd class="col-8">
          <i
            v-if="investment.active"
            class="fa fa-check-square text-success"
            :title="__('Yes')"
          ></i>
          <i
            v-else
            class="fa fa-square text-danger"
            :title="__('No')"
          ></i>
        </dd>
        <dt class="col-4">{{ __('Group') }}</dt>
        <dd class="col-8">{{ investment.investment_group.name }}</dd>
        <dt class="col-4">{{ __('Currency') }}</dt>
        <dd class="col-8">{{ investment.currency.name }}</dd>
        <template v-if="investment.comment">
          <dt class="col-4">{{ __('Comment') }}</dt>
          <dd class="col-8">{{ investment.comment }}</dd>
        </template>
        <dt class="col-4">{{ __('Automatic update') }}</dt>
        <dd class="col-8">
          <i
            v-if="investment.auto_update"
            class="fa fa-check-square text-success"
            :title="__('Yes')"
          ></i>
          <i
            v-else
            class="fa fa-square text-danger"
            :title="__('No')"
          ></i>
        </dd>
        <template v-if="investment.auto_update">
          <dt class="col-4">{{ __('Price provider') }}</dt>
          <dd class="col-8">{{ investment.investment_price_provider_name }}</dd>
        </template>
      </dl>
    </div>
  </div>
</template>

<script>
export default {
  name: "InvestmentDetailsCard",
  props: {
    investment: {
      type: Object,
      required: true
    }
  },
  methods: {
    getEditUrl() {
        return window.route('investment.edit', {investment: this.investment.id});
    }
  }
};
</script>