require('datatables.net-bs5');
require("datatables.net-responsive-bs5");

import * as dataTableHelpers from '../components/dataTableHelper';

import DateRangePicker from 'vanillajs-datepicker/DateRangePicker';

const selectorScheduleTable = '#scheduleTable';
const selectorHistoryTable = '#historyTable';

// Initialize an object which checks if preset filters are populated. This is used to trigger initial dataTable content.
let presetFilters = {
    ready: function() {
        for (let key in presetFilters) {
            if (presetFilters[key] === false) {
                return false;
            }
        }
        return true;
    }
};

// Loop filter object keys and populate presetFilters array.
for (let key in filters) {
    presetFilters[key] = false;
}

// Disable table refresh, if any filters are preset
if (!presetFilters.ready()) {
    document.getElementById('reload').setAttribute('disabled','disabled');
}

// Initialize date range picker
const dateRangePicker = new DateRangePicker(
    document.getElementById('dateRangePicker'),
    {
        allowOneSidedRange: true,
        weekStart: 1,
        todayBtn: true,
        todayBtnMode: 1,
        todayHighlight: true,
        language: window.YAFFA.language,
        format: 'yyyy-mm-dd',
        autohide: true,
        buttonClass: 'btn',
    }
);

let initialLoad = true;

var dtHistory = $(selectorHistoryTable).DataTable({
    ajax: function (_data, callback, _settings) {
        if (initialLoad) {
            initialLoad = false;
            callback({data: []}); // Don't fire ajax, just return empty set
            return;
        }

        const dates = dateRangePicker.getDates('yyyy-mm-dd');
        const params = new URLSearchParams();
        if (dates[0]) {
            params.append('date_from', dates[0]);
        }
        if (dates[1]) {
            params.append('date_to', dates[1]);
        }
        params.append('accounts[]', account.id);

        // Ajax will now only fire programmatically, via ajax.reload()
        fetch(
            '/api/transactions?' + params,
            {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': window.csrfToken,
                },
            }
        )
        .then((response) => response.json())
        .then((data) => {
            let result = data.data
            .map(function(transaction) {
                transaction.date = new Date(transaction.date);

                return transaction;
            });

            callback({data: result});
        });
    },
    columns: [
        dataTableHelpers.transactionColumnDefiniton.dateFromCustomField('date', __('Date'), window.YAFFA.locale),
        {
            data: "reconciled",
            title: '<span title="' + __('Reconciled') + '">R</span>',
            className: "text-center",
            render: function (_data, type, row) {
                if (type === 'filter') {
                    return (!row.schedule
                        && (row.transaction_type.type === 'standard' || row.transaction_type.type === 'investment')
                        ? (row.reconciled == 1
                            ? __('Reconciled')
                            : __('Uncleared')
                        )
                        : __('Unavailable')
                    );
                }
                return (!row.schedule
                    && (row.transaction_type.type === 'standard' || row.transaction_type.type === 'investment')
                    ? (row.reconciled == 1
                        ? '<i class="fa fa-check-circle text-success reconcile" data-reconciled="true" data-id="' + row.id + '"></i>'
                        : '<i class="fa fa-circle text-info reconcile" data-reconciled="false" data-id="' + row.id + '"></i>'
                    )
                    : '<i class="fa fa-circle text-muted"></i>'
                );
            },
            orderable: false,
        },
        dataTableHelpers.transactionColumnDefiniton.payee,
        dataTableHelpers.transactionColumnDefiniton.category,
        dataTableHelpers.transactionColumnDefiniton.amount,
        dataTableHelpers.transactionColumnDefiniton.comment,
        dataTableHelpers.transactionColumnDefiniton.tags,
        {
            data: 'id',
            title: __("Actions"),
            render: function (data, _type, row) {
                if (row.transaction_type.type === 'standard') {
                    return dataTableHelpers.dataTablesActionButton(data, 'standardQuickView') +
                           dataTableHelpers.dataTablesActionButton(data, 'standardShow') +
                           dataTableHelpers.dataTablesActionButton(data, 'edit', 'standard') +
                           dataTableHelpers.dataTablesActionButton(data, 'clone', 'standard') +
                           dataTableHelpers.dataTablesActionButton(data, 'delete');
                }

                // Investment
                return '<a href="' + route('transactions.open.investment', { transaction: data, action: 'edit' }) +  '" class="btn btn-xs btn-primary" title="' + __('Edit')  + '"><i class="fa fa-fw fa-edit"></i></a> ' +
                       '<a href="' + route('transactions.open.investment', { transaction: data, action: 'clone' }) + '" class="btn btn-xs btn-primary" title="' + __('Clone') + '"><i class="fa fa-fw fa-clone"></i></a> ' +
                       dataTableHelpers.dataTablesActionButton(data, 'delete');
            },
            className: "dt-nowrap",
            orderable: false,
            searchable: false,
        }
    ],
    createdRow: function (row, data) {
        if (data.schedule) {
            $(row).addClass('text-muted text-italic');
        }
    },
    initComplete: function() {
        // Get the Datatable API instance
        var api = this.api();
        setTimeout(function() {
            api.columns.adjust().draw();
        }, 2000);
    },
    order: [
        [0, "asc"]
    ],
    responsive: true,
    deferRender: true,
    scrollY: '400px',
    scrollCollapse: true,
    scroller: true,
    stateSave: false,
    processing: true,
    paging: false,
});

