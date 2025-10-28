<template>
  <div id="transactionShowInvestment" v-if="transaction.id">
    <div class="row">
      <div class="col-md-4">
        <div class="card mb-3">
          <div class="card-header">
            <div class="card-title">
              {{ __('Properties') }}
            </div>
          </div>
          <div class="card-body">
            <dl class="row">
              <dt class="col-6">
                {{ __('Type') }}
              </dt>
              <dd class="col-6" data-test="label-transaction-type">
                {{ __(transaction.transaction_type.name) }}
              </dd>

              <dt class="col-6">
                {{ __('Date') }}
              </dt>
              <dd class="col-6">
                <span v-if="transaction.date">{{
                  formattedDate(transaction.date)
                }}</span>
                <span v-else class="text-muted text-italic">{{
                  __('Not set')
                }}</span>
              </dd>

              <dt class="col-6">
                {{ __('Account') }}
              </dt>
              <dd class="col-6" data-test="label-account-name">
                <a
                  :href="
                    route('account-entity.show', {
                      account_entity: transaction.config.account.id,
                    })
                  "
                  :title="__('Go to account')"
                >
                  {{ transaction.config.account.name }}
                </a>
              </dd>

              <dt class="col-6">
                {{ __('Investment') }}
              </dt>
              <dd class="col-6" data-test="label-investment-name">
                <a
                  :href="
                    route('investment.show', {
                      investment: transaction.config.investment.id,
                    })
                  "
                  :title="__('Go to investment')"
                >
                  {{ transaction.config.investment.name }}
                </a>
              </dd>

              <dt class="col-6">
                {{ __('Comment') }}
              </dt>
              <dd
                class="col-6"
                :class="transaction.comment ? '' : 'text-muted text-italic'"
              >
                {{ transaction.comment || 'Not set' }}
              </dd>

              <dt class="col-6">
                {{ __('Scheduled') }}
              </dt>
              <dd class="col-6">
                <span v-if="transaction.schedule"
                  ><i class="fa fa-check text-success" :title="__('Yes')"></i
                ></span>
                <span v-else
                  ><i class="fa fa-ban text-danger" :title="__('No')"></i
                ></span>
              </dd>

              <dt class="col-6">
                {{ __('Reconciled') }}
              </dt>
              <dd class="col-6">
                <span v-if="transaction.reconciled"
                  ><i class="fa fa-check text-success" :title="__('Yes')"></i
                ></span>
                <span v-else
                  ><i class="fa fa-ban text-danger" :title="__('No')"></i
                ></span>
              </dd>
            </dl>
          </div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="card mb-3">
          <div class="card-header">
            <div class="card-title">
              {{ __('Amounts') }}
            </div>
          </div>
          <div class="card-body">
            <dl class="row">
              <dt class="col-6">
                {{ __('Quantity') }}
              </dt>
              <dd class="col-6" data-test="label-quantity">
                {{ formattedQuantity }}
              </dd>

              <dt class="col-6">
                {{ __('Price') }}
              </dt>
              <dd class="col-6" data-test="label-price">
                {{
                  toFormattedCurrency(
                    transaction.config.price,
                    locale,
                    transaction.config.account.config.currency
                  ) || __('Not set')
                }}
              </dd>

              <dt class="col-6">
                {{ __('Commission') }}
              </dt>
              <dd class="col-6" v-if="transaction.config.commission">
                {{
                  toFormattedCurrency(
                    transaction.config.commission,
                    locale,
                    transaction.config.account.config.currency
                  )
                }}
              </dd>
              <dd class="col-6" v-else>
                <span class="text-muted text-italic">{{ __('Not set') }}</span>
              </dd>

              <dt class="col-6">
                {{ __('Tax') }}
              </dt>
              <dd class="col-6" v-if="transaction.config.tax">
                {{
                  toFormattedCurrency(
                    transaction.config.tax,
                    locale,
                    transaction.config.account.config.currency
                  )
                }}
              </dd>
              <dd class="col-6" v-else>
                <span class="text-muted text-italic">{{ __('Not set') }}</span>
              </dd>

              <dt class="col-6">
                {{ __('Dividend') }}
              </dt>
              <dd
                class="col-6"
                v-if="transaction.config.dividend"
                data-test="label-dividend"
              >
                {{
                  toFormattedCurrency(
                    transaction.config.dividend,
                    locale,
                    transaction.config.account.config.currency
                  )
                }}
              </dd>
              <dd class="col-6" v-else data-test="label-dividend">
                <span class="text-muted text-italic">{{ __('Not set') }}</span>
              </dd>
            </dl>
          </div>
        </div>
      </div>
      <div class="col-md-4">
        <transaction-schedule
          :isVisible="transaction.schedule"
          :isSchedule="transaction.schedule"
          :isBudget="false"
          :schedule="transaction.transaction_schedule || {}"
        ></transaction-schedule>
      </div>
    </div>
  </div>
</template>

<script>
  import TransactionSchedule from './Schedule.vue';
  import { __, toFormattedCurrency } from '../../helpers';

  export default {
    components: {
      'transaction-schedule': TransactionSchedule,
    },

    props: {
      transaction: {
        type: Object,
        default: {},
      },
      locale: {
        type: String,
        default: window.YAFFA.locale,
      },
    },

    computed: {
      total() {
        return (
          (this.transaction.config.quantity || 0) *
            (this.transaction.config.price || 0) +
          (this.transaction.config.dividend || 0) -
          (this.transaction.config.commission || 0) -
          (this.transaction.config.tax || 0)
        );
      },
      formattedQuantity() {
        // Check if quantity is not null
        if (!this.transaction.config.quantity) {
          return __('Not set');
        }

        return this.transaction.config.quantity.toLocaleString(this.locale, {
          minimumFractionDigits: 0,
          maximumFractionDigits: 4,
        });
      },
    },
    methods: {
      __,
      toFormattedCurrency,
      route,
      formattedDate(date) {
        if (typeof date === 'undefined') {
          return;
        }

        const newDate = new Date(date);

        return newDate.toLocaleDateString(this.locale);
      },
    },
  };
</script>
