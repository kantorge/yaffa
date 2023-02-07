/**
 * This functionality allows the user to open a CSV file and parse it to an array of objects.
 * The array of objects is then run throug a rule engine, to create a list of possible transactions.
 */

import 'datatables.net-bs5';
// Import dataTable helper functions
import * as dataTableHelpers from './../components/dataTableHelper'
import { toFormattedCurrency } from '../helpers';

// Import RRule library for handling schedules
import { RRule } from 'rrule';

import 'select2';
require('jquery-csv');

window.transactions = [];
window.account_currency = {};
window.unmatchedRows = [];
window.schedules = [];

// Helper function to save nested object values
function storeNestedObjectValue(base, names, value) {
    // If a value is given, remove the last name and keep it for later:
    var lastName = arguments.length === 3 ? names.pop() : false;

    // Walk the hierarchy, creating new objects where needed.
    // If the lastName was removed, then the last object is not set yet:
    for( var i = 0; i < names.length; i++ ) {
        base = base[ names[i] ] = base[ names[i] ] || {};
    }

    // If a value was given, set it to the last name:
    if( lastName ) base = base[ lastName ] = value;

    // Return the last object in the hierarchy:
    return base;
};

// Require the rule engine
// TODO: make this dynamic based on the selected account
let engine = require('./rules/hun_raiffeisen_v1.js');

// The following variable is used to store the current transaction being created.
let recentTransactionDraftId;

// CSV parse functionality
document.getElementById('csv_file').addEventListener('change', function () {
    if (!this.files || !this.files[0]) {
        return;
    }

    var myFile = this.files[0];
    var reader = new FileReader();

    reader.addEventListener('load', function (e) {

        let csvData = e.target.result;
        var csvRows = $.csv.toObjects(csvData, { separator: ';' });
        let processedRows = 0;

        // Run the rule engine for each row
        csvRows.forEach(function (transaction, index) {
            // Drop empty columns
            // TODO: Can and should this be generalized?
            delete transaction[''];

            let rawTransaction = {
                draftId: index,
                handled: false,
                hidden: false,
                similarTransactions: false,
                quickRecordingPossible: false,
                config: {},
            };

            engine.run(transaction)
                .then(({ events }) => {

                    // Loop all rules to extract transaction data from row
                    events.filter(event => event.params.processingRules).map(event => event.params.processingRules.map(rule => {
                        // Get value from rule
                        const value = rule.customFunction(transaction, rawTransaction);

                        // If field is provided as list of keys (.), split to an array and handle accordingly
                        if (rule.transactionField.includes('.')) {
                            const fieldPath = rule.transactionField.split('.');
                            storeNestedObjectValue(rawTransaction, fieldPath, value);
                        } else {
                            rawTransaction[rule.transactionField] = value;
                        }
                    }));

                    // TODO: proper filtering
                    if (rawTransaction.date) {
                        // Does this draft transaction qualify for a quick recording?
                        // It needs: date, account_from, account_to, amount_from, amount_to, payee default category
                        if (rawTransaction.date && rawTransaction.config && rawTransaction.config.account_from && rawTransaction.config.account_to && rawTransaction.config.amount_from && rawTransaction.config.amount_to) {
                            if (rawTransaction.transaction_type.name === 'withdrawal' && rawTransaction.config.account_to.config.category_id) {
                                rawTransaction.quickRecordingPossible = true;
                            } else if (rawTransaction.transaction_type.name === 'deposit' && rawTransaction.config.account_from.config.category_id) {
                                rawTransaction.quickRecordingPossible = true;
                            }
                        }

                        transactions.push(rawTransaction);
                    } else {
                        unmatchedRows.push(transaction);
                    }

                    processedRows++;

                    // If all rows have been processed, refill tables
                    if (processedRows === csvRows.length) {
                        refillUnmatchedRows(unmatchedRows);
                        table.clear().rows.add(transactions).draw();

                        // Also initiate collection of similar transactions
                        collectSimilarTransactions();
                    }
                })
        });
    });

    reader.readAsBinaryString(myFile);
});

