<template>
  <div id="payeeOverviewCard" class="card mb-3">
    <div class="card-header">
      <div class="card-title">{{ __('Overview') }}</div>
    </div>
    <div class="card-body">
      <dl class="row mb-0">
        <dt class="col-5">{{ __('Active') }}</dt>
        <dd class="col-7">
          <i
            v-if="payee.active"
            class="fa fa-check-square text-success"
            :title="__('Yes')"
          ></i>
          <i v-else class="fa fa-square text-danger" :title="__('No')"></i>
        </dd>

        <dt class="col-5">{{ __('Default category') }}</dt>
        <dd class="col-7">
          <a
            v-if="payee.config?.category"
            :href="
              route('categories.show', { category: payee.config.category.id })
            "
          >
            {{ payee.config.category.full_name }}
          </a>
          <span v-else class="text-muted">{{ __('Not set') }}</span>
        </dd>

        <template v-if="preferredCategories.length > 0">
          <dt class="col-5">{{ __('Preferred categories') }}</dt>
          <dd class="col-7">
            <template
              v-for="(category, index) in preferredCategories"
              :key="category.id"
            >
              <span v-if="index > 0">, </span>
              <a :href="route('categories.show', { category: category.id })">{{
                category.full_name
              }}</a>
            </template>
          </dd>
        </template>
        <template v-if="excludedCategories.length > 0">
          <dt class="col-5">{{ __('Excluded categories') }}</dt>
          <dd class="col-7">
            <template
              v-for="(category, index) in excludedCategories"
              :key="category.id"
            >
              <span v-if="index > 0">, </span>
              <a :href="route('categories.show', { category: category.id })">{{
                category.full_name
              }}</a>
            </template>
          </dd>
        </template>
        <template v-if="aliases.length > 0">
          <dt class="col-5">{{ __('Import alias') }}</dt>
          <dd class="col-7 text-break">
            <ul class="mb-0 ps-3">
              <li v-for="alias in aliases" :key="alias">{{ alias }}</li>
            </ul>
          </dd>
        </template>

        <dt class="col-5">{{ __('Number of transactions') }}</dt>
        <dd class="col-7">{{ overview.count }}</dd>

        <dt class="col-5">{{ __('First transaction') }}</dt>
        <dd class="col-7">{{ formatDate(overview.first_date) }}</dd>

        <dt class="col-5">{{ __('Last transaction') }}</dt>
        <dd class="col-7">
          {{ formatDate(overview.last_date) }}
          <span v-if="daysSinceLast !== null" class="text-muted">
            ({{ __(':days days ago', { days: daysSinceLast }) }})
          </span>
        </dd>

        <dt class="col-5">{{ __('Total paid') }}</dt>
        <dd class="col-7">
          {{ formatMoney(overview.withdrawal_total) }}
          <i
            v-if="overview.rates_missing"
            class="fa fa-exclamation-triangle text-warning"
            :title="
              __(
                'Some amounts could not be converted to the base currency, as exchange rates are missing.',
              )
            "
          ></i>
        </dd>

        <dt class="col-5">{{ __('Total received') }}</dt>
        <dd class="col-7">
          {{ formatMoney(overview.deposit_total) }}
          <i
            v-if="overview.rates_missing"
            class="fa fa-exclamation-triangle text-warning"
            :title="
              __(
                'Some amounts could not be converted to the base currency, as exchange rates are missing.',
              )
            "
          ></i>
        </dd>
      </dl>
    </div>
  </div>
</template>

<script>
  import { __, toFormattedCurrency, toFormattedDate } from '@/shared/lib/i18n';

  export default {
    name: 'PayeeOverviewCard',
    props: {
      payee: { type: Object, required: true },
      overview: { type: Object, required: true },
      baseCurrency: { type: Object, default: null },
    },
    data() {
      return { locale: window.YAFFA.userSettings.locale };
    },
    computed: {
      aliases() {
        return (this.payee.alias || '')
          .split(/\r?\n/)
          .map((line) => line.trim())
          .filter(Boolean);
      },
      preferredCategories() {
        return this.payee.preferred_categories || [];
      },
      excludedCategories() {
        return this.payee.deferred_categories || [];
      },
      daysSinceLast() {
        if (!this.overview.last_date) {
          return null;
        }

        const last = new Date(this.overview.last_date + 'T00:00:00');
        const today = new Date();
        today.setHours(0, 0, 0, 0);

        return Math.max(0, Math.round((today - last) / 86400000));
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
