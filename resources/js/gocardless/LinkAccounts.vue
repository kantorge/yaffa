<template>
    <div class="row">
        <div class="col-md-4">
            <div class="card">
                <div class="card-header d-flex justify-content-between">
                    <div class="card-title">
                        {{ __('GoCardless Accounts') }}
                    </div>
                    <div>
                        <button
                                class="btn btn-sm btn-ghost-danger"
                                :title="__('Clear selection')"
                                @click="selectedGocardlessAccount = undefined"
                                v-show="selectedGocardlessAccount"
                        >
                            <i class="fa fa-fw fa-times"></i>
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <select
                            size="10"
                            class="form-control"
                            v-model="selectedGocardlessAccount"
                    >
                        <option
                            v-for="account in unlinkedGoCardlessAccounts"
                            :key="account.id"
                            :value="account.id"
                        >
                            {{ account.name }}
                        </option>
                    </select>
                    <div>

                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card mb-3">
                <div class="card-header d-flex justify-content-between">
                    <div class="card-title">
                        {{ __('YAFFA Accounts') }}
                    </div>
                    <div>
                        <button
                                class="btn btn-sm btn-ghost-danger"
                                :title="__('Clear selection')"
                                @click="selectedAccount = undefined"
                                v-show="selectedAccount"
                        >
                            <i class="fa fa-fw fa-times"></i>
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <select
                            size="10"
                            class="form-control"
                            v-model="selectedAccount"
                    >
                        <option
                            v-for="account in unlinkedAccounts"
                            :key="account.id"
                            :value="account.id"
                        >
                            {{ account.name }}
                        </option>
                    </select>
                    <div>

                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header">
                    <div
                            class="card-title collapse-control"
                            data-coreui-toggle="collapse"
                            data-coreui-target="#cardFilters-Accounts">
                        <i class="fa fa-angle-down"></i>
                        {{ __('Filters for YAFFA accounts') }}
                    </div>
                </div>
                <ul class="list-group list-group-flush collapse show" aria-expanded="true" id="cardFilters-Accounts">
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        {{ __('Active') }}
                        <div aria-label="Toggle button group for active" class="btn-group" role="group">
                            <input
                                    type="radio"
                                    class="btn-check"
                                    id="table_filter_active_yes"
                                    value="Yes"

                                    v-model="filter_active_accounts"
                            >
                            <label
                                    class="btn btn-outline-primary btn-xs"
                                    for="table_filter_active_yes"
                                    :title="__('Yes')"
                            >
                                <span class="fa fa-fw fa-check"></span>
                            </label>

                            <input
                                    type="radio"
                                    class="btn-check"
                                    id="table_filter_active_any"
                                    value="Any"
                                    v-model="filter_active_accounts"
                            >
                            <label
                                    class="btn btn-outline-primary btn-xs"
                                    for="table_filter_active_any"
                                    :title="__('Any')"
                            >
                                <span class="fa fa-fw fa-circle"></span>
                            </label>

                            <input
                                    type="radio"
                                    class="btn-check"
                                    id="table_filter_active_no"
                                    value="No"
                                    v-model="filter_active_accounts"
                            >
                            <label
                                    class="btn btn-outline-primary btn-xs"
                                    for="table_filter_active_no"
                                    :title="__('No')"
                            >
                                <span class="fa fa-fw fa-close"></span>
                            </label>
                        </div>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <label class="col-6" for="table_filter_search_text">
                            {{ __('Search') }}
                        </label>
                        <input
                                autocomplete="off"
                                class="form-control form-control-sm"
                                type="text"
                                v-model="filter_search_text_accounts"
                        >
                    </li>
                </ul>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card mb-3">
                <div class="card-header d-flex justify-content-between">
                    <div class="card-title">
                        {{ __('Linked Accounts') }}
                    </div>
                    <div>
                        <button
                                class="btn btn-sm btn-success me-2"
                                :title="__('Create link for selected accounts')"
                                @click="createLink"
                                v-show="selectedAccount && selectedGocardlessAccount"
                                :disabled="busy"
                        >
                            <i v-if="!busy" class="fa fa-fw fa-link"></i>
                            <i v-else class="fa fa-fw fa-spinner fa-spin"></i>
                        </button>
                        <button
                                class="btn btn-sm btn-danger"
                                :title="__('Delete link')"
                                :disabled="!selectedPair || busy"
                                @click="removeLink"
                                v-show="selectedPair"
                        >
                            <i class="fa fa-fw fa-times"></i>
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <select
                            size="10"
                            class="form-control"
                            v-model="selectedPair"
                    >
                        <option
                            v-for="account in pairs"
                            :key="account.id"
                            :value="account.id"
                        >
                            {{ account.name }} <> {{ account.config.gocardless_account.name }}
                        </option>
                    </select>
                    <div>

                    </div>
                </div>
            </div>
            <div class="card mb-3">
                <div class="card-header">
                    <div
                            class="card-title collapse-control"
                            data-coreui-toggle="collapse"
                            data-coreui-target="#cardActions-Pairs">
                        <i class="fa fa-angle-down"></i>
                        {{ __('Actions for selected pair') }}
                        <span title="This is probably not the final place for these actions">*</span>
                    </div>
                </div>
                <ul class="list-group list-group-flush collapse show" aria-expanded="true" id="cardActions-Pairs">
                    <li
                            class="list-group-item d-flex justify-content-between align-items-center"
                    >
                        <label class="col-6">
                            {{ __('Download 90 days transaction history') }}
                        </label>
                        <button
                                class="btn btn-sm btn-primary"
                                :disabled="!selectedPair || busy"
                                @click="getTransactionHistory(selectedPair, 90)"
                        >
                            <i v-if="!busy" class="fa fa-fw fa-download"></i>
                            <i v-else class="fa fa-fw fa-spinner fa-spin"></i>
                        </button>
                    </li>
                    <li
                            class="list-group-item d-flex justify-content-between align-items-center"
                    >
                        <label class="col-6">
                            {{ __('Download 30 days transaction history') }}
                        </label>
                        <button
                                class="btn btn-sm btn-primary"
                                :disabled="!selectedPair || busy"
                                @click="getTransactionHistory(selectedPair, 30)"
                        >
                            <i v-if="!busy" class="fa fa-fw fa-download"></i>
                            <i v-else class="fa fa-fw fa-spinner fa-spin"></i>
                        </button>
                    </li>
                    <li
                            class="list-group-item d-flex justify-content-between align-items-center"
                    >
                        <label class="col-6">
                            {{ __('Download 7 days transaction history') }}
                        </label>
                        <button
                                class="btn btn-sm btn-primary"
                                :disabled="!selectedPair || busy"
                                @click="getTransactionHistory(selectedPair, 7)"
                        >
                            <i v-if="!busy" class="fa fa-fw fa-download"></i>
                            <i v-else class="fa fa-fw fa-spinner fa-spin"></i>
                        </button>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</template>

