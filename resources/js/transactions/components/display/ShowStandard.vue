<template>
  <div v-if="transaction.id" id="transactionShowStandard">
    <div class="row">
      <div class="col-md-4">
        <div class="card mb-3">
          <div class="card-header">
            <div class="card-title">
              {{ __('Properties') }}
            </div>
          </div>
          <div class="card-body">
            <dl class="row mb-0">
              <dt class="col-6">
                {{ __('Type') }}
              </dt>
              <dd class="col-6">
                {{ __(capitalize(transaction.transaction_type)) }}
              </dd>

              <dt class="col-6">
                {{ __('Date') }}
              </dt>
              <dd class="col-6">
                <span v-if="transaction.date">{{
                  formattedDate(transaction.date)
                }}</span>
                <span v-else class="not-set">{{ __('Not set') }}</span>
              </dd>

              <dt class="col-6">
                {{ accountFromFieldLabel }}
              </dt>
              <dd
                class="col-6"
                :class="{ 'not-set': !transaction.config.account_from?.name }"
                dusk="label-account-from-name"
              >
                <a
                  v-if="
                    entityHasLink(
                      transaction.config.account_from,
                      transaction.config.account_from_id,
                    )
                  "
                  :href="
                    entityLink(
                      transaction.config.account_from,
                      transaction.config.account_from_id,
                      'from',
                    )
                  "
                  :title="
                    entityLinkTitle(transaction.config.account_from, 'from')
                  "
                >
                  {{ transaction.config.account_from.name }}
                </a>
                <span v-else>
                  {{ transaction.config.account_from?.name || __('Not set') }}
                </span>
              </dd>

              <dt class="col-6">
                {{ accountToFieldLabel }}
              </dt>
              <dd
                class="col-6"
                :class="{ 'not-set': !transaction.config.account_to?.name }"
                dusk="label-account-to-name"
              >
                <a
                  v-if="
                    entityHasLink(
                      transaction.config.account_to,
                      transaction.config.account_to_id,
                    )
                  "
                  :href="
                    entityLink(
                      transaction.config.account_to,
                      transaction.config.account_to_id,
                      'to',
                    )
                  "
                  :title="entityLinkTitle(transaction.config.account_to, 'to')"
                >
                  {{ transaction.config.account_to.name }}
                </a>
                <span v-else>
                  {{ transaction.config.account_to?.name || __('Not set') }}
                </span>
              </dd>

              <dt class="col-6">
                {{ __('Comment') }}
              </dt>
              <dd class="col-6" :class="{ 'not-set': !transaction.comment }">
                {{ transaction.comment || __('Not set') }}
              </dd>

              <dt class="col-6">
                {{ __('Scheduled') }}
              </dt>
              <dd class="col-6">
                <i
                  v-if="transaction.schedule"
                  class="fa fa-check text-success"
                  :title="__('Yes')"
                ></i>
                <i v-else class="fa fa-ban text-danger" :title="__('No')"></i>
              </dd>

              <dt class="col-6">
                {{ __('Reconciled') }}
              </dt>
              <dd class="col-6">
                <i
                  v-if="transaction.reconciled"
                  class="fa fa-check text-success"
                  :title="__('Yes')"
                ></i>
                <i v-else class="fa fa-ban text-danger" :title="__('No')"></i>
              </dd>

              <dt v-if="createdOrigin" class="col-6">
                {{ __('Origin') }}
              </dt>
              <dd v-if="createdOrigin" class="col-6">
                <span
                  class="badge bg-success"
                  data-coreui-toggle="tooltip"
                  data-coreui-placement="top"
                  :title="createdOrigin.decision_reason || null"
                >
                  {{ __('Auto-recorded') }}
                </span>
                <span
                  v-if="createdOrigin.reviewed_at"
                  class="badge bg-success ms-1"
                >
                  {{ __('Checked') }}
                </span>
                <button
                  v-else
                  type="button"
                  class="badge bg-warning border-0 ms-1"
                  dusk="confirm-origin"
                  :title="__('Click once it\'s correct, fixed or not')"
                  :disabled="confirming"
                  @click="confirmOrigin"
                >
                  {{ __('Needs checking') }}
                </button>
              </dd>
            </dl>
          </div>
        </div>

        <div class="card mb-3">
          <div class="card-header">
            <div class="card-title">
              {{ __('Amounts') }}
            </div>
          </div>
          <div class="card-body">
            <dl class="row mb-0">
              <dt class="col-6">
                {{ ammountFromFieldLabel }}
              </dt>
              <dd class="col-6">
                {{
                  toFormattedCurrency(
                    transaction.config.amount_from,
                    locale,
                    ammountFromCurrency,
                  )
                }}
              </dd>

              <dt v-if="exchangeRatePresent" class="col-6">
                {{ __('Exchange rate') }}
              </dt>
              <dd v-if="exchangeRatePresent" class="col-6">
                {{ exchangeRate }}
              </dd>

              <dt v-if="exchangeRatePresent" class="col-6">
                {{ __('Amount to') }}
              </dt>
              <dd v-if="exchangeRatePresent" class="col-6">
                {{
                  toFormattedCurrency(
                    transaction.config.amount_to,
                    locale,
                    transaction.config.account_to?.config.currency,
                  )
                }}
              </dd>

              <dt v-if="!transactionTypeIsTransfer" class="col-6">
                {{ __('Total allocated') }}
              </dt>
              <dd v-if="!transactionTypeIsTransfer" class="col-6">
                {{
                  toFormattedCurrency(
                    allocatedAmount,
                    locale,
                    ammountFromCurrency,
                  )
                }}
              </dd>

              <dt v-if="!transactionTypeIsTransfer" class="col-6">
                {{ __('Not allocated') }}
              </dt>
              <dd v-if="!transactionTypeIsTransfer" class="col-6">
                {{
                  toFormattedCurrency(
                    remainingAmountNotAllocated,
                    locale,
                    ammountFromCurrency,
                  )
                }}
              </dd>
            </dl>
          </div>
        </div>

        <transaction-schedule
          :is-visible="transaction.schedule"
          :is-schedule="transaction.schedule"
          :schedule="transaction.transaction_schedule || {}"
        ></transaction-schedule>
      </div>

      <div class="col-md-8">
        <transaction-item-container
          :transaction-items="transaction.transaction_items"
          :currency="ammountFromCurrency"
          :enabled="!transactionTypeIsTransfer"
        ></transaction-item-container>
      </div>
    </div>
  </div>
