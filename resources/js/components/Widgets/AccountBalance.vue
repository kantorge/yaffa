<template>
  <div class="card mb-4">
    <div class="card-header d-flex justify-content-between">
      <div class="card-title">
        {{ __('Total value') }}
      </div>
      <div v-show="ready">
        {{ toFormattedCurrency(totalValue, locale, baseCurrency) }}
      </div>
    </div>
    <ul class="list-group list-group-flush" id="accordionAccountBalance" v-if="!ready">
      <li
          aria-hidden="true"
          class="list-group-item placeholder-glow"
          v-for="i in 5"
          v-bind:key="i"
      >
        <span class="placeholder col-12"></span>
      </li>
    </ul>
    <ul class="list-group list-group-flush" id="accordionAccountBalance" v-if="ready">
      <li
        class="list-group-item"
        v-for="(accountGroup, accountGroupId) in accountBalanceDataByGroups"
        v-bind:key="accountGroupId"
      >
        <div class="d-flex justify-content-between">
          <span
            data-coreui-toggle="collapse"
            data-parent="#accordionAccountBalance"
            :href="'#collapse_' + accountGroupId"
            :aria-controls="'#collapse_' + accountGroupId"
            class="collapse-control collapsed"
            aria-expanded="false"
            role="button"
          >
            <i class="fa fa-angle-down"></i>
            {{ accountGroup.name }}
          </span>
          <span :class="{ 'text-danger' : accountGroup.sum < 0}">
            {{ toFormattedCurrency(accountGroup.sum, locale, baseCurrency) }}
          </span>
        </div>
        <div :id="'collapse_' + accountGroupId" class="list-group collapse mt-3" aria-expanded="false">
          <a
            class="list-group-item d-flex justify-content-between list-group-item-action"
            :href="getRoute(account)"
            v-for="(account, index) in accountGroup.accounts"
            v-bind:key="index"
          >
            <span>
              {{ account.name }}
            </span>
            <span :class="{ 'text-danger' : account.sum < 0 }">
              <span v-if="account.hasOwnProperty('sum_foreign')">
                {{ toFormattedCurrency(account.sum_foreign, locale, account.currency) }} /
              </span>
              {{ toFormattedCurrency(account.sum, locale, baseCurrency) }}
            </span>
          </a>
        </div>
      </li>
    </ul>
    <div class="card-footer d-flex justify-content-between" v-show="ready">
      <div></div>
      <div>
        <span v-if="withClosed" v-html="__('Closed accounts are <strong>included</strong>')"></span>
        <span v-if="!withClosed" v-html="__('Closed accounts are <strong>hidden</strong>')"></span>

        <button
          class="btn btn-sm btn-outline-dark ms-1"
          type="button"
          @click="toggleWithInactive"
          v-html="(withClosed ? __('Hide') : __('Show'))"
        ></button>
      </div>
    </div>
  </div>
</template>

<script>
import * as helpers from '../../helpers';

export default {
  components: {
    helpers
  },

  props: {
    locale: {
      type: String,
      default: window.YAFFA.locale,
    }
  },

  data() {
    return {
      baseCurrency: window.YAFFA.baseCurrency,
      accountBalanceData: [],
      withClosed: false,
      account: null,
      ready: false,
    }
  },

  created() {
    let $vm = this;
    axios.get('/api/account/balance/')
      .then(function (response) {
        $vm.accountBalanceData = response.data.accountBalanceData;
        $vm.account = response.data.account;
        $vm.ready = true;
      })
      .catch(function (error) {
        console.log(error)
      })
  },

  computed: {
    accountBalanceDataByGroups() {
      var groups = {};
      var $vm = this;
      this.accountBalanceData.forEach(function (account) {
        // Skip closed accounts, if needed
        if (!$vm.withClosed && !account.active) {
          return;
        }

        if (!groups.hasOwnProperty(account.account_group_id)) {
          groups[account.account_group_id] = {
            name: account.account_group,
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
      var $vm = this;
      return this.accountBalanceData
        .filter((account) => $vm.withClosed || account.active)
        .reduce((sum, account) => sum + account.sum, 0);
    },
  },

  methods: {
    getRoute: function (account) {
      return window.route('account-entity.show', {account_entity: account.id})
    },

    toggleWithInactive: function () {
      this.withClosed = !this.withClosed;
    },

    toFormattedCurrency(input, locale, currencySettings) {
      return helpers.toFormattedCurrency(input, locale, currencySettings);
    },
  }
}
</script>
