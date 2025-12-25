import 'datatables.net-bs5';
import "datatables.net-responsive-bs5";

import * as dataTableHelpers from '../components/dataTableHelper';
import * as helpers from '../helpers';

import DateRangePicker from 'vanillajs-datepicker/DateRangePicker';

import presetCalculators from '../presetDates';

const selectorScheduleTable = '#scheduleTable';
const selectorHistoryTable = '#historyTable';

// Initialize an object which checks if preset filters are populated. This is used to trigger initial dataTable content.
let presetFilters = {
    ready: function () {
        for (let key in presetFilters) {
            if (presetFilters[key] === false) {
                return false;
            }
        }
        return true;
    }
};

// Loop filter object keys and populate presetFilters array
for (let key in window.filters) {
    // For the date range preset, treat 'none' as not preset
    if (key === 'date_preset' && window.filters[key] === 'none') {
        continue;
    }
    presetFilters[key] = false;
}

// Disable table refresh, if any filters are preset
if (!presetFilters.ready()) {
    document.getElementById('reload').setAttribute('disabled', 'disabled');
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

/**
 * Helper function to get adjusted cash flow in the context of the current account
 * @param transaction
 * @property {string} transaction.config_type
 * @property {number} transaction.cashflow_value
 * @return {*}
 */
const processTransaction = function (transaction) {
    if (transaction.config_type === 'standard') {
        // If the cashflow value is a number, use it
        if (typeof transaction.cashflow_value === 'number') {
            transaction.current_cash_flow = transaction.cashflow_value;
        } else {
            // Otherwise this is a transfer, and we need to decide based on the input account
            if (transaction.config.account_from_id === window.account.id) {
                transaction.current_cash_flow = -transaction.config.amount_from;
            } else {
                transaction.current_cash_flow = transaction.config.amount_to;
            }
        }
    } else if (transaction.config_type === 'investment') {
        transaction.current_cash_flow = transaction.cashflow_value ?? 0;
    }
    return transaction;
};

let initialLoad = true;
let isPresetChange = false;

let dtHistory = $(selectorHistoryTable).DataTable({
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
                    .map(helpers.processTransaction)
                    .map(processTransaction);

                callback({data: result});
            });
    },
    columns: [
        dataTableHelpers.transactionColumnDefinition.dateFromCustomField('date', __('Date'), window.YAFFA.locale),
        {
            data: "reconciled",
            title: '<span title="' + __('Reconciled') + '">R</span>',
            className: "text-center",
            render: function (_data, type, row) {
                if (type === 'filter') {
                    return (!row.schedule
                        && (row.transaction_type.type === 'standard' || row.transaction_type.type === 'investment')
                            ? (row.reconciled ? __('Reconciled') : __('Uncleared'))
                            : __('Unavailable')
                    );
                }
                return (!row.schedule
                    && (row.transaction_type.type === 'standard' || row.transaction_type.type === 'investment')
                        ? (row.reconciled
                                ? '<i class="fa fa-check-circle text-success reconcile" data-reconciled="true" data-id="' + row.id + '"></i>'
                                : '<i class="fa fa-circle text-info reconcile" data-reconciled="false" data-id="' + row.id + '"></i>'
                        )
                        : '<i class="fa fa-circle text-muted"></i>'
                );
            },
            orderable: false,
        },
        dataTableHelpers.transactionColumnDefinition.payee,
        dataTableHelpers.transactionColumnDefinition.category,
        dataTableHelpers.transactionColumnDefinition.amountCustom,
        dataTableHelpers.transactionColumnDefinition.comment,
        dataTableHelpers.transactionColumnDefinition.tags,
        {
            data: 'id',
            title: __("Actions"),
            render: function (data) {
                return dataTableHelpers.dataTablesActionButton(data, 'quickView') +
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
    /**
     * Callback for every row created: row and column specific formatting.
     *
     * @param {Node} row
     * @param {Object} data
     * @property {Number} data.current_cash_flow
     * @returns {void}
     */
    createdRow: function (row, data) {
        // Color coding for the amount column
        if (data.current_cash_flow > 0) {
            $('td', row).eq(4).addClass('text-success');
        } else if (data.current_cash_flow < 0) {
            $('td', row).eq(4).addClass('text-danger');
        }

        // Mute category cell with 'not set' value
        if (data.config_type === 'standard' && data.categories.length === 0) {
            $('td', row).eq(3).addClass('text-muted text-italic');
        }
    },
    initComplete: function () {
        // Get the Datatable API instance
        var api = this.api();
        setTimeout(function () {
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
    stateSave: false,
    processing: true,
    paging: false,
});

let dtSchedule = $(selectorScheduleTable).DataTable({
    ajax: {
        url: '/api/transactions/get_scheduled_items/schedule' +
            '?accountEntity=' + window.account.id +
            '&accountSelection=selected',
        type: 'GET',
        dataSrc: function (data) {
            return data.transactions
                .map(helpers.processTransaction)
                .map(processTransaction)
                .filter(transaction => transaction.transaction_schedule.next_date);
        },
        deferRender: true
    },
    columns: [
        dataTableHelpers.transactionColumnDefinition.dateFromCustomField('transaction_schedule.next_date', __('Next date'), window.YAFFA.locale),
        dataTableHelpers.transactionColumnDefinition.payee,
        dataTableHelpers.transactionColumnDefinition.category,
        dataTableHelpers.transactionColumnDefinition.amountCustom,
        dataTableHelpers.transactionColumnDefinition.comment,
        dataTableHelpers.transactionColumnDefinition.tags,
        {
            data: 'id',
            title: __("Actions"),
            defaultContent: '',
            render: function (data) {
                return '<button class="btn btn-xs btn-success create-transaction-from-draft" data-draft="' + data + '" type="button" title="' + __('Adjust and enter instance') + '"><i class="fa fa-fw fa-pencil"></i></button> ' +
                    dataTableHelpers.dataTablesActionButton(data, 'skip') +
                    dataTableHelpers.dataTablesActionButton(data, 'edit') +
                    dataTableHelpers.dataTablesActionButton(data, 'clone') +
                    dataTableHelpers.dataTablesActionButton(data, 'replace') +
                    dataTableHelpers.dataTablesActionButton(data, 'delete');
            },
            className: "dt-nowrap",
            orderable: false,
            searchable: false,
        }
    ],
    /**
     * Callback for every row created: colorize the next date.
     *
     * @param {Node} row
     * @param {Object} data
     * @property {Object} data.transaction_schedule
     * @property {Date} data.transaction_schedule.next_date
     * @returns {void}
     */
    createdRow: function (row, data) {
        // This data is required, but just to be on the safe side, let's validate it
        if (data.transaction_schedule.next_date) {
            if (data.transaction_schedule.next_date < new Date(new Date().setHours(0, 0, 0, 0))) {
                $(row).addClass('table-danger');
            } else if (data.transaction_schedule.next_date < new Date(new Date().setHours(24, 0, 0, 0))) {
                $(row).addClass('table-warning');
            }
        }

        // Color coding for the amount column
        if (data.current_cash_flow > 0) {
            $('td', row).eq(3).addClass('text-success');
        } else if (data.current_cash_flow < 0) {
            $('td', row).eq(3).addClass('text-danger');
        }

        // Mute category cell with 'not set' value
        if (data.categories.length === 0) {
            $('td', row).eq(2).addClass('text-muted text-italic');
        }
    },
    initComplete: function () {
        // Get the Datatable API instance
        const api = this.api();
        setTimeout(function () {
            api.columns.adjust().draw();
        }, 2000);
    },
    order: [
        // Next date is the first column
        [0, "asc"]
    ],
    responsive: true,
    deferRender: true,
    scrollY: '500px',
    scrollCollapse: true,
    stateSave: false,
    processing: true,
    paging: false,
});

dataTableHelpers.initializeQuickViewButton(selectorHistoryTable);

// Skip instance via API
$(selectorScheduleTable).on("click", "[data-skip]", function () {
    // Prevent running multiple times in parallel
    if ($(this).hasClass("busy")) {
        return false;
    }

    let id = Number(this.dataset.id);

    $(this).addClass('busy');

    axios.patch('/api/transactions/' + id + '/skip')
        .then(function (response) {
            // Find and update original row in schedule table
            let row = $(selectorScheduleTable).dataTable().api().row(function (_idx, data, _node) {
                return Number(data.id) === id;
            });

            let data = row.data();
            let newNextDate = response.data.transaction.transaction_schedule.next_date;
            // If next date exists, update the row. Otherwise remove it.
            if (newNextDate) {
                data.transaction_schedule.next_date = new Date(newNextDate);
                row.data(data).draw();

                // Emit a custom event to global scope about the result
                let notificationEvent = new CustomEvent('toast', {
                    detail: {
                        header: __('Success'),
                        headerSmall: helpers.transactionLink(id, __('Go to transaction')),
                        body: __('Schedule instance skipped.'),
                        toastClass: "bg-success",
                    }
                });
                window.dispatchEvent(notificationEvent);
            } else {
                row.remove().draw();

                // Emit a custom event to global scope about the result
                let notificationEvent = new CustomEvent('toast', {
                    detail: {
                        header: __('Success'),
                        headerSmall: helpers.transactionLink(id, __('Go to transaction')),
                        body: __('Schedule instance skipped. This schedule has ended.'),
                        toastClass: "bg-success",
                    }
                });
                window.dispatchEvent(notificationEvent);
            }

            // The redraw will also remove the busy class
        });
});

// Define and run a function to get the account balance
let getAccountBalance = function () {
    // Get the balance related elements
    let elementOpeningBalance = document.getElementById('overviewOpeningBalance');
    let elementCurrentCash = document.getElementById('overviewCurrentCash');
    let elementCurrentBalance = document.getElementById('overviewCurrentBalance');

    // Ensure that spinner icon is shown for all elements
    elementOpeningBalance.innerHTML =
        elementCurrentCash.innerHTML =
            elementCurrentBalance.innerHTML =
                '<i class="fa fa-fw fa-spinner fa-spin"></i>';

    axios.get('/api/account/balance/' + window.account.id)
        .then(function (response) {
            // Check if the response is valid data
            if (response.data.result === 'busy') {
                elementOpeningBalance.innerHTML =
                    elementCurrentCash.innerHTML =
                        elementCurrentBalance.innerHTML =
                            `<i
                                 class="text-warning fa-solid fa-triangle-exclamation"
                                 title="${response.data.message}"
                         ></i>`;

                setTimeout(getAccountBalance, 5000);
                return;
            }
            let balance = response.data.accountBalanceData[0];

            elementOpeningBalance.innerText = helpers.toFormattedCurrency(
                balance.config.opening_balance,
                window.YAFFA.locale,
                balance.config.currency
            );

            elementCurrentCash.innerText = helpers.toFormattedCurrency(
                balance.cash,
                window.YAFFA.locale,
                window.YAFFA.baseCurrency
            );

            if (balance.hasOwnProperty('cash_foreign')) {
                elementCurrentCash.innerText += ' / ' + helpers.toFormattedCurrency(
                    balance.cash_foreign,
                    window.YAFFA.locale,
                    balance.config.currency
                );
            }

            elementCurrentBalance.innerText = helpers.toFormattedCurrency(
                balance.sum,
                window.YAFFA.locale,
                window.YAFFA.baseCurrency
            );

            if (balance.hasOwnProperty('sum_foreign')) {
                elementCurrentBalance.innerText += ' / ' + helpers.toFormattedCurrency(
                    balance.sum_foreign,
                    window.YAFFA.locale,
                    balance.config.currency
                );
            }
        })
        .catch(function (error) {
            elementOpeningBalance.innerHTML =
                elementCurrentCash.innerHTML =
                    elementCurrentBalance.innerHTML =
                        `<i
                                 class="text-danger fa-solid fa-triangle-exclamation"
                                 title="${__('Error while retrieving data')}"
                         ></i>`;

            let notificationEvent = new CustomEvent('toast', {
                detail: {
                    header: __('Error while fetching account balance data'),
                    body: error.message,
                    toastClass: "bg-danger",
                }
            });
            window.dispatchEvent(notificationEvent);
        })
}
getAccountBalance();

// Delete instance via API
dataTableHelpers.initializeAjaxDeleteButton(selectorHistoryTable, getAccountBalance);
dataTableHelpers.initializeAjaxDeleteButton(selectorScheduleTable);

// Reconciled button listener
$(selectorHistoryTable).on("click", "i.reconcile", function () {
    if ($(this).hasClass("fa-spinner")) {
        return false;
    }

    const currentState = $(this).data("reconciled");
    const currentId = Number($(this).data("id"));

    $(this).removeClass().addClass('fa fa-spinner fa-spin');

    $.ajax({
        type: 'PUT',
        url: '/api/transaction/' + currentId + '/reconciled/' + (currentState ? 0 : 1),
        data: {
            "_token": csrfToken,
        },
        dataType: "json",
        context: this,
        success: function (_data) {
            let row = $(selectorHistoryTable).dataTable().api().row(function (_idx, data, _node) {
                return data.id === currentId
            });
            let data = row.data()

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
    document.getElementById('reload').setAttribute('disabled', 'disabled');
    dtHistory.ajax.reload(function () {
        document.getElementById('reload').removeAttribute('disabled');

        // (Re-)Initialize tooltips in table
        helpers.initializeBootstrapTooltips();
    });
}

// Reload button functionality
$("#reload").on('click', reloadTable);

$("#clear_dates").on('click', function () {
    dateRangePicker.setDates(
        {clear: true},
        {clear: true}
    );
})

// Listener for the date range presets
document.getElementById('dateRangePickerPresets').addEventListener('change', function (_event) {
    isPresetChange = true;
    const preset = this.options[this.selectedIndex].value;
    const date = new Date();
    let start;
    let end;

    // Get the start and end dates based on the selected preset and the external calculator functions
    const calculator = presetCalculators[preset];
    if (calculator) {
        const dates = calculator(date);
        start = dates.start;
        end = dates.end;
    } else {
        start = {clear: true};
        end = {clear: true};
    }

    dateRangePicker.setDates(
        start,
        end
    );

    isPresetChange = false;
});

let rebuildUrl = function () {
    let params = [];

    if (isPresetChange) {
        const preset = document.getElementById('dateRangePickerPresets').value;
        if (preset && preset !== 'none') {
            params.push('date_preset=' + preset);
        }
    } else {

        const dates = dateRangePicker.getDates('yyyy-mm-dd');
        // Date from
        if (dates[0]) {
            params.push('date_from=' + dates[0]);
        }

        // Date to
        if (dates[1]) {
            params.push('date_to=' + dates[1]);
        }
    }

    window.history.pushState('', '', window.location.origin + window.location.pathname + '?' + params.join('&'));
}

// Attach event listener to date pickers
document.getElementById('date_from').addEventListener('changeDate', function() {
    if (!isPresetChange) {
        document.getElementById('dateRangePickerPresets').value = 'none';
    }
    rebuildUrl();
});
document.getElementById('date_to').addEventListener('changeDate', function() {
    if (!isPresetChange) {
        document.getElementById('dateRangePickerPresets').value = 'none';
    }
    rebuildUrl();
});

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
} else if (filters.date_preset && filters.date_preset !== 'none') {
    // If date preset is set, apply it
    document.getElementById('dateRangePickerPresets').value = filters.date_preset;
    const event = new Event('change');
    document.getElementById('dateRangePickerPresets').dispatchEvent(event);

    presetFilters.date_preset = true;
    // If all preset filters are ready, reload table data
    if (presetFilters.ready()) {
        reloadTable();
    }
} else if (filters.date_preset && filters.date_preset === 'none') {
    presetFilters.date_preset = true;
    // At the moment we don't expect any other filters, and the table does not need to be reloaded
}

// Set up event listener for new standard transaction button
$('#create-standard-transaction-button').on('click', function () {
    // TODO: should this data passed back and forth instead of storing it?
    recentTransactionDraftId = undefined;

    // Create transaction daft
    const transaction = {
        transaction_type: {
            name: 'withdrawal'
        },
        schedule: false,
        budget: false,
        date: new Date(),
        config: {
            account_from_id: account.id,
        },
    };

    // Dispatch event
    const event = new CustomEvent('initiateCreateFromDraft', {
        detail: {
            transaction: transaction,
            type: 'standard',
        }
    });
    window.dispatchEvent(event);
});

// The following variable is used to store the current transaction being created.
let recentTransactionDraftId;

// Set up event listener for new investment transaction button
$('#create-investment-transaction-button').on('click', function () {
    // TODO: should this data passed back and forth instead of storing it?
    recentTransactionDraftId = undefined;

    // Create transaction daft
    const transaction = {
        transaction_type: {
            name: 'Buy',
        },
        schedule: false,
        budget: false,
        date: new Date(),
        config: {
            account_id: account.id,
        },
    };

    // Dispatch event
    const event = new CustomEvent('initiateCreateFromDraft', {
        detail: {
            transaction: transaction,
            type: 'investment',
        }
    });
    window.dispatchEvent(event);
});

// Set up event listener that stores the currently selected transaction and dispatches an event
$(selectorScheduleTable).on('click', 'button.create-transaction-from-draft', function () {
    // TODO: should this data be passed back and forth instead of storing it?
    recentTransactionDraftId = Number($(this).data('draft'));

    const draft = dtSchedule.row($(this).parentsUntil('tr')).data();
    const transaction = {...draft};

    // Remove schedule and budget data
    transaction.schedule = false;
    transaction.budget = false;

    // Adjust the date to the next scheduled date
    transaction.date = transaction.transaction_schedule.next_date;

    // Dispatch event
    const event = new CustomEvent('initiateEnterInstance', {
        detail: {
            transaction: transaction,
        }
    });
    window.dispatchEvent(event);
});

// Set up an event listener for the recently created transaction
window.addEventListener('transaction-created', function (event) {
    // Transform incoming data
    let transaction = processTransaction(helpers.processTransaction(event.detail.transaction));
    transaction.date = new Date(transaction.date);

    // Add the newly created transaction to the history table, regardless if the date range and account matches
    dtHistory.row.add(transaction).draw();

    // Reload the account balance with a static delay
    setTimeout(getAccountBalance, 15000);

    // Adjust columns
    setTimeout(function () {
        dtHistory.columns.adjust().draw();
    }, 2000);

    // If the transaction was created from a draft, then adjust the schedule
    if (!recentTransactionDraftId) {
        return;
    }

    // Reload the schedule table
    dtSchedule.ajax.reload();

    // TODO: is there a more efficient way to do this instead of reloading the entire table?
});

// Add event listener for the cache update button
document.getElementById('recalculateMonthlyCachedData').addEventListener('click', function () {
    // Prevent running multiple times in parallel
    if (this.classList.contains("busy")) {
        return false;
    }

    this.classList.add('busy');
    const button = this;

    axios.put(window.route(
        'api.account.updateMonthlySummary',
        {accountEntity: window.account.id}
    ))
        .then(function (response) {
            const data = response.data;
            // Emit a custom event to global scope about the result
            let notificationEvent = new CustomEvent('toast', {
                detail: {
                    header: data.result === 'success' ? __('Success') : __('Error'),
                    body: data.message,
                    toastClass: data.result === 'success' ? 'bg-success' : 'bg-danger',
                }
            });
            window.dispatchEvent(notificationEvent);

            // Reload the account balance with a static delay
            setTimeout(getAccountBalance, 5000);
        })
        .catch(function (error) {
            // Emit a custom event to global scope about the result
            let notificationEvent = new CustomEvent('toast', {
                detail: {
                    header: __('Error'),
                    body: error.message,
                    toastClass: 'bg-danger'
                }
            });
            window.dispatchEvent(notificationEvent);
        })
        .finally(function () {
            button.classList.remove('busy');
        });
});

// Initialize Vue for the quick view
import {createApp} from 'vue'

const app = createApp({})

// Add global translator function
app.config.globalProperties.__ = window.__;

import TransactionShowModal from './../components/TransactionDisplay/Modal.vue'
import CreateStandardTransactionModal from './../components/TransactionForm/ModalStandard.vue'
import CreateInvestmentTransactionModal from './../components/TransactionForm/ModalInvestment.vue'

app.component('transaction-show-modal', TransactionShowModal)
app.component('transaction-create-standard-modal', CreateStandardTransactionModal)
app.component('transaction-create-investment-modal', CreateInvestmentTransactionModal)

app.mount('#app')

// Initialize tooltips in table
$(document).ready(function () {
    helpers.initializeBootstrapTooltips();
});