function collectSimilarTransactions() {
    // Find min and max date in transactions array
    let minDate = new Date(Math.min.apply(Math, transactions.map(function (o) { return o.date; })));
    let maxDate = new Date(Math.max.apply(Math, transactions.map(function (o) { return o.date; })));

    // Get all standard transactions in the range of min and max date
    let url = new URL(window.location.origin + '/api/transactions');
    url.searchParams.append('date_from', minDate.isoDateString());
    url.searchParams.append('date_to', maxDate.isoDateString());

    fetch(url)
    .then(function(response) {
        // TODO: proper error handling
        if (!response.ok) {
            throw new Error('Network response was not OK');
        }
        return response.json()
    })
    .then(data => {
        let existingTransactions = data.data.map(transaction => {
            transaction.date = new Date(transaction.date);
            return transaction;
        });

        // Loop all transactions and associate similar transactions
        window.transactions.map(function (transaction) {
            transaction.similarTransactions = [];
            existingTransactions.forEach(function (existingTransaction) {
                // Calculate similarity between transactions using date, amount and accounts

                // Transaction types must match
                if (transaction.transaction_type.name !== existingTransaction.transaction_type.name) {
                    return
                }

                // Other fields count towards similarity
                let similarityCount = 0;
                const maxSimilarity = 4;

                if (transaction.date.isoDateString() === existingTransaction.date.isoDateString()) {
                    similarityCount++;
                }
                if (transaction.config.amount_to == existingTransaction.config.amount_to) {
                    similarityCount++;
                }
                if (transaction.config.account_from?.id == existingTransaction.config.account_from.id) {
                    similarityCount++;
                }
                if (transaction.config.account_to?.id == existingTransaction.config.account_to.id) {
                    similarityCount++;
                }

                if (similarityCount / maxSimilarity > 0.5) {
                    transaction.similarTransactions.push(Object.assign({similarityScore: similarityCount / maxSimilarity}, existingTransaction));
                }
            });

            // Loop the array of schedules to find matches
            transaction.relatedSchedules = [];
            window.schedules.forEach(function (schedule) {
                // Calculate similarity between transactions using amount and accounts

                // Transaction types must match
                if (transaction.transaction_type.name !== schedule.transaction_type.name) {
                    return;
                }

                // Other fields count towards similarity
                let similarityCount = 0;
                const maxSimilarity = 4;

                if (transaction.date.isoDateString() === schedule.schedule_config.next_date.isoDateString()) {
                    similarityCount++;
                }
                if (transaction.config.amount_to == schedule.config.amount_to) {
                    similarityCount++;
                }
                if (transaction.config.account_from && transaction.config.account_from.id == schedule.config.account_from.id) {
                    similarityCount++;
                }
                if (transaction.config.account_to && transaction.config.account_to.id == schedule.config.account_to.id) {
                    similarityCount++;
                }

                if (similarityCount / maxSimilarity > 0.5) {
                    transaction.relatedSchedules.push(Object.assign({similarityScore: similarityCount / maxSimilarity}, schedule));
                }
            });

            return transaction;
        })
    })
    .finally(() => {
        table.clear().rows.add(transactions).draw();
    })
}

// Function to refill the unmatched rows table
function refillUnmatchedRows(data) {
    let head = document.getElementById('unmatched_table_head');
    let body = document.getElementById('unmatched_table_body');

    // Reset head and body
    head.innerHTML = '';
    body.innerHTML = '';

    if (data.length === 0) {
        // No unmatched rows
        head.innerHTML = '<th>No unmatched rows</th>';
    }

    // Add headings
    let headers = Object.keys(data[0])
    let headerRow = document.createElement('tr');
    headers.forEach(headerText => {
        let header = document.createElement('th');
        let textNode = document.createTextNode(headerText);
        header.appendChild(textNode);
        headerRow.appendChild(header);
    });
    head.appendChild(headerRow);

    // Add rows
    data.forEach(emp => {
        let row = document.createElement('tr');
        Object.values(emp).forEach(text => {
            let cell = document.createElement('td');
            let textNode = document.createTextNode(text);
            cell.appendChild(textNode);
            row.appendChild(cell);
        })
        body.appendChild(row);
    });
}