var dtSchedule = $(selectorScheduleTable).DataTable({
    ajax: {
        url: '/api/transactions/get_scheduled_items/schedule?account=' + account.id,
        type: 'GET',
        dataSrc: function(data) {
            return data.transactions
            .map(function(transaction) {
                transaction.schedule_config.start_date = new Date(transaction.schedule_config.start_date);
                if (transaction.schedule_config.next_date) {
                    transaction.schedule_config.next_date = new Date(transaction.schedule_config.next_date);
                }
                if (transaction.schedule_config.end_date) {
                    transaction.schedule_config.end_date = new Date(transaction.schedule_config.end_date);
                }

                return transaction;
            })
            .filter(transaction => transaction.schedule_config.next_date);
        },
        deferRender: true
    },
    columns: [
        dataTableHelpers.transactionColumnDefiniton.dateFromCustomField('schedule_config.next_date', __('Next date'), window.YAFFA.locale),
        dataTableHelpers.transactionColumnDefiniton.payee,
        dataTableHelpers.transactionColumnDefiniton.category,
        dataTableHelpers.transactionColumnDefiniton.amount,
        dataTableHelpers.transactionColumnDefiniton.comment,
        dataTableHelpers.transactionColumnDefiniton.tags,
        {
            data: 'id',
            title: __("Actions"),
            defaultContent: '',
            render: function (data, _type, row) {
                return  '<button class="btn btn-xs btn-success create-transaction-from-draft" data-draft="' + data + '" type="button" title="' + __('Adjust and enter instance') + '"><i class="fa fa-fw fa-pencil"></i></button> ' +
                        // TODO '<button class="btn btn-xs btn-success record" data-draft="' + data + '" type="button"><i class="fa fa-fw fa-bolt" title="Crete from existing values"></i></button> ' +
                        dataTableHelpers.dataTablesActionButton(data, 'skip') +
                        dataTableHelpers.dataTablesActionButton(data, 'edit', row.transaction_type.type) +
                        dataTableHelpers.dataTablesActionButton(data, 'clone', row.transaction_type.type) +
                        dataTableHelpers.dataTablesActionButton(data, 'replace', row.transaction_type.type) +
                        dataTableHelpers.dataTablesActionButton(data, 'delete');
            },
            className: "dt-nowrap",
            orderable: false,
            searchable: false,
        }
    ],
    createdRow: function (row, data) {
        // This data is required, but just to be on the safe side, let's validate it
        if (!data.schedule_config.next_date) {
            return;
        }

        if (data.schedule_config.next_date  < new Date(new Date().setHours(0,0,0,0)) ) {
            $(row).addClass('table-danger');
        } else if (data.schedule_config.next_date  < new Date(new Date().setHours(24,0,0,0)) ) {
            $(row).addClass('table-warning');
        }
    },
        order: [
        // Next date is the first column
        [ 0, "asc" ]
    ],
    responsive: true,
    deferRender:    true,
    scrollY:        '500px',
    scrollCollapse: true,
    scroller:       true,
    stateSave:      false,
    processing:     true,
    paging:         false,
});

dataTableHelpers.initializeQuickViewButton(selectorHistoryTable);