<script>
    import * as helpers from '../helpers'

    export default {
        data() {
            return {
                goCardlessAccounts: window.goCardlessAccounts,
                accounts: window.accounts,
                selectedAccount: undefined,
                selectedGocardlessAccount: undefined,
                selectedPair: undefined,
                filter_active_accounts: 'Yes',
                filter_search_text_accounts: '',
                busy: false,
            };
        },
        mounted() {

        },
        methods: {
            createLink() {
                this.busy = true;

                // Send a POST request to the server to create the link.
                window.axios.post(
                    window.route('api.gocardless.createLink', {
                        account: this.selectedAccount,
                        gocardlessAccount: this.selectedGocardlessAccount
                    })
                )
                    .then(response => {
                        // Replace the account data in the accounts array.
                        const index = this.accounts.findIndex(a => a.id === this.selectedAccount);
                        this.accounts[index] = response.data.account;

                        // Remove the selection for both accounts
                        this.selectedAccount = undefined;
                        this.selectedGocardlessAccount = undefined;

                        // Display a success toast.
                        this.showToast(
                            __('Success'),
                            __('The accounts have been linked successfully'),
                            'bg-success'
                        );
                    })
                    .catch(error => {
                        this.showToast(
                            __('Error'),
                            error.response.data.error,
                            'bg-danger',
                            {
                                headerSmall: __('Failed to link the accounts')
                            }
                        );
                    })
                    .finally(() => {
                        this.busy = false;
                    });
            },

            removeLink() {
                this.busy = true;

                // Send a POST request to the server to remove the link.
                window.axios.post(
                    window.route('api.gocardless.deleteLink', {
                        account: this.selectedPair,
                        gocardlessAccount: this.accounts.find(a => a.id === this.selectedPair).config.gocardless_account_id
                    })
                )
                    .then(response => {
                        // Replace the account data in the accounts array.
                        const index = this.accounts.findIndex(a => a.id === response.data.account.id);
                        this.accounts[index] = response.data.account;

                        // Remove the selection for the pair
                        this.selectedPair = undefined;

                        // Display a success toast.
                        this.showToast(
                            __('Success'),
                            __('The link has been removed successfully'),
                            'bg-success'
                        );
                    })
                    .catch(error => {
                        this.showToast(
                            __('Error'),
                            error.response.data.message,
                            'bg-danger',
                            {
                                headerSmall: __('Failed to remove the link')
                            }
                        );
                    })
                    .finally(() => {
                        this.busy = false;
                    });
            },

            getTransactionHistory(accountId, days) {
                const account = this.accounts.find(a => a.id === accountId);

                // Convert the provided days into dates in YYYY-MM-DD format. Today and "days" days ago.
                const today = new Date();
                const daysAgo = new Date(today);
                daysAgo.setDate(today.getDate() - days);

                this.busy = true;

                // Send a GET request to the server to get the transaction history.
                // We only expect a summary to be returned about the retrieved and updated transactions.
                window.axios.get(
                    window.route('api.gocardless.getTransactions', {
                        gocardlessAccount: account.config.gocardless_account_id,
                        date_from: daysAgo.toISOString().split('T')[0],
                        date_to: today.toISOString().split('T')[0]
                    })
                )
                    .then(response => {
                        const message = __('Retrieved :created transactions and updated :updated transactions' , {
                            'created': response.data.transactions.created,
                            'updated': response.data.transactions.updated
                        });
                        // Display a success toast.
                        this.showToast(
                            __('Success'),
                            message,
                            'bg-success'
                        );
                    })
                    .catch(error => {
                        this.showToast(
                            __('Error'),
                            error.response.data.error,
                            'bg-danger',
                            {
                                headerSmall: __('Failed to get the transaction history')
                            }
                        );
                    })
                    .finally(() => {
                        this.busy = false;
                    });
            },

            /**
             * Import the translation helper function.
             */
            __: function (string, replace) {
                return helpers.__(string, replace);
            },

            /**
             * Import the toast display helper function.
             */
            showToast: function (header, body, toastClass, otherProperties) {
                return helpers.showToast(header, body, toastClass, otherProperties);
            },
        },
        computed: {
            unlinkedGoCardlessAccounts() {
                // First of all, filter out the accounts that are already linked.
                return this.goCardlessAccounts.filter(account => {
                    return !this.accounts.find(a => a.config.gocardless_account_id === account.id);
                })
                // Then, if a YAFFA account is selected, filter the accounts with the same currency
                .filter(account => {
                    if (!this.selectedAccount) {
                        return true;
                    }

                    const yaffaAccount = this.accounts.find(a => a.id === this.selectedAccount);
                    return account.currency_code === yaffaAccount.config.currency.iso_code;
                });
            },
            unlinkedAccounts() {
                return this.accounts
                // First of all, filter out the accounts that are already linked.
                .filter(account => {
                    return !account.config.gocardless_account_id;
                })
                // Then, if a GoCardless account is selected, filter the accounts with the same currency
                .filter(account => {
                    if (!this.selectedGocardlessAccount) {
                        return true;
                    }

                    const gocardlessAccount = this.goCardlessAccounts.find(a => a.id === this.selectedGocardlessAccount);
                    return account.config.currency.iso_code === gocardlessAccount.currency_code;
                })
                // Then, apply the search text, if it's not empty.
                .filter(account => {
                    if (this.filter_search_text_accounts === '') {
                        return true;
                    }

                    return account.name.toLowerCase().includes(this.filter_search_text_accounts.toLowerCase());
                })
                // Also, apply the active filter.
                .filter(account => {
                    if (this.filter_active_accounts === 'Any') {
                        return true;
                    }

                    return this.filter_active_accounts === 'Yes' ? account.active : !account.active;
                });
            },
            pairs() {
                return this.accounts.filter(account => {
                    return account.config.gocardless_account_id;
                });
            }
        }
    };
</script>