// Select 2 functionality for account select
$('#account').select2({
    multiple: false,
    ajax: {
        url: '/api/assets/account',
        dataType: 'json',
        delay: 150,
        data: function (params) {
            return {
                q: params.term,
            };
        },
        processResults: function (data) {
            return {
                results: data.map(function(account) {
                    return {
                        id: account.id,
                        text: account.name,
                    }
                }),
            };
        },
        cache: true
    },
    selectOnClose: false,
    placeholder: "Select account",
    allowClear: true
})
.on('select2:select', function (e) {
    $.ajax({
        url:  '/api/assets/account/' + e.params.data.id,
        data: {
            _token: csrfToken,
        }
    })
    .done(data => {
        window.account_currency = data.config.currency;

        // Enable the file input
        document.getElementById('csv_file').disabled = false;
    });
})
.on('select2:unselect', function (e) {
    window.account_currency = {};

    // Disable the file input
    document.getElementById('csv_file').disabled = true;
});

window.table = $("#dataTable").DataTable({
    data: window.transactions,
    columns: [
        {
            data: "date",
            title: 'Date',
            render: function (data) {
                if (!data) {
                    return data;
                }
                return data.toLocaleDateString(window.YAFFA.locale);
            },
            className: "dt-nowrap",
        },
        {
            title: 'Type',
            render: function (_data, _type, row) {
                return dataTableHelpers.transactionTypeIcon(row.transaction_config_type, row.transaction_type.name);
            },
            className: "text-center",
        },
        {
            title: 'From',
            render: function (_data, _type, row) {
                if (row.config && row.config.account_from) {
                    return row.config.account_from.name;
                }

                return 'Not set';
            }
        },
        {
            title: 'To',
            render: function (_data, _type, row) {
                if (row.config && row.config.account_to) {
                    return row.config.account_to.name;
                }

                return 'Not set';
            }
        },
        {
            title: 'Default category',
            render: function (_data, _type, row) {
                // No default category for transfers
                if (row.transaction_type.name === 'transfer') {
                    return 'Not applicable';
                }

                // Set the relevant account type based on the transaction type
                const accountType = row.transaction_type.name === 'deposit' ? 'account_from' : 'account_to';
                // Check if payee is set
                if (!row.config[accountType]) {
                    return 'Not set';
                }

                // Check if default category is set for the payee
                if (!row.config[accountType].config.category) {
                    return 'Not set';
                }

                return row.config[accountType].config.category.full_name;
            },
            orderable: false
        },
        {
            title: 'Amount',
            render: function (_data, _type, row) {
                if (!row.config.amount_to) {
                    return 'Not set';
                }
                let prefix = '';
                if (row.transaction_type.amount_operator == 'minus') {
                    prefix = '- ';
                }
                if (row.transaction_type.amount_operator == 'plus') {
                    prefix = '+ ';
                }
                return prefix + toFormattedCurrency(row.config.amount_to, window.YAFFA.locale, window.account_currency);
            },
            className: "dt-nowrap",
        },
        {
            title: "Comment",
            data: "comment",
            render: function (data) {
                // Empty
                if (!data) {
                    return 'Not set';
                }

                return data;
            },
        },
        {
            title: 'Similar transactions',
            data: 'similarTransactions',
            render: function (data, type) {
                if (type === 'filter') {
                    return (data && data.length > 0) ? 'Yes' : 'No';
                }

                // Display

                // Initial unset value
                if (data === false) {
                    return '<i class="fa fa-spinner fa-spin"></i>';
                }

                if (!data || data.length === 0) {
                    return 'Not found';
                }

                var html = '';
                data.forEach(function (similarTransaction) {
                    html += '<button class="btn btn-sm ' + (similarTransaction.similarityScore === 1 ? 'btn-success' : 'btn-warning') + ' transaction-similar transaction-basic transaction-quickview" data-id="' + similarTransaction.id + '" type="button"><i class="fa fa-fw fa-eye" title="Quick view"></i></button> ';
                })

                return html;
            }
        },
        {
            title: 'Related schedules',
            data: 'relatedSchedules',
            render: function (data, type, row) {
                if (type === 'filter') {
                    return (data && data.length > 0) ? 'Yes' : 'No';
                }

                // Display

                // Initial unset value
                if (data === false) {
                    return '<i class="fa fa-spinner fa-spin"></i>';
                }

                if (!data || data.length === 0) {
                    return 'Not found';
                }

                var html = '';
                data.forEach(function (relatedTransaction) {
                    html += '<button class="btn btn-sm ' + (relatedTransaction.similarityScore === 1 ? 'btn-success' : 'btn-warning') + ' transaction-related transaction-quickview" data-draft="' + row.draftId + '" data-id="' + relatedTransaction.id + '" type="button"><i class="fa fa-fw fa-eye" title="Quick view"></i></button> ';
                })

                return html;
            }
        },
        {
            title: 'Handled',
            data: 'handled',
            render: function (data, type) {
                return dataTableHelpers.booleanToTableIcon(data, type);
            }
        },
        {
            title: "Actions",
            data: 'draftId',
            orderable: false,
            render: function (data, _type, row) {
                return '<button class="btn btn-sm btn-primary create-transaction-from-draft" data-draft="' + data + '" type="button"><i class="fa fa-fw fa-plus" title="Quick create"></i></button> ' +
                       (row.quickRecordingPossible ? '<button class="btn btn-sm btn-success record" data-draft="' + data + '" type="button"><i class="fa fa-fw fa-bolt" title="Crete from existing values"></i></button> ' : '') +
                       '<button class="btn btn-sm btn-info handled" data-draft="' + data + '" type="button"><i class="fa fa-fw fa-check" title="Mark as handled"></i></button> ';
            }
        }
    ],
    createdRow: function(row, data) {
        // Account from name
        dataTableHelpers.muteCellWithValue($('td:eq(2)', row), 'Not set');
        // Account to name
        dataTableHelpers.muteCellWithValue($('td:eq(3)', row), 'Not set');
        // Default category
        dataTableHelpers.muteCellWithValue($('td:eq(4)', row), 'Not set');
        dataTableHelpers.muteCellWithValue($('td:eq(4)', row), 'Not applicable');
        // Comment
        if (!data.comment) {
            $('td:eq(6)', row).addClass("text-muted text-italic");
        }
        //Similar transactions
        dataTableHelpers.muteCellWithValue($('td:eq(7)', row), 'Not found');
        //Related schedules
        dataTableHelpers.muteCellWithValue($('td:eq(8)', row), 'Not found');

    },
    // Apply initial filters
    initComplete: function () {
        // Initially filter by handled
        $('#dataTable').DataTable().column(9).search('No').draw();
    }
});

