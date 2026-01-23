<template>
  <div class="card mb-4" id="widgetAccountBalance">
    <div class="card-header d-flex justify-content-between">
      <div class="card-title">
        {{ __('Total value') }}
      </div>
      <div v-show="state === 'data-available'">
        {{ toFormattedCurrency(totalValue, locale, baseCurrency) }}
      </div>
    </div>
    <ul class="list-group list-group-flush" v-if="state === 'loading'">
      <li
        aria-hidden="true"
        class="list-group-item placeholder-glow"
        v-for="i in 5"
        v-bind:key="i"
      >
        <span class="placeholder col-12"></span>
      </li>
    </ul>
    <ul
      class="list-group list-group-flush"
      v-if="state === 'data-not-available'"
    >
      <li class="list-group-item list-group-item-warning">
        {{ errorMessage }}
      </li>
    </ul>
    <ul class="list-group list-group-flush" v-if="state === 'error'">
      <li class="list-group-item list-group-item-danger">
        {{ __('There was an error while getting account data: ') }}
        {{ errorMessage }}
      </li>
    </ul>
    <ul
      class="list-group list-group-flush"
      id="accordionAccountBalance"
      v-if="state === 'data-available'"
    >
      <li
        class="list-group-item"
        v-for="(accountGroup, accountGroupId) in accountBalanceDataByGroups"
        v-bind:key="accountGroupId"
      >
        <div class="d-flex justify-content-between">
          <span
            data-coreui-toggle="collapse"
            data-parent="#accordionAccountBalance"
            :data-coreui-target="'#collapse_' + accountGroupId"
            :aria-controls="'#collapse_' + accountGroupId"
            class="collapse-control collapsed"
            aria-expanded="false"
            role="button"
          >
            <i class="fa fa-angle-down"></i>
            {{ accountGroup.name }}
          </span>
          <span :class="{ 'text-danger': accountGroup.sum < 0 }">
            {{ toFormattedCurrency(accountGroup.sum, locale, baseCurrency) }}
          </span>
        </div>
        <div
          :id="'collapse_' + accountGroupId"
          class="list-group collapse mt-3"
          aria-expanded="false"
        >
          <a
            class="list-group-item d-flex justify-content-between list-group-item-action"
            :href="getRoute(account)"
            v-for="(account, index) in accountGroup.accounts"
            v-bind:key="index"
          >
            <span>
              {{ account.name }}
            </span>
            <span :class="{ 'text-danger': account.sum < 0 }">
              <span v-if="account.hasOwnProperty('sum_foreign')">
                {{
                  toFormattedCurrency(
                    account.sum_foreign,
                    locale,
                    account.currency,
                  )
                }}
                /
              </span>
              {{ toFormattedCurrency(account.sum, locale, baseCurrency) }}
            </span>
          </a>
        </div>
      </li>
    </ul>
    <div class="card-footer d-flex justify-content-between">
      <div></div>
      <div v-show="state === 'data-available'">
        <span
          v-if="withClosed"
          v-html="__('Closed accounts are <strong>included</strong>')"
        ></span>
        <span
          v-if="!withClosed"
          v-html="__('Closed accounts are <strong>hidden</strong>')"
        ></span>

        <button
          class="btn btn-sm btn-ghost-dark ms-1"
          type="button"
          @click="toggleWithInactive"
          v-html="withClosed ? __('Hide') : __('Show')"
        ></button>
      </div>
    </div>
  </div>
</template>

<script>
  import { __, toFormattedCurrency } from '../../helpers';
  import * as toastHelpers from '../../toast';

  export default {
    components: {
      helpers,
    },

    props: {
      locale: {
        type: String,
        default: window.YAFFA.locale,
      },
    },

    data() {
      return {
        baseCurrency: window.YAFFA.baseCurrency,
        accountBalanceData: [],
        withClosed: false,
        // Expected values: loading, data-loaded, data-not-available, error
        state: 'loading',
        errorMessage: null,
        retryInterval: 15000,
      };
    },

    created() {
      this.getAccountBalanceData();
    },

    computed: {
      accountBalanceDataByGroups() {
        let groups = {};
        let $vm = this;

        /**
         * Group accounts by account group and return a new object.
         *
         * @private
         * @param {Object} account
         * @property {Number} account.account_group_id
         * @property {String} account.account_group_name
         * @returns {Object}
         */
        this.accountBalanceData.forEach(function (account) {
          // Skip closed accounts, if needed
          if (!$vm.withClosed && !account.active) {
            return;
          }

          if (!groups.hasOwnProperty(account.account_group_id)) {
            groups[account.account_group_id] = {
              name: account.account_group_name,
              accounts: [],
              sum: 0,
            };
          }

          groups[account.account_group_id].accounts.push(account);
          groups[account.account_group_id].sum += account.sum;
        });

        return groups;
      },

      totalValue() {
        let withClosedAccounts = this.withClosed;
        return this.accountBalanceData
          .filter((account) => withClosedAccounts || account.active)
          .reduce((sum, account) => sum + account.sum, 0);
      },
    },

    methods: {
      getAccountBalanceData: function () {
        // Verify if base currency is set. Without this, the widget cannot be displayed.
        if (!this.baseCurrency) {
          this.state = 'error';
          this.errorMessage = __('Base currency is not set');
          this.baseCurrency = {};

          return;
        }

        let $vm = this;
        this.state = 'loading';

        axios
          .get('/api/account/balance/')
          .then(function (response) {
            // Check if the response is valid data
            if (response.data.result === 'busy') {
              $vm.state = 'data-not-available';
              $vm.errorMessage = response.data.message;

              // Retry after 15 seconds
              setTimeout(function () {
                $vm.getAccountBalanceData();
              }, $vm.retryInterval);

              // Increase retry interval
              $vm.retryInterval *= 2;

              return;
            }

            $vm.accountBalanceData = response.data.accountBalanceData;
            $vm.state = 'data-available';
          })
          .catch(function (error) {
            $vm.state = 'error';
            $vm.errorMessage = error.message;

            toastHelpers.showErrorToast(error.message);
          });
      },

      getRoute: function (account) {
        return window.route('account-entity.show', {
          account_entity: account.id,
        });
      },

      toggleWithInactive: function () {
        this.withClosed = !this.withClosed;
      },
      toFormattedCurrency,
      __,
    },
  };
</script>
