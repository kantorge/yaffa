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
                        <li class="nav-item">
                            <button
                                    class="nav-link"
                                    id="nav-pivot-table"
                                    data-coreui-toggle="tab"
                                    data-coreui-target="#tab-pivot-table"
                                    type="button"
                                    role="tab"
                                    aria-controls="tab-pivot-table"
                                    aria-selected="false"
                                    @click="initializePivotTable"
                            >
                                {{ __('Pivot Table') }}
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
                        <div
                                class="tab-pane fade"
                                id="tab-pivot-table"
                                role="tabpanel"
                                aria-labelledby="nav-pivot-table"
                                tabindex="5"
                        >
                            <div ref="pivotTableContainer" class="pivot-table-container"></div>
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
import FindTransactionSelectCard from "./FindTransactionSelectCard"
import DateRangeSelector from "./DateRangeSelector.vue";
import ReportingCanvasFindTransactionsCategoryDetails from "./ReportingWidgets/ReportingCanvas-FindTransactions-CategoryDetails.vue";
import ReportingCanvasFindTransactionsSummary from "./ReportingWidgets/ReportingCanvas-FindTransactions-Summary.vue";
import ReportingCanvasFindTransactionsTimeline from "./ReportingWidgets/ReportingCanvas-FindTransactions-Timeline.vue";
import TransactionShowModal from './../components/TransactionDisplay/Modal.vue'

