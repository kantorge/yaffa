import 'datatables.net-bs5';
import "datatables.net-responsive-bs5";

import * as dataTableHelpers from './../components/dataTableHelper';

let transactionData = window.transactionData || [];

// Convert date strings to Date objects to allow consistent formatting
transactionData = transactionData.map(function (transaction) {
    if (transaction.date) {
        transaction.date = new Date(transaction.date);
    }
    return transaction;
});

console.log('Transaction data loaded:', transactionData.length, 'transactions');

// Initialize DataTable
const table = $('#reconcileTable').DataTable({
    data: transactionData,
    pageLength: 50,
    order: [[0, 'asc']],
    columnDefs: [
        // Date column (YYYY-MM-DD)
        {
            targets: 0,
            data: 'date',
            render: function(data, type) {
                if (type === 'display' && data) {
                    return data.toISOString().split('T')[0];
                }
                return data;
            },
            className: "dt-nowrap"
        },

        // From
        {
            targets: 1,
            width: '150px',
            data: 'account_from_name',
            render: function(data) {
                return data || '';
            }
        },

        // To
        {
            targets: 2,
            width: '150px',
            data: 'account_to_name',
            render: function(data) {
                return data || '';
            }
        },

        // Category (use helper)
        {
            targets: 3,
            data: null,
            render: function(_data, type, row) {
                return (dataTableHelpers.transactionColumnDefinition.category.render(null, type, row) || '');
            },
            orderable: false
        },

        // Comment
        {
            targets: 4,
            data: 'comment',
            render: function(data, type, row) {
                let html = data || '';
                if (row.categories && row.categories.length > 0) {
                    // categories already shown in category column; keep comment tidy
                }
                return html;
            }
        },

        // Withdrawal (match history logic)
        {
            targets: 5,
            width: '120px',
            className: 'text-end',
            render: function(_data, type, row) {
                if (row.transactionOperator !== -1) {
                    return '';
                }
                return dataTableHelpers.toFormattedCurrency(type, row.amount_from, window.YAFFA.locale, window.currency);
            }
        },

        // Deposit (match history logic)
        {
            targets: 6,
            width: '120px',
            className: 'text-end',
            render: function(_data, type, row) {
                if (row.transactionOperator !== 1) {
                    return '';
                }
                return dataTableHelpers.toFormattedCurrency(type, row.amount_to, window.YAFFA.locale, window.currency);
            }
        },

        // Balance
        {
            targets: 7,
            width: '120px',
            className: 'text-end fw-bold',
            data: 'running_total',
            render: function(data, type) {
                return dataTableHelpers.toFormattedCurrency(type, data, window.YAFFA.locale, window.currency);
            }
        },

        // Actions column
        {
            targets: 8,
            width: '180px',
            orderable: false,
            searchable: false,
            render: function(_data, _type, row) {
                if (row.transaction_type && row.transaction_type.type === 'Opening balance') {
                    return '';
                }

                let html = '';

                // Quick view / show / edit / clone / delete
                html += dataTableHelpers.dataTablesActionButton(row.id, 'quickView');
                html += dataTableHelpers.dataTablesActionButton(row.id, 'show');
                html += dataTableHelpers.dataTablesActionButton(row.id, 'edit');
                html += dataTableHelpers.dataTablesActionButton(row.id, 'clone');
                html += dataTableHelpers.dataTablesActionButton(row.id, 'delete');

                // Reconcile single transaction button (uses bulk endpoint for simplicity)
                if (!row.reconciled) {
                    html += '<button class="btn btn-xs btn-success btn-reconcile" data-id="' + row.id + '" title="' + __('Reconcile') + '"><i class="fa fa-fw fa-check"></i></button> ';
                } else {
                    html += '<span class="badge bg-success ms-1"><i class="fa fa-fw fa-check"></i></span>';
                }

                return html;
            }
        }
    ],
    columns: [
        { title: __('Date'), data: 'date' },
        { title: __('From'), data: 'account_from_name' },
        { title: __('To'), data: 'account_to_name' },
        { title: __('Category'), data: null },
        { title: __('Comment'), data: 'comment' },
        { title: __('Withdrawal'), data: null },
        { title: __('Deposit'), data: null },
        { title: __('Balance'), data: 'running_total' },
        { title: __('Actions'), data: null }
    ],
    dom: 'lrtip'
});

// Handle reconciled filter - removed as we no longer have reconcile column visible

dataTableHelpers.initializeQuickViewButton('#reconcileTable');
// Initialize quick view and delete handlers
dataTableHelpers.initializeQuickViewButton('#reconcileTable');
dataTableHelpers.initializeDeleteButtonListener('#reconcileTable', 'transactions.destroy');

// Handle single reconcile button
$(document).on('click', '.btn-reconcile', function () {
    const id = this.dataset.id;
    if (!id) return;

    if (!confirm(__('Reconcile this transaction?'))) {
        return;
    }

    fetch('/api/transactions/bulk-reconcile', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Accept': 'application/json'
        },
        body: JSON.stringify({ transaction_ids: [parseInt(id, 10)] })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            window.location.reload();
        } else {
            alert(__('Error reconciling transaction: :message', {message: data.message || 'Unknown error'}));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert(__('Error reconciling transaction. Please try again.'));
    });
});

// Mount Vue app and register components used in the page so the <balance-checkpoint-modal> component renders
import { createApp } from 'vue';
const app = createApp({});

// Add global translator function
app.config.globalProperties.__ = window.__;

import TransactionShowModal from './../components/TransactionDisplay/Modal.vue';
app.component('transaction-show-modal', TransactionShowModal);

import BalanceCheckpointModal from './../components/BalanceCheckpointModal.vue';
app.component('balance-checkpoint-modal', BalanceCheckpointModal);

app.mount('#app');