// Skip instance via API
$(selectorScheduleTable).on("click", "[data-skip]", function() {
    // Prevent running multiple times in parallel
    if ($(this).hasClass("busy")) {
        return false;
    }

    let id = this.dataset.id;

    $(this).addClass('busy');

    axios.patch('/api/transactions/' + id + '/skip')
    .then(function (response) {
        // Find and update original row in schedule table
        var row = $(selectorScheduleTable).dataTable().api().row(function (_idx, data, _node) {
            return data.id == id;
        });

        var data = row.data();
        var newNextDate = response.data.transaction.schedule_config.next_date;
        // If next date exists, update the row. Otherwise remove it.
        if (newNextDate) {
            data.schedule_config.next_date = new Date(newNextDate);
            row.data(data).draw();

            // Emit a custom event to global scope about the result
            let notificationEvent = new CustomEvent('notification', {
                detail: {
                    notification: {
                        type: 'success',
                        message: 'Schedule instance skipped (#' + id + ')',
                        title: null,
                        icon: null,
                        dismissible: true,
                    }
                },
            });
        window.dispatchEvent(notificationEvent);
        } else {
            row.remove().draw();

            // Emit a custom event to global scope about the result
            let notificationEvent = new CustomEvent('notification', {
                detail: {
                    notification: {
                        type: 'success',
                        message: 'Schedule instance skipped. (#' + id + '). This schedule has ended.',
                        title: null,
                        icon: null,
                        dismissible: true,
                    }
                },
            });
            window.dispatchEvent(notificationEvent);
        }

        // The redraw will also remove the busy class
        // TODO: is this reliable, or should there be an other flag, which needs to be reset manually?
    });
});

// Delete instance via API
dataTableHelpers.initializeAjaxDeleteButton(selectorScheduleTable);
dataTableHelpers.initializeAjaxDeleteButton(selectorHistoryTable);

// Reconciled button listener
$(selectorHistoryTable).on("click", "i.reconcile", function () {
    if ($(this).hasClass("fa-spinner")) {
        return false;
    }

    var currentState = $(this).data("reconciled");
    var currentId = $(this).data("id");

    $(this).removeClass().addClass('fa fa-spinner fa-spin');

    $.ajax({
        type: 'PUT',
        url: '/api/transaction/' + currentId + '/reconciled/' + (!currentState ? 1 : 0),
        data: {
            "_token": csrfToken,
        },
        dataType: "json",
        context: this,
        success: function (_data) {
            var row = $(selectorHistoryTable).dataTable().api().row(function ( _idx, data, _node ) {
                return data.id == currentId
            });
            var data = row.data()

            data.reconciled = !currentState;

            row.data(data).draw();

        }
    });
});

// Reconciled flag search buttons
$('input[name=reconciled]').on("change", function () {
    $(selectorHistoryTable).DataTable().column(1).search(this.value).draw();
});

// Function to reload table data
function reloadTable() {
    document.getElementById('reload').setAttribute('disabled','disabled');
    dtHistory.ajax.reload(function() {
        document.getElementById('reload').removeAttribute('disabled');

        // (Re-)Initialize tooltips in table
        $('[data-toggle="tooltip"]').tooltip();
    });
}

// Reload button functionality
$("#reload").on('click', reloadTable);

$("#clear_dates").on('click', function() {
    dateRangePicker.setDates(
        {clear: true},
        {clear: true}
    );
})

// Set initial dates
if (filters.date_from || filters.date_to) {
    const start = (filters.date_from ? filters.date_from : {clear: true});
    const end = (filters.date_to ? filters.date_to : {clear: true});

    dateRangePicker.setDates(
        start,
        end
    );

    presetFilters.date_from = true;
    presetFilters.date_to = true;
    // If all preset filters are ready, reload table data
    if (presetFilters.ready()) {
        reloadTable();
    }
}

// Set up event listener for new transaction button
$('#create-standard-transaction-button').on('click', function () {
    // TODO: should this data passed back and forth instead of storing it?
    recentTransactionDraftId = undefined;

    // Create transactiuon daft
    const transaction = {};

    transaction.transaction_type = 'withdrawal';
    transaction.schedule = false;
    transaction.budget = false;
    transaction.date = new Date();
    transaction.config = {};
    transaction.config.account_from_id = account.id;

    // Dispatch event
    const event = new CustomEvent('initiateCreateFromDraft', {
        detail: {
            transaction: transaction
        }
    });
    window.dispatchEvent(event);
});

// The following variable is used to store the current transaction being created.
let recentTransactionDraftId;