</template>

<script>
  import TransactionItemContainer from './ItemContainer.vue';
  import TransactionSchedule from './Schedule.vue';
  import { __, toFormattedCurrency, toFormattedDate } from '@/shared/lib/i18n';
  import * as toastHelpers from '@/shared/lib/toast';
  import { initializeBootstrapTooltips } from '@/shared/lib/helpers';

  export default {
    components: {
      'transaction-item-container': TransactionItemContainer,
      'transaction-schedule': TransactionSchedule,
    },

    props: {
      transaction: {
        type: Object,
        default: () => ({}),
      },
      locale: {
        type: String,
        default: window.YAFFA.userSettings.locale,
      },
    },

    data() {
      return { confirming: false };
    },

    computed: {
      // Set when the transaction was recorded automatically from an AI document
      createdOrigin() {
        return (
          this.transaction.origins?.find(
            (origin) => origin.relation === 'created',
          ) ?? null
        );
      },

      // Account TO and FROM labels based on transaction type
      accountFromFieldLabel() {
        if (
          this.transaction.transaction_type === 'withdrawal' ||
          this.transaction.transaction_type === 'transfer'
        ) {
          return __('Account from');
        }

        return __('Payee');
      },

      accountToFieldLabel() {
        if (
          this.transaction.transaction_type === 'deposit' ||
          this.transaction.transaction_type === 'transfer'
        ) {
          return __('Account to');
        }

        return __('Payee');
      },

      // Amount from label is different for transfer
      ammountFromFieldLabel() {
        return this.exchangeRatePresent ? __('Amount from') : __('Amount');
      },

      // Amound from currency is dependent on transaction type
      ammountFromCurrency() {
        if (
          this.transaction.transaction_type === 'withdrawal' ||
          this.transaction.transaction_type === 'transfer'
        ) {
          return this.transaction.config.account_from?.config.currency;
        }

        return this.transaction.config.account_to?.config.currency;
      },

      // Calculate the summary of all existing items and their values
      allocatedAmount() {
        return this.transaction.transaction_items
          .map((item) => Number(item.amount) || 0)
          .reduce((amount, currentValue) => amount + currentValue, 0);
      },

      remainingAmountNotAllocated() {
        return this.transaction.config.amount_from - this.allocatedAmount;
      },

      // Indicates if transaction type is transfer, and currencies of accounts are different
      exchangeRatePresent() {
        return (
          this.transaction.config.account_from?.config.currency &&
          this.transaction.config.account_to?.config.currency &&
          this.transaction.config.account_from.config.currency.id !==
            this.transaction.config.account_to.config.currency.id
        );
      },

      exchangeRate() {
        const from = this.transaction.config.amount_from;
        const to = this.transaction.config.amount_to;

        if (from && to) {
          return (Number(to) / Number(from)).toFixed(4);
        }

        return 0;
      },
      transactionTypeIsTransfer() {
        return this.transaction?.transaction_type === 'transfer';
      },
    },

    mounted() {
      initializeBootstrapTooltips(this.$el);
    },

    updated() {
      initializeBootstrapTooltips(this.$el);
    },
    methods: {
      confirmOrigin() {
        this.confirming = true;

        window.axios
          .post(
            window.route('api.v1.transaction-origins.review', {
              origin: this.createdOrigin.id,
            }),
          )
          .then(() => {
            this.createdOrigin.reviewed_at = new Date().toISOString();
            toastHelpers.showSuccessToast(__('Transaction marked as checked'));
            // Lists showing the same transaction (e.g. the AI documents table) update themselves
            window.dispatchEvent(
              new CustomEvent('transactionOriginReviewed', {
                detail: { id: this.createdOrigin.id },
              }),
            );
          })
          .catch((error) => {
            toastHelpers.showErrorToast(
              __('Error while reviewing document: :errorMessage', {
                errorMessage: error.response?.data?.message || error.message,
              }),
            );
          })
          .finally(() => {
            this.confirming = false;
          });
      },

      entityHasLink(entity, entityId) {
        return Boolean(entityId && entity?.name);
      },

      entityIsPayee(entity, side) {
        if (entity?.config_type) {
          return entity.config_type === 'payee';
        }

        if (side === 'from') {
          return !['withdrawal', 'transfer'].includes(
            this.transaction.transaction_type,
          );
        }

        return !['deposit', 'transfer'].includes(
          this.transaction.transaction_type,
        );
      },

      entityLink(entity, entityId, side) {
        return route('account-entity.show', {
          account_entity: entityId,
        });
      },

      entityLinkTitle(entity, side) {
        if (this.entityIsPayee(entity, side)) {
          return __('Go to payee');
        }

        return __('Go to account');
      },

      formattedDate(date) {
        return toFormattedDate(date, this.locale, undefined);
      },
      toFormattedCurrency,
      route,
      capitalize(string) {
        return string[0].toUpperCase() + string.slice(1);
      },
      __,
    },
  };
</script>
