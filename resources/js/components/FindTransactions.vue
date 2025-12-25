<template>
    <div class="row">
        <div class="col-sm-3">
            <div class="card mb-3">
                <div class="card-header">
                    <div class="card-title">
                        {{ __('Actions') }}
                    </div>
                </div>
                <ul class="list-group list-group-flush">
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <span>
                            {{ __('Update') }}
                        </span>
                        <button
                                name="reload"
                                type="button"
                                class="btn btn-sm btn-primary"
                                :disabled="busy || !ready"
                                @click="getTransactions"
                        >
                            <i class="fas fa-sync-alt"></i>
                        </button>
                    </li>
                </ul>
            </div>

            <date-range-selector
                    :initial-date-from="dateFrom"
                    :initial-date-to="dateTo"
                    @update="onUpdateDateRange"
            ></date-range-selector>

            <find-transaction-select-card
                    property="category"
                    title="Category"
                    placeholder="Select category"
                    search-api-path="/api/assets/category"
                    search_label_field="text"
                    details-api-path="/api/assets/category/#id#"
                    details-label-field="full_name"
                    :preset-item-ids="selectedCategories"
                    @update="onUpdateCategory($event)"
                    @preset-ready="setReadyFlag($event)"
            ></find-transaction-select-card>

            <find-transaction-select-card
                    search-api-path="/api/assets/payee"
                    property="payee"
                    title="Payee"
                    placeholder="Select payee"
                    details-api-path="/api/assets/payee/#id#"
                    :preset-item-ids="selectedPayees"
                    @update="onUpdatePayee($event)"
                    @preset-ready="setReadyFlag($event)"
            ></find-transaction-select-card>

            <find-transaction-select-card
                    search-api-path="/api/assets/account"
                    property="account"
                    title="Account"
                    placeholder="Select account"
                    details-api-path="/api/assets/account/#id#"
                    :preset-item-ids="selectedAccounts"
                    @update="onUpdateAccount($event)"
                    @preset-ready="setReadyFlag($event)"
            ></find-transaction-select-card>

            <find-transaction-select-card
                    search-api-path="/api/assets/tag"
                    search_label_field="text"
                    property="tag"
                    title="Tag"
                    placeholder="Select tag"
                    details-api-path="/api/assets/tag/#id#"
                    :preset-item-ids="selectedTags"
                    @update="onUpdateTag($event)"
                    @preset-ready="setReadyFlag($event)"
            ></find-transaction-select-card>

        </div>
        <div class="col-sm-9">
            <div class="card">
                <div class="card-header">
                    <ul class="nav nav-tabs card-header-tabs">
                        <li class="nav-item">
                            <button
                                    class="nav-link active"
                                    id="nav-summary"
                                    data-coreui-toggle="tab"
                                    data-coreui-target="#tab-summary"
                                    type="button"
                                    role="tab"
                                    aria-controls="tab-summary"
                                    aria-selected="true"
                            >
                                {{ __('Summary') }}
                            </button>
                        </li>
                        <li class="nav-item">
                            <button
                                    class="nav-link"
                                    id="nav-transaction-list"
                                    data-coreui-toggle="tab"
                                    data-coreui-target="#tab-transaction-list"
                                    type="button"
                                    role="tab"
                                    aria-controls="tab-transaction-list"
                                    aria-selected="false"
                            >
                                {{ __('List of transactions') }}
                            </button>
                        </li>
                        <li class="nav-item">
                            <button
                                    class="nav-link"
                                    id="nav-timeline-charts"
                                    data-coreui-toggle="tab"
                                    data-coreui-target="#tab-timeline-charts"
                                    type="button"
                                    role="tab"
                                    aria-controls="tab-timeline-charts"
                                    aria-selected="false"
                            >
                                {{ __('Timeline charts') }}
                            </button>
                        </li>
                        <li class="nav-item">
                            <button
                                    class="nav-link"
                                    id="nav-category-charts"
                                    data-coreui-toggle="tab"
                                    data-coreui-target="#tab-category-charts"
                                    type="button"
                                    role="tab"
                                    aria-controls="tab-category-charts"
                                    aria-selected="false"
                            >
                                {{ __('Category charts') }}
                            </button>
                        </li>
                    </ul>
                </div>

                <div class="card-body">
                    <div class="tab-content">
                        <div
                            class="tab-pane fade show active"
                            id="tab-summary"
                            role="tabpanel"
                            aria-labelledby="nav-summary"
                            tabindex="0"
                        >
                            <reporting-canvas-summary
                                :transactions="transactions"
                                :busy="busy"
                            ></reporting-canvas-summary>
                        </div>
                        <div
                                class="tab-pane fade"
                                id="tab-transaction-list"
                                role="tabpanel"
                                aria-labelledby="nav-transaction-list"
                                tabindex="1"
                        >
                            <table class="table table-bordered table-hover no-footer" ref="dataTable"></table>
                        </div>
                        <div
                                class="tab-pane fade"
                                id="tab-timeline-charts"
                                role="tabpanel"
                                aria-labelledby="nav-timeline-charts"
                                tabindex="3"
                        >
                            <reporting-canvas-timeline
                                    :transactions="transactions"
                                    :busy="busy"
                            ></reporting-canvas-timeline>
                        </div>
                        <div
                                class="tab-pane fade"
                                id="tab-category-charts"
                                role="tabpanel"
                                aria-labelledby="nav-category-charts"
                                tabindex="4"
                        >
                            <reporting-canvas-categories
                                    :transactions="transactions"
                                    :busy="busy"
                            ></reporting-canvas-categories>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <transaction-show-modal></transaction-show-modal>
    </div>
