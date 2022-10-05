<template>
    <div class="box">
        <div class="box-header">
            <h3 class="box-title">Total value</h3>
            <div class="pull-right" v-show="ready">
                {{ totalValue.toLocalCurrency(baseCurrency, false) }}
            </div>
        </div>
        <!-- /.box-header -->
        <div class="box-body">
            <div class="box-group" id="accordion">
                <div v-if="!ready">
                    <Skeletor
                        width="100%"
                        v-for="i in 5"
                    />
                </div>
                <div class="panel box box-primary" v-for="(accountGroup, accountGroupId) in accountBalanceDataByGroups" v-bind:key="accountGroupId">
                    <div class="box-header with-border">
                        <h4 class="box-title">
                            <a data-toggle="collapse" data-parent="#accordion" :href="'#collapse_' + accountGroupId" :aria-controls="'#collapse_' + accountGroupId" class="collapsed" aria-expanded="false">
                                {{ accountGroup.name }}
                            </a>
                        </h4>
                        <div class="pull-right" :class="(accountGroup.sum < 0 ? 'text-danger' : '')">
                            {{ accountGroup.sum.toLocalCurrency(baseCurrency, false) }}
                        </div>
                    </div>
                    <div :id="'collapse_' + accountGroupId" class="panel-collapse collapse" aria-expanded="false" style="height: 0px;">
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item" v-for="(account, index) in accountGroup.accounts" v-bind:key="index">
                                <a :href="getRoute(account)" class="product-title">
                                    <span v-html="account.name"></span>
                                    <span class="pull-right" :class="(account.sum < 0 ? 'text-danger' : '')">
                                        <span v-if="account.hasOwnProperty('sum_foreign')">{{ account.sum_foreign.toLocalCurrency(account.currency, false) }} / </span>
                                        {{ account.sum.toLocalCurrency(baseCurrency, false) }}
                                    </span>
                                </a>
                            </li >
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        <!-- /.box-body -->
        <div class="box-footer" v-show="ready">
            <div class="pull-right box-tools">
                Closed accounts are <span class="text-muted" v-html="(withClosed ? 'included' : 'hidden')"></span>
                <button class="btn btn-xs btn-default" style="margin-left: 1rem;" type="button" @click="toggleWithInactive" v-html="(withClosed ? 'Hide' : 'Show')">
                </button>
            </div>
        </div>
    </div>
    <!-- /.box -->
</template>

<script>
import { Skeletor } from 'vue-skeletor';

export default {
    components: { Skeletor },
    props: {},

    data() {
        return {
            baseCurrency: window.baseCurrency,
            accountBalanceData: [],
            withClosed: false,
            account: null,
            ready: false,
        }
    },

    created() {
        let $vm = this;
        axios.get('/api/account/balance/')
            .then(function(response) {
                $vm.accountBalanceData = response.data.accountBalanceData;
                $vm.account = response.data.account;
                $vm.ready = true;
            })
            .catch(function(error) {
                console.log(error)
            })
    },

    computed: {
        accountBalanceDataByGroups() {
            var groups = {};
            var $vm = this;
            this.accountBalanceData.forEach(function(account) {
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
        getRoute: function(account) {
            return route('account.history', {account: account.id})
        },

        toggleWithInactive: function() {
            this.withClosed = !this.withClosed;
        }
    }
}
</script>