// Set up event listener that stores the currently selected transaction and dispatches an event
$(selectorScheduleTable).on('click', 'button.create-transaction-from-draft', function () {
    // TODO: should this data passed back and forth instead of storing it?
    recentTransactionDraftId = $(this).data('draft');

    var transactions = [];
    $(selectorScheduleTable).dataTable().api().data().each(function(d) { transactions.push(d)});

    // Retrieve the transaction draft based on stored (draft) ID
    const draft = transactions.find(transaction => transaction.id == recentTransactionDraftId);
    const transaction = Object.assign({}, draft);

    // TODO: can this transaction type conversion be generalized elsewhere?
    transaction.transaction_type = transaction.transaction_type.name;
    transaction.config_type = 'transaction_detail_standard';
    transaction.schedule = false;
    transaction.budget = false;
    transaction.date = transaction.schedule_config.next_date;

    // Dispatch event
    const event = new CustomEvent('initiateEnterInstance', {
        detail: {
            transaction: transaction
        }
    });
    window.dispatchEvent(event);
});

// Set up an event listener for the recently created transaction
window.addEventListener('transaction-created', function (event) {
    // Transform incoming data
    let transaction = event.detail.transaction;
    transaction.date = new Date(transaction.date);

    // Add the newly created transaction to the history table, regardless if the date range and account matches
    dtHistory.row.add(transaction).draw();

    // Adjust columns
    setTimeout(function() {
        dtHistory.columns.adjust().draw();
    }, 2000);

    // Adjust the next date of the original scheduled item, accounting for a completed schedule
    console.log(recentTransactionDraftId)
});


// Set up an event listener for immediately creating a transaction
window.scheduleTable = $(selectorScheduleTable).on('click', 'button.record', function () {
    recentTransactionDraftId = $(this).data('draft');
    // TODO: Disable all the action buttons of this item

    var transactions = [];
    $(selectorScheduleTable).dataTable().api().data().each(function(d) { transactions.push(d)});

    let transaction = transactions.find(transaction => transaction.id == $(this).data('draft'));

    // Further data preparation
    transaction.action = 'enter';
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

// Listener for the date range presets
document.getElementById('dateRangePickerPresets').addEventListener('change', function(event) {
    const preset = this.options[this.selectedIndex].value;
    const date = new Date();
    let start;
    let end;
    let quarter;

    switch(preset) {
        case 'thisMonth':
            start = new Date(date.getFullYear(), date.getMonth(), 1);
            end = new Date(date.getFullYear(), date.getMonth() + 1, 0);
            break;
        case 'thisQuarter':
            quarter = Math.floor((date.getMonth() + 3) / 3);
            start = new Date(date.getFullYear(), (quarter - 1) * 3, 1);
            end = new Date(date.getFullYear(), quarter * 3, 0);
            break;
        case 'thisYear':
            start = new Date(date.getFullYear(), 0, 1);
            end = new Date(date.getFullYear(), 12, 0);
            break;
        case 'thisMonthToDate':
            start = new Date(date.getFullYear(), date.getMonth(), 1);
            end = date;
            break;
        case 'thisQuarterToDate':
            quarter = Math.floor((date.getMonth() + 3) / 3);
            start = new Date(date.getFullYear(), (quarter - 1) * 3, 1);
            end = date;
            break;
        case 'thisYearToDate':
            start = new Date(date.getFullYear(), 0, 1);
            end = date;
            break;
        case 'previousMonth':
            start = new Date(date.getFullYear(), date.getMonth() - 1, 1);
            end = new Date(date.getFullYear(), date.getMonth(), 0);
            break;
        default:
            start = {clear: true};
            end = {clear: true};
    }

    dateRangePicker.setDates(
        start,
        end
    );

});

// Attach event listener to filters
let rebuildUrl = function () {
    let params = [];

    const dates = dateRangePicker.getDates('yyyy-mm-dd');
    // Date from
    if (dates[0]) {
        params.push('date_from=' + dates[0]);
    }

    // Date to
    if (dates[1]) {
        params.push('date_to=' + dates[1]);
    }

    window.history.pushState('', '', window.location.origin + window.location.pathname + '?' + params.join('&'));
}

document.getElementById('date_from').addEventListener('changeDate', rebuildUrl);
document.getElementById('date_to').addEventListener('changeDate', rebuildUrl);

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