// Set up event listener that stores the currently selected transaction and dispatches an event
$('#dataTable').on('click', 'button.create-transaction-from-draft', function () {
    // TODO: should this data passed back and forth instead of storing it?
    recentTransactionDraftId = $(this).data('draft');

    // Retrieve the transaction draft based on stored draft ID
    const draft = window.transactions.find(transaction => transaction.draftId == recentTransactionDraftId);
    const transaction = Object.assign({}, draft);

    // Some transformations
    // TODO: these variations should be unified at source
    transaction.transaction_items = [];
    // Should not cause any problems, but does not needed for the form either
    delete transaction.similarTransactions;
    delete transaction.relatedSchedules;


    // Dispatch event
    const event = new CustomEvent('initiateCreateFromDraft', {
        detail: {
            transaction: transaction
        }
    });
    window.dispatchEvent(event);
});

// Quick view for similar transactions
// Initiate display, without any actions
$('#dataTable').on('click', 'button.transaction-similar.transaction-basic.transaction-quickview', function () {
    let icon = this.querySelector('i');
    // If spinner is displayed, do not initiate another request
    if (icon.classList.contains("fa-spinner")) {
        return false;
    }

    const originalIconClass = icon.className;
    icon.className = "fa fa-fw fa-spin fa-spinner";

    fetch('/api/transaction/' + this.dataset.id)
    .then(function(response) {
        if (!response.ok) {
            throw Error(response.statusText);
        }
        return response;
    }).then(response => response.json())
    .then(function(data) {
        let transaction = data.transaction;

        // Convert dates to Date objects
        if (transaction.date) {
            transaction.date = new Date(transaction.date);
        }
        if (transaction.transaction_schedule) {
            if (transaction.transaction_schedule.start_date) {
                transaction.transaction_schedule.start_date = new Date(transaction.transaction_schedule.start_date);
            }
            if (transaction.transaction_schedule.end_date) {
                transaction.transaction_schedule.end_date = new Date(transaction.transaction_schedule.end_date);
            }
            if (transaction.transaction_schedule.next_date) {
                transaction.transaction_schedule.next_date = new Date(transaction.transaction_schedule.next_date);
            }
        }

        // Emit global event for modal to display
        let event = new CustomEvent('showTransactionQuickviewModal', {
            detail: {
                transaction: transaction,
                controls: {
                    show: false,
                    edit: false,
                    clone: false,
                    skip: false,
                    enter: false,
                    delete: false,
                }
            }
        });
        window.dispatchEvent(event);
    })
    .catch((error) => {
        console.log(error);
    })
    .finally(() => {
        icon.className = originalIconClass;
    });
});