</template>

<script>

import { __ as translator, processTransaction } from "../helpers"
import * as dataTableHelpers from "./dataTableHelper";
import FindTransactionSelectCard from "./FindTransactionSelectCard.vue"
import DateRangeSelector from "./DateRangeSelector.vue";
import ReportingCanvasFindTransactionsCategoryDetails from "./ReportingWidgets/ReportingCanvas-FindTransactions-CategoryDetails.vue";
import ReportingCanvasFindTransactionsSummary from "./ReportingWidgets/ReportingCanvas-FindTransactions-Summary.vue";
import ReportingCanvasFindTransactionsTimeline from "./ReportingWidgets/ReportingCanvas-FindTransactions-Timeline.vue";
import TransactionShowModal from './../components/TransactionDisplay/Modal.vue'

require ('datatables.net-bs5');

export default {
    name: 'FindTransactions',
    components: {
        'find-transaction-select-card': FindTransactionSelectCard,
        'transaction-show-modal': TransactionShowModal,
        'date-range-selector': DateRangeSelector,
        'reporting-canvas-categories': ReportingCanvasFindTransactionsCategoryDetails,
        'reporting-canvas-summary': ReportingCanvasFindTransactionsSummary,
        'reporting-canvas-timeline': ReportingCanvasFindTransactionsTimeline,
    },
    data() {
        const urlParams = new URLSearchParams(window.location.search);
        return {
            busy: false,
            ready: false,
            dataTable: null,
            dateFrom: urlParams.get('date_from') || null,
            dateTo: urlParams.get('date_to') || null,
            selectedAccounts: this.getUrlParams('accounts'),
            selectedCategories: this.getUrlParams('categories'),
            selectedPayees: this.getUrlParams('payees'),
            selectedTags: this.getUrlParams('tags'),
            presetsReady: {
                category: false,
                payee: false,
                account: false,
                tag: false,
            },
            transactions: [],
        }
    },
    methods: {
        setReadyFlag(event) {
            this.presetsReady[event] = true;
            this.ready = Object.values(this.presetsReady).every((item) => item === true);
        },
        onUpdateDateRange(event) {
            this.dateFrom = event.dateFrom;
            this.dateTo = event.dateTo;
            this.rebuildUrl();
        },
        onUpdateCategory(event) {
            this.selectedCategories = event;
            this.rebuildUrl();
        },
        onUpdatePayee(event) {
            this.selectedPayees = event;
            this.rebuildUrl();
        },
        onUpdateAccount(event) {
            this.selectedAccounts = event;
            this.rebuildUrl();
        },
        onUpdateTag(event) {
            this.selectedTags = event;
            this.rebuildUrl();
        },
        rebuildUrl() {
            let params = [];

            // Date from
            if (this.dateFrom) {
                params.push('date_from=' + this.dateFrom);
            }

            // Date to
            if (this.dateTo) {
                params.push('date_to=' + this.dateTo);
            }

            // Accounts
            const accounts = this.selectedAccounts.map((item) => 'accounts[]=' + item);
            params.push(...accounts);

            // Categories
            const categories = this.selectedCategories.map((item) => 'categories[]=' + item);
            params.push(...categories);

            // Payees
            const payees = this.selectedPayees.map((item) => 'payees[]=' + item);
            params.push(...payees);

            // Tags
            const tags = this.selectedTags.map((item) => 'tags[]=' + item);
            params.push(...tags);

            window.history.pushState('', '', window.location.origin + window.location.pathname + '?' + params.join('&'));
        },

        getTransactions() {
            this.busy = true;

            window.axios.get('/api/transactions', {
                params: {
                    date_from: this.dateFrom,
                    date_to: this.dateTo,
                    accounts: this.selectedAccounts,
                    categories: this.selectedCategories,
                    payees: this.selectedPayees,
                    tags: this.selectedTags,
                }
            })
                .then(response => {
                    this.transactions = response.data.data.map(processTransaction);
                })
                .then(() => {
                    this.dataTable.clear();
                    this.dataTable.rows.add(this.transactions);
                    this.dataTable.draw();
                })
                .catch(error => {
                    // Emit a custom event to global scope about the result
                    let notificationEvent = new CustomEvent('toast', {
                        detail: {
                            header: __('Error'),
                            body: __('Error getting transactions: :error', {error: error}),
                            toastClass: "bg-danger"
                        }
                    });
                    window.dispatchEvent(notificationEvent);
                })
                .finally(() => {
                    this.busy = false;
                });
        },

        /**
         * This helper function is intended to provide flexibility in getting URL parameters, in terms of various array formats.
         * E.g. if paramName is 'tags', it should return anything that is in the URL like: 'tags[]' or 'tags[0]'
         *
         * @param paramName
         * @returns {string[]} Array of URL parameters
         */
        getUrlParams(paramName) {
            const urlParams = new URLSearchParams(window.location.search);
            const regex = new RegExp(`^${paramName}\\[(\\d)?\\]$`);

            let params = [];

            urlParams.forEach((value, key) => {
                if (regex.test(key)) {
                  params.push(value);
                }
            });

            return params;
        },

        /**
         * Define the translation helper function locally.
         */
        __: function (string, replace) {
            return translator(string, replace);
        },
    },

    watch: {
        // When all preselected filters are ready, get the transactions
        ready: function (newReady) {
            if (newReady) {
                this.getTransactions();
            }
        },
        busy: function (newBusy) {
            if (!this.dataTable) {
                return;
            }
            this.dataTable.processing(newBusy);
        }
    },

    mounted() {
        this.dataTable = $(this.$refs.dataTable).DataTable({
            data: this.transactions,
            processing: true,
            columns: [
                dataTableHelpers.transactionColumnDefinition.dateFromCustomField('date', __('Date'), window.YAFFA.locale),
                dataTableHelpers.transactionColumnDefinition.type,
                {
                    title: __('From'),
                    defaultContent: '',
                    data: 'config.account_from.name',
                },
                {
                    title: __('To'),
                    defaultContent: '',
                    data: 'config.account_to.name',
                },
                dataTableHelpers.transactionColumnDefinition.category,
                dataTableHelpers.transactionColumnDefinition.amount,
                dataTableHelpers.transactionColumnDefinition.extra,
                {
                    data: 'id',
                    defaultContent: '',
                    title: __("Actions"),
                    render: function(data, _type) {
                        return  dataTableHelpers.dataTablesActionButton(data, 'quickView') +
                            dataTableHelpers.dataTablesActionButton(data, 'show') +
                            dataTableHelpers.dataTablesActionButton(data, 'edit') +
                            dataTableHelpers.dataTablesActionButton(data, 'clone') +
                            dataTableHelpers.dataTablesActionButton(data, 'delete');
                    },
                    className: "dt-nowrap",
                    orderable: false,
                    searchable: false,
                }
            ],
            order: [
                [0, "asc"]
            ]
        });

        // Delete transaction icon functionality
        dataTableHelpers.initializeAjaxDeleteButton(this.$refs.dataTable);
        dataTableHelpers.initializeQuickViewButton(this.$refs.dataTable);

        this.ready = true;
    }
}
</script>
