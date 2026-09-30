<template>
  <div id="categoryOverviewCard" class="card mb-3">
    <div class="card-header">
      <div class="card-title">{{ __('Overview') }}</div>
    </div>
    <div class="card-body">
      <dl class="row mb-0">
        <dt class="col-6">{{ __('Name') }}</dt>
        <dd class="col-6 text-break">{{ category.full_name }}</dd>

        <dt class="col-6">{{ __('Active') }}</dt>
        <dd class="col-6">
          <i
            v-if="category.active"
            class="fa fa-check-square text-success"
            :title="__('Yes')"
          ></i>
          <i v-else class="fa fa-square text-danger" :title="__('No')"></i>
        </dd>

        <template v-if="category.description">
          <dt class="col-6">{{ __('Description') }}</dt>
          <dd class="col-6 text-break">{{ category.description }}</dd>
        </template>

        <dt class="col-6">{{ __('Parent category') }}</dt>
        <dd class="col-6">
          <a
            v-if="category.parent"
            :href="route('categories.show', { category: category.parent.id })"
          >
            {{ category.parent.name }}
          </a>
          <span v-else class="text-muted">{{ __('Not set') }}</span>
        </dd>

        <template v-if="category.children.length > 0">
          <dt class="col-6">{{ __('Subcategories') }}</dt>
          <dd class="col-6">
            <template
              v-for="(child, index) in category.children"
              :key="child.id"
            >
              <span v-if="index > 0">, </span>
              <a :href="route('categories.show', { category: child.id })">{{
                child.name
              }}</a>
            </template>
          </dd>
        </template>

        <dt class="col-6">{{ __('Default aggregation') }}</dt>
        <dd class="col-6">{{ __(aggregationLabel) }}</dd>

        <dt class="col-6">{{ __('Number of transactions') }}</dt>
        <dd class="col-6">{{ overview.count }}</dd>

        <dt class="col-6">{{ __('First transaction') }}</dt>
        <dd class="col-6">{{ formatDate(overview.first_date) }}</dd>

        <dt class="col-6">{{ __('Last transaction') }}</dt>
        <dd class="col-6">{{ formatDate(overview.last_date) }}</dd>

        <dt class="col-6">{{ __('Total paid') }}</dt>
        <dd class="col-6">{{ formatMoney(overview.withdrawal_total) }}</dd>

        <dt class="col-6">{{ __('Total received') }}</dt>
        <dd class="col-6">{{ formatMoney(overview.deposit_total) }}</dd>
      </dl>
    </div>
  </div>
</template>

<script>
  import { __, toFormattedCurrency, toFormattedDate } from '@/shared/lib/i18n';

  const AGGREGATION_LABELS = {
    month: 'Month',
    quarter: 'Quarter',
    year: 'Year',
  };

  export default {
    name: 'CategoryOverviewCard',
    props: {
      category: { type: Object, required: true },
      overview: { type: Object, required: true },
      baseCurrency: { type: Object, default: null },
    },
    data() {
      return { locale: window.YAFFA.userSettings.locale };
    },
    computed: {
      aggregationLabel() {
        return AGGREGATION_LABELS[this.category.default_aggregation] || 'Month';
      },
    },
    methods: {
      formatDate(date) {
        return date
          ? toFormattedDate(date, this.locale, '')
          : this.__('No data');
      },
      formatMoney(value) {
        return toFormattedCurrency(value, this.locale, this.baseCurrency);
      },
      __,
    },
  };
</script>