// Quick view for related schedules
// Initiate display and store draft id
// TODO: unify functionality with similar transaction display
$('#dataTable').on('click', 'button.transaction-related.transaction-quickview', function () {
    window.recentTransactionDraftId = $(this).data('draft');

    let icon = this.querySelector('i');
    // If spinner is displayed, do not initiate another request
    if (icon.classList.contains("fa-spinner")) {
        return false;
    }

    const originalIconClass = icon.className;
    icon.className = "fa fa-fw fa-spin fa-spinner";

    fetch('/api/transaction/' + this.dataset.id)
    .then(function(response) {
        if (!response.ok) {
            throw Error(response.statusText);
        }
        return response;
    }).then(response => response.json())
    .then(function(data) {
        let transaction = data.transaction;

        // Convert dates to Date objects
        if (transaction.date) {
            transaction.date = new Date(transaction.date);
        }
        if (transaction.transaction_schedule) {
            if (transaction.transaction_schedule.start_date) {
                transaction.transaction_schedule.start_date = new Date(transaction.transaction_schedule.start_date);
            }
            if (transaction.transaction_schedule.end_date) {
                transaction.transaction_schedule.end_date = new Date(transaction.transaction_schedule.end_date);
            }
            if (transaction.transaction_schedule.next_date) {
                transaction.transaction_schedule.next_date = new Date(transaction.transaction_schedule.next_date);
            }
        }

        // Emit global event for modal to display
        let event = new CustomEvent('showTransactionQuickviewModal', {
            detail: {
                transaction: transaction,
                controls: {
                    show: false,
                    edit: false,
                    clone: false,
                    skip: true,
                    enter: true,
                    delete: false,
                }
            }
        });
        window.dispatchEvent(event);
    })
    .catch((error) => {
        console.log(error);
    })
    .finally(() => {
        icon.className = originalIconClass;
    });
});

// Set up an event listener for the recently created transaction
window.addEventListener('transaction-created', function (event) {
    // Add the newly created transaction as a similar transaction to the current one
    let transaction = window.transactions.find(transaction => transaction.draftId == recentTransactionDraftId)
    transaction.similarTransactions.push(event.detail.transaction);

    // Also mark the transaction as being handled by the user
    transaction.handled = true;

    // Update the table
    window.table.clear().rows.add(window.transactions).draw();
});

