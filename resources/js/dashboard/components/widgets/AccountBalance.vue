<template>
    <div id="widgetAccountBalance" class="card mb-4">
        <div
            class="card-header d-flex justify-content-between align-items-center"
        >
            <div class="card-title">
                {{ __('widget.accountBalance.cardTitle') }}
            </div>
            <div v-show="state === 'data-available'">
                {{ toFormattedCurrency(totalValue, locale, baseCurrency) }}
            </div>
        </div>
        <ul v-if="state === 'loading'" class="list-group list-group-flush">
            <li
                v-for="i in 5"
                :key="i"
                aria-hidden="true"
                class="list-group-item placeholder-glow"
            >
                <span class="placeholder col-12"></span>
            </li>
        </ul>
        <ul
            v-if="state === 'data-not-available'"
            class="list-group list-group-flush"
        >
            <li class="list-group-item list-group-item-warning">
                {{ errorMessage }}
            </li>
        </ul>
        <ul v-if="state === 'error'" class="list-group list-group-flush">
            <li class="list-group-item list-group-item-danger">
                {{ __('widget.accountBalance.loadErrorPrefix') }}
                {{ errorMessage }}
            </li>
        </ul>
        <ul
            v-if="state === 'data-available'"
            id="accordionAccountBalance"
            class="list-group list-group-flush"
        >
            <li
                v-for="(
                    accountGroup, accountGroupId
                ) in accountBalanceDataByGroups"
                :key="accountGroupId"
                class="list-group-item"
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
                        {{
                            toFormattedCurrency(
                                accountGroup.sum,
                                locale,
                                baseCurrency,
                            )
                        }}
                    </span>
                </div>
                <div
                    :id="'collapse_' + accountGroupId"
                    class="list-group collapse mt-3"
                    aria-expanded="false"
                >
                    <a
                        v-for="(account, index) in accountGroup.accounts"
                        :key="index"
                        class="list-group-item d-flex justify-content-between list-group-item-action"
                        :href="getRoute(account)"
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
                            {{
                                toFormattedCurrency(
                                    account.sum,
                                    locale,
                                    baseCurrency,
                                )
                            }}
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
                    v-html="__('widget.accountBalance.closedIncluded')"
                ></span>
                <span
                    v-if="!withClosed"
                    v-html="__('widget.accountBalance.closedHidden')"
                ></span>

                <button
                    class="btn btn-sm btn-ghost-dark ms-1"
                    type="button"
                    @click="toggleWithInactive"
                    v-html="
                        withClosed
                            ? __('widget.accountBalance.hideButton')
                            : __('widget.accountBalance.showButton')
                    "
                ></button>
            </div>
        </div>
    </div>
</template>

<script>
    import { __, toFormattedCurrency } from '@/shared/lib/i18n';
    import * as toastHelpers from '@/shared/lib/toast';
    import { pollUntilReady } from '@/shared/lib/busyPoll';

    export default {
        props: {
            locale: {
                type: String,
                default: window.YAFFA.userSettings.locale,
            },
        },

        data() {
            return {
                baseCurrency: window.YAFFA.userSettings.baseCurrency,
                accountBalanceData: [],
                withClosed: false,
                // Expected values: loading, data-loaded, data-not-available, error
                state: 'loading',
                errorMessage: null,
                cancelPoll: null,
            };
        },

        computed: {
            accountBalanceDataByGroups() {
                let groups = {};

                /**
                 * Group accounts by account group and return a new object.
                 *
                 * @private
                 * @param {Object} account
                 * @property {Number} account.account_group_id
                 * @property {String} account.account_group_name
                 * @returns {Object}
                 */
                this.accountBalanceData.forEach((account) => {
                    // Skip closed accounts, if needed
                    if (!this.withClosed && !account.active) {
                        return;
                    }

                    if (!Object.hasOwn(groups, account.account_group_id)) {
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

        created() {
            this.getAccountBalanceData();
        },

        beforeUnmount() {
            // Cancel any pending retry when the component is destroyed
            if (this.cancelPoll) {
                this.cancelPoll();
            }
        },

        methods: {
            getAccountBalanceData: function () {
                // Verify if base currency is set. Without this, the widget cannot be displayed.
                if (!this.baseCurrency) {
                    this.state = 'error';
                    this.errorMessage = __(
                        'widget.accountBalance.baseCurrencyMissing',
                    );
                    this.baseCurrency = {};

                    return;
                }

                this.state = 'loading';

                this.cancelPoll = pollUntilReady(
                    () =>
                        axios
                            .get(this.route('api.v1.accounts.balance'))
                            .then((response) => response.data),
                    {
                        onBusy: (message) => {
                            this.state = 'data-not-available';
                            this.errorMessage = message;
                        },
                        onReady: (data) => {
                            this.accountBalanceData = data.accountBalanceData;
                            this.state = 'data-available';
                        },
                        onError: (error) => {
                            this.state = 'error';
                            this.errorMessage = error.message;

                            toastHelpers.showErrorToast(error.message);
                        },
                    },
                );
            },

            getRoute: function (account) {
                return this.route('account-entity.show', {
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