require ('datatables.net-bs5');
import 'pivottable/dist/pivot.css';

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
            pivotTableInitialized: false,
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

        /**
         * Initialize the pivot table with transaction data
         */
        initializePivotTable() {
            if (this.pivotTableInitialized || this.transactions.length === 0) {
                return;
            }

            try {
                // Ensure jQuery and pivottable are loaded
                const $ = window.jQuery;
                if (!$ || !$.pivotUtilities) {
                    console.error('Pivottable library not loaded');
                    return;
                }



                // Helper function to parse currency string to number
                const parseCurrency = (value) => {
                    if (typeof value === 'number') return value;
                    if (!value) return 0;
                    // Remove currency symbols, commas, and parse as float
                    return parseFloat(String(value).replace(/[£$€,\s]/g, '')) || 0;
                };

                // Helper function to get category name from transaction
                const getCategoryName = (transaction) => {
                    // Try to get category from various possible locations
                    if (transaction.category?.full_name) {
                        return transaction.category.full_name;
                    }
                    if (transaction.category?.name) {
                        return transaction.category.name;
                    }
                    // Check transaction items for category
                    if (transaction.transaction_items && transaction.transaction_items.length > 0) {
                        const firstItem = transaction.transaction_items[0];
                        if (firstItem.category?.full_name) {
                            return firstItem.category.full_name;
                        }
                        if (firstItem.category?.name) {
                            return firstItem.category.name;
                        }
                    }
                    // Also try transactionItems (camelCase)
                    if (transaction.transactionItems && transaction.transactionItems.length > 0) {
                        const firstItem = transaction.transactionItems[0];
                        if (firstItem.category?.full_name) {
                            return firstItem.category.full_name;
                        }
                        if (firstItem.category?.name) {
                            return firstItem.category.name;
                        }
                    }
                    return 'Uncategorized';
                };

                // Transform transactions into a format suitable for pivot table
                // Note: transactions can have multiple items with different categories
                // We'll create one row per transaction item, or one row with "Uncategorized" if no items
                const pivotData = [];
                
                this.transactions.forEach((transaction, index) => {
                    const date = new Date(transaction.date);
                    
                    // For transfers (type 3), cashflow_value is NULL, so use amount_from/amount_to from config
                    let amount;
                    if (transaction.transaction_type_id === 3 && transaction.config) {
                        amount = Math.abs(transaction.config.amount_from || transaction.config.amount_to || 0);
                    } else {
                        amount = Math.abs(transaction.cashflow_value || 0);
                    }
                    
                    // Base transaction data
                    const baseData = {
                        'Date': transaction.date,
                        'Year': date.getFullYear(),
                        'Month': date.toLocaleString(window.YAFFA.locale, { month: 'long' }),
                        'Year-Month': `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}`,
                        'Type': transaction.transaction_type?.name || 'N/A',
                        'Config Type': transaction.config_type || 'N/A',
                        'From': transaction.config?.account_from?.name || '',
                        'To': transaction.config?.account_to?.name || '',
                        'Comment': transaction.comment || '',
                    };
                    
                    // Determine if this is a withdrawal (negative) or deposit (positive)
                    // transaction_type_id: 1 = withdrawal, 2 = deposit, 3 = transfer, 4 = Buy
                    // - Withdrawals and Buy transactions should be negative (money out)
                    // - For transfers, check if money is going out or coming in based on filtered accounts
                    let isNegative = transaction.transaction_type_id === 1 || transaction.transaction_type_id === 4;
                    
                    // Handle transfers specially based on the account filter
                    if (transaction.transaction_type_id === 3) {
                        const accountFromId = transaction.config?.account_from?.id;
                        const accountToId = transaction.config?.account_to?.id;
                        
                        if (this.selectedAccounts.length > 0) {
                            // We have account filters - check if this transfer involves any of them
                            const fromMatches = accountFromId && this.selectedAccounts.includes(accountFromId);
                            const toMatches = accountToId && this.selectedAccounts.includes(accountToId);
                            
                            if (fromMatches) {
                                // Money leaving the filtered account (or one of them)
                                isNegative = true;
                            } else if (toMatches) {
                                // Money entering the filtered account (or one of them)
                                isNegative = false;
                            } else {
                                // Neither account is in filter - use cashflow_value sign as fallback
                                isNegative = (transaction.cashflow_value || 0) < 0;
                            }
                        } else {
                            // No account filter - use cashflow_value sign
                            isNegative = (transaction.cashflow_value || 0) < 0;
                        }
                    }
                    
                    const directionalMultiplier = isNegative ? -1 : 1;
                    
                    // Check if transaction has items with categories
                    if (transaction.transaction_items && Array.isArray(transaction.transaction_items) && transaction.transaction_items.length > 0) {
                        // Create a row for each transaction item
                        transaction.transaction_items.forEach((item, itemIndex) => {
                            const categoryName = item.category?.full_name || item.category?.name || 'Uncategorized';
                            const itemAmount = Math.abs(item.amount || 0);
                            const directionalAmount = itemAmount * directionalMultiplier;
                            
                            pivotData.push({
                                ...baseData,
                                'Category': categoryName,
                                'Amount': itemAmount,
                                'Directional Amount': directionalAmount,
                            });
                        });
                    } else {
                        // No items, create single row with total amount
                        const directionalAmount = amount * directionalMultiplier;
                        
                        pivotData.push({
                            ...baseData,
                            'Category': 'Uncategorized',
                            'Amount': amount,
                            'Directional Amount': directionalAmount,
                        });
                    }
                });

                console.log('Pivot data sample:', JSON.parse(JSON.stringify(pivotData.slice(0, 3))));

                // Clear previous pivot table if exists
                $(this.$refs.pivotTableContainer).empty();

                // Initialize pivot table
                $(this.$refs.pivotTableContainer).pivotUI(pivotData, {
                    rows: ['Category'],
                    cols: ['Year-Month'],
                    aggregatorName: 'Sum',
                    vals: ['Amount'],
                    rendererName: 'Table',
                    sorters: {
                        'Year-Month': function(a, b) {
                            return a.localeCompare(b);
                        }
                    }
                });

                this.pivotTableInitialized = true;
            } catch (error) {
                console.error('Error initializing pivot table:', error);
                $(this.$refs.pivotTableContainer).html('<div class="alert alert-danger">Error loading pivot table: ' + error.message + '</div>');
            }
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
        },
        // Re-initialize pivot table when transactions change
        transactions: function () {
            this.pivotTableInitialized = false;
            // If pivot table tab is currently active, reinitialize it
            const pivotTab = document.getElementById('tab-pivot-table');
            if (pivotTab && pivotTab.classList.contains('active')) {
                this.$nextTick(() => {
                    this.initializePivotTable();
                });
            }
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