// Set up an event listener for immediately creating a transaction
$('#dataTable').on('click', 'button.record', function () {
    recentTransactionDraftId = $(this).data('draft');
    // TODO: Disable all the action buttons of this item

    let transaction = window.transactions.find(transaction => transaction.draftId == $(this).data('draft'));

    // Further data preparation
    transaction.action = 'create';
    transaction.config_type = 'transaction_detail_standard';
    transaction.items = [];
    transaction.fromModal = true;
    transaction.config.account_from_id = transaction.config.account_from.id;
    transaction.config.account_to_id = transaction.config.account_to.id;

    // If default category is set, use it as remaining payee default amount
    if (transaction.config.account_to?.config.category) {
        transaction.remaining_payee_default_amount = transaction.amount;
        transaction.remaining_payee_default_category_id = transaction.config.account_to.config.category.id;
    }

    // Call the backend to create the transaction
    const url = route('api.transactions.storeStandard');
    fetch(url, {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': window.csrfToken,
        },
        body: JSON.stringify(transaction)
    })
    .then((response) => {
        if (response.statusText !== 'OK') {
            throw new Error(response.statusText);
        }

        response.json()
    })
    .then((data) => {
        // Get the new transaction from the response
        let transaction = data.transaction;

        // TODO: This should be unified with the same modal behavior
        // Emit a custom event to global scope about the new transaction to be displayed as a notification
        let notificationEvent = new CustomEvent('notification', {
            detail: {
                notification: {
                    type: 'success',
                    message: 'Transaction added (#' + transaction.id + ')',
                    title: null,
                    icon: null,
                    dismissible: true,
                }
            },
        });
        window.dispatchEvent(notificationEvent);

        // Emit a custom event about the new transaction to be displayed
        let transactionEvent = new CustomEvent('transaction-created', {
            detail: {
                // Pass the entire transaction object to the event
                transaction: transaction,
            }
        });
        window.dispatchEvent(transactionEvent);
    })
    .finally(() => {
        // TODO: Re-enable all the action buttons of this item
    })
    .catch(error => {
        console.error(error);
    });
});

// Event listener for marking a transaction as handled
$('#dataTable').on('click', 'button.handled', function () {
    let transactionId = $(this).data('draft');
    let transaction = window.transactions.find(transaction => transaction.draftId == transactionId);
    transaction.handled = true;
    window.table.clear().rows.add(window.transactions).draw();

    // Remove this button from the table
    $(this).remove();
});

// Set up filtering
$('input[name=has_similar]').on("change", function() {
    table.column(7).search(this.value).draw();
});
$('input[name=handled]').on("change", function() {
    table.column(9).search(this.value).draw();
});

// Form reset functionality
$('#reset').on('click', function () {
    // Confirm the reset
    if (!confirm('Are you sure you want to reset the form?')) {
        return;
    }

    // Reset select2
    $('#account').val(null).trigger('change');

    // Reset file input and make it disabled
    $('#csv_file').val(null);
    $('#csv_file').prop('disabled', true);

    // Reset global variables
    window.recentTransactionDraftId = null;
    window.transactions = [];
    window.account_currency = {};
    window.unmatchedRows = [];

    // Reset the main DataTable
    table.clear().rows.add(transactions).draw();

    // Reset the unmatched table header and body
    document.getElementById('unmatched_table_head').innerHTML = '';
    document.getElementById('unmatched_table_body').innerHTML = '';
});

// Load active schedules via API
fetch('/api/transactions/get_scheduled_items/schedule')
.then(response => response.json())
.then(data => {
    window.schedules = data.transactions
    // Take only standard transaction (ignore investments)
    .filter(transaction => transaction.transaction_config_type === 'standard')
    // Take only transactions with a next date
    .filter(transaction => transaction.schedule_config.next_date)
    .map(function(transaction) {
        transaction.schedule_config.start_date = new Date(transaction.schedule_config.start_date);
        if (transaction.schedule_config.next_date) {
            transaction.schedule_config.next_date = new Date(transaction.schedule_config.next_date);
        }
        if (transaction.schedule_config.end_date) {
            transaction.schedule_config.end_date = new Date(transaction.schedule_config.end_date);
        }

        // Create rule
        transaction.schedule_config.rule = new RRule({
            freq: RRule[transaction.schedule_config.frequency],
            interval: transaction.schedule_config.interval,
            dtstart: transaction.schedule_config.start_date,
            until: transaction.schedule_config.end_date,
        });

        transaction.schedule_config.active = !!transaction.schedule_config.rule.after(new Date(), true);

        return transaction;
    })
    .filter(function(transaction) {
        return transaction.schedule_config.active;
    });
})
.catch(error => {
    console.error(error);
});

// Initialize Vue for the quick view
import { createApp } from 'vue'
const app = createApp({})

// Add global translator function
app.config.globalProperties.__ = window.__;

import TransactionShowModal from './../components/TransactionDisplay/Modal.vue'
import TransactionCreateModal from './../components/TransactionForm/ModalStandard.vue'
app.component('transaction-show-modal', TransactionShowModal)
app.component('transaction-create-modal', TransactionCreateModal)

app.mount('#app')
