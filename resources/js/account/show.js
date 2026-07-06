import 'datatables.net-bs5';
import 'datatables.net-responsive-bs5';

import * as dataTableHelpers from '@/shared/lib/datatable';
import * as helpers from '@/shared/lib/helpers';
import { getDataTablesLanguageOptions, toFormattedCurrency } from '@/shared/lib/i18n';
import * as toastHelpers from '@/shared/lib/toast';
import { initializeTwoColumnLeftControlPanelToggle } from '@/shared/lib/ui/leftControlPanelToggle';
import DateRangeFilterCard from '@/shared/ui/date/DateRangeFilterCard.vue';

const selectorScheduleTable = '#scheduleTable';
const selectorHistoryTable = '#historyTable';
const selectorLeftControlPanel = '#accountLeftControlPanel';
const selectorMainContent = '#accountMainContent';
const selectorLeftControlPanelToggleButton = '#toggleAccountLeftControlPanelButton';
const reconcileSectionLabels = {
    cash: {
        opening_balance: __('Opening balance'),
        total_withdrawals: __('Total withdrawals'),
        total_deposits: __('Total deposits'),
        balance: __('Balance'),
        checkpoint_value: __('Checkpoint value'),
        variance: __('Variance / Match'),
    },
    investment: {
        opening_value: __('Opening investment value'),
        closing_value: __('Closing investment value'),
        checkpoint_value: __('Checkpoint value'),
        variance: __('Variance / Match'),
    },
    total: {
        balance: __('Balance'),
        checkpoint_value: __('Checkpoint value'),
        variance: __('Variance / Match'),
    },
};

let advancedReconcileData = null;
let advancedReconcilePriceModal = null;
let advancedReconcilePriceContext = null;

function escapeHtml(value) {
    return $('<div>').text(value ?? '').html();
}

let currentDateFilters = {
    dateFrom: window.filters?.date_from || null,
    dateTo: window.filters?.date_to || null,
    preset: window.filters?.date_preset || null,
};

const hasInitialFilters =
    !!currentDateFilters.dateFrom ||
    !!currentDateFilters.dateTo ||
    (!!currentDateFilters.preset && currentDateFilters.preset !== 'none');

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

let dtHistory = $(selectorHistoryTable).DataTable({
    language: getDataTablesLanguageOptions() || undefined,
    ajax: function (_data, callback, _settings) {
        if (initialLoad) {
            initialLoad = false;
            callback({data: []}); // Don't fire ajax, just return empty set
            return;
        }

        const params = new URLSearchParams();
        if (currentDateFilters.dateFrom) {
            params.append('date_from', currentDateFilters.dateFrom);
        }
        if (currentDateFilters.dateTo) {
            params.append('date_to', currentDateFilters.dateTo);
        }
        params.append('accounts[]', account.id);

        // Ajax will now only fire programmatically, via ajax.reload()
        fetch(
            '/api/v1/transactions?' + params,
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
        dataTableHelpers.transactionColumnDefinition.dateFromCustomField('date', __('Date'), window.YAFFA.userSettings.locale),
        {
            data: "reconciled",
            title: '<span title="' + __('Reconciled') + '">R</span>',
            className: "text-center",
            render: function (_data, type, row) {
                if (type === 'filter') {
                    return (!row.schedule
                        && (row.config_type === 'standard' || row.config_type === 'investment')
                            ? (row.reconciled ? __('Reconciled') : __('Uncleared'))
                            : __('Unavailable')
                    );
                }
                return (!row.schedule
                    && (row.config_type === 'standard' || row.config_type === 'investment')
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
    language: getDataTablesLanguageOptions() || undefined,
    ajax: {
        url: '/api/v1/transactions/scheduled-items?type=schedule' +
            '&accountEntity=' + window.account.id +
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
        dataTableHelpers.transactionColumnDefinition.dateFromCustomField('transaction_schedule.next_date', __('Next date'), window.YAFFA.userSettings.locale),
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

    axios.patch('/api/v1/transactions/' + id + '/skip')
        .then(function (response) {
            // Find and update original row in schedule table
            let row = $(selectorScheduleTable).dataTable().api().row(function (_idx, data, _node) {
                return Number(data.id) === id;
            });

            let data = row.data();
            let newNextDate = response.data.transaction.transaction_schedule.next_date;
            // If next date exists, update the row. Otherwise remove it.
            if (newNextDate) {
                data.transaction_schedule.next_date = helpers.parseIsoDate(newNextDate);
                row.data(data).draw();

                toastHelpers.showToast(
                    __('Success'),
                    __('Schedule instance skipped.'),
                    'bg-success',
                    {
                        headerSmall: helpers.transactionLink(id, __('Go to transaction')),
                    }
                );
            } else {
                row.remove().draw();

                toastHelpers.showToast(
                    __('Success'),
                    __('Schedule instance skipped. This schedule has ended.'),
                    'bg-success',
                    {
                        headerSmall: helpers.transactionLink(id, __('Go to transaction')),
                    }
                );
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

    axios.get('/api/v1/accounts/' + window.account.id + '/balance')
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

            elementOpeningBalance.innerText = toFormattedCurrency(
                balance.config.opening_balance,
                window.YAFFA.userSettings.locale,
                balance.config.currency
            );

            elementCurrentCash.innerText = toFormattedCurrency(
                balance.cash,
                window.YAFFA.userSettings.locale,
                window.YAFFA.userSettings.baseCurrency
            );

            if (balance.hasOwnProperty('cash_foreign')) {
                elementCurrentCash.innerText += ' / ' + toFormattedCurrency(
                    balance.cash_foreign,
                    window.YAFFA.userSettings.locale,
                    balance.config.currency
                );
            }

            elementCurrentBalance.innerText = toFormattedCurrency(
                balance.sum,
                window.YAFFA.userSettings.locale,
                window.YAFFA.userSettings.baseCurrency
            );

            if (balance.hasOwnProperty('sum_foreign')) {
                elementCurrentBalance.innerText += ' / ' + toFormattedCurrency(
                    balance.sum_foreign,
                    window.YAFFA.userSettings.locale,
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

            toastHelpers.showErrorToast(error.message);
        });
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
        type: 'PATCH',
        url: '/api/v1/transactions/' + currentId + '/reconciliation',
        data: JSON.stringify({
            "reconciled": currentState ? false : true,
        }),
        contentType: 'application/json',
        headers: { 'X-CSRF-TOKEN': csrfToken },
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
    dtHistory.ajax.reload(function () {
        // (Re-)Initialize tooltips in table
        helpers.initializeBootstrapTooltips();
    });
}

const handleDateRangeUpdated = ({dateFrom, dateTo, preset}) => {
    currentDateFilters = {
        dateFrom: dateFrom || null,
        dateTo: dateTo || null,
        preset: preset || null,
    };

    loadAdvancedReconcile();
    reloadTable();
};

const dateRangeApp = createApp({
    components: {
        DateRangeFilterCard,
    },
    data() {
        return {
            initialDateFrom: currentDateFilters.dateFrom,
            initialDateTo: currentDateFilters.dateTo,
            initialPreset: currentDateFilters.preset,
        };
    },
    methods: {
        onDateRangeUpdated(payload) {
            handleDateRangeUpdated(payload);
        },
    },
    mounted() {
        if (hasInitialFilters && this.$refs.dateFilter?.emitDates) {
            this.$refs.dateFilter.emitDates();
        }
    },
});

installRouteGlobal(dateRangeApp);
dateRangeApp.mount('#account-date-range-filter');

function formatAccountCurrency(value) {
    return toFormattedCurrency(
        value || 0,
        window.YAFFA.userSettings.locale,
        window.account.config.currency
    );
}

function renderCheckpointValue(section) {
    if (!section.checkpoint) {
        return '<span class="text-muted">' + __('No checkpoint') + '</span>';
    }

    const note = section.checkpoint.note ? ' title="' + escapeHtml(section.checkpoint.note) + '"' : '';
    return '<span' + note + '>' + formatAccountCurrency(section.checkpoint.balance) + '</span>';
}

function renderVariance(section) {
    if (section.status === 'no_checkpoint') {
        return '<span class="text-muted">' + __('No checkpoint') + '</span>';
    }

    if (section.status === 'matched') {
        return '<span class="text-success"><i class="fa-solid fa-check"></i> ' + __('Matched') + '</span>';
    }

    return '<span class="text-warning"><i class="fa-solid fa-triangle-exclamation"></i> ' +
        formatAccountCurrency(section.variance) +
        '</span>';
}

function renderAdvancedReconcileSection(type, data) {
    const element = document.querySelector('[data-reconcile-section="' + type + '"]');
    if (!element) {
        return;
    }

    const labels = reconcileSectionLabels[type];
    element.innerHTML = Object.keys(labels).map((key) => {
        const value = key === 'checkpoint_value'
            ? renderCheckpointValue(data)
            : (key === 'variance' ? renderVariance(data) : formatAccountCurrency(data[key]));

        return '<dt class="col-7">' + labels[key] + '</dt><dd class="col-5 text-end">' + value + '</dd>';
    }).join('');
}

function renderAdvancedReconcileHoldings(holdings) {
    const body = document.getElementById('advancedReconcileHoldingsBody');
    if (!body) {
        return;
    }

    if (!holdings.length) {
        body.innerHTML = '<tr><td colspan="7" class="text-muted">' + __('No investment holdings in this period') + '</td></tr>';
        return;
    }

    body.innerHTML = holdings.map((holding) => {
        return '<tr class="' + (holding.has_missing_price ? 'table-warning' : '') + '">' +
            '<td>' + escapeHtml(holding.name) + '</td>' +
            '<td class="text-end">' + holding.open_quantity + '</td>' +
            '<td class="text-end">' + holding.close_quantity + '</td>' +
            '<td class="text-end">' + holding.buys + '</td>' +
            '<td class="text-end">' + holding.sells + '</td>' +
            '<td class="text-end">' + renderHoldingPriceButton(holding, 'open') + '</td>' +
            '<td class="text-end">' + renderHoldingPriceButton(holding, 'close') + '</td>' +
             '<td class="text-end">' + formatAccountCurrency(holding.open_value)  + '</td>' +
            '<td class="text-end">' + formatAccountCurrency(holding.close_value) + '</td>' +
            '</tr>';
    }).join('');
}

function renderHoldingPriceButton(holding, side) {
    const quantity = side === 'open' ? holding.open_quantity : holding.close_quantity;
    const price = side === 'open' ? holding.open_price : holding.close_price;
    const value = side === 'open' ? holding.open_value : holding.close_value;
    const date = side === 'open' ? holding.open_price_date : holding.close_price_date;
    const storedPriceId = side === 'open' ? holding.open_stored_price_id : holding.close_stored_price_id;
    const isMissing = quantity !== 0 && price === null;
    const label = isMissing ? __('Missing') : formatAccountCurrency(price);
    const className = isMissing ? 'btn btn-xs btn-outline-warning' : 'btn btn-xs btn-link p-0 text-decoration-none';

    return '<button type="button" class="' + className + '" ' +
        'data-price-investment="' + holding.investment_id + '" ' +
        'data-price-date="' + date + '" ' +
        'data-price-side="' + side + '" ' +
        'data-price-quantity="' + quantity + '" ' +
        'data-price-current="' + (price ?? '') + '" ' +
        'data-price-current-value="' + (value ?? '') + '" ' +
        'data-price-stored-id="' + (storedPriceId ?? '') + '" ' +
        'data-price-investment-name="' + escapeHtml(holding.name) + '">' +
        label +
        '</button>';
}

function renderAdvancedReconcile(data) {
    advancedReconcileData = data;
    renderAdvancedReconcileSection('cash', data.cash);
    renderAdvancedReconcileSection('investment', data.investment);
    renderAdvancedReconcileSection('total', data.total);
    renderAdvancedReconcileHoldings(data.investment.holdings || []);
    helpers.initializeBootstrapTooltips();
}

function loadAdvancedReconcile() {
    const params = new URLSearchParams();
    if (currentDateFilters.dateFrom) {
        params.append('date_from', currentDateFilters.dateFrom);
    }
    if (currentDateFilters.dateTo) {
        params.append('date_to', currentDateFilters.dateTo);
    }

    fetch('/api/v1/accounts/' + window.account.id + '/advanced-reconcile?' + params, {
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': window.csrfToken,
        },
    })
        .then(response => response.json())
        .then(renderAdvancedReconcile)
        .catch(error => toastHelpers.showErrorToast(error.message));
}

document.querySelectorAll('[data-checkpoint-type]').forEach((button) => {
    button.addEventListener('click', () => {
        if (!advancedReconcileData) {
            return;
        }

        const type = button.dataset.checkpointType;
        const section = advancedReconcileData[type];
        const balance = type === 'investment' ? section.closing_value : section.balance;
        const checkpointValue = prompt(__('Checkpoint value'), balance);
        if (checkpointValue === null) {
            return;
        }

        const note = prompt(__('Checkpoint note'), '');

        axios.post('/api/v1/accounts/' + window.account.id + '/balance-checkpoints', {
            checkpoint_date: advancedReconcileData.date_to,
            checkpoint_type: type,
            balance: checkpointValue,
            note: note,
        }).then(() => {
            toastHelpers.showSuccessToast(__('Checkpoint saved'));
            loadAdvancedReconcile();
        }).catch((error) => {
            toastHelpers.showErrorToast(error.message);
        });
    });
});

document.getElementById('advancedReconcileHoldingsBody')?.addEventListener('click', (event) => {
    const button = event.target.closest('[data-price-investment]');
    if (!button) {
        return;
    }

    openAdvancedReconcilePriceModal(button);
});

function ensureAdvancedReconcilePriceModal() {
    let element = document.getElementById('advancedReconcilePriceModal');
    if (!element) {
        document.body.insertAdjacentHTML('beforeend', `
            <div class="modal fade" id="advancedReconcilePriceModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog">
                    <form class="modal-content" id="advancedReconcilePriceForm">
                        <div class="modal-header">
                            <h5 class="modal-title">${__('Set investment price')}</h5>
                            <button type="button" class="btn-close" data-coreui-dismiss="modal" data-bs-dismiss="modal" aria-label="${__('Close')}"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <div class="fw-semibold" id="advancedReconcilePriceInvestment"></div>
                                <div class="text-muted small" id="advancedReconcilePriceMeta"></div>
                            </div>
                            <ul class="nav nav-tabs" role="tablist">
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link active" id="advancedReconcilePriceTab" data-bs-toggle="tab" data-coreui-toggle="tab" data-bs-target="#advancedReconcilePricePanel" data-coreui-target="#advancedReconcilePricePanel" type="button" role="tab">
                                        ${__('Price at date')}
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="advancedReconcileValueTab" data-bs-toggle="tab" data-coreui-toggle="tab" data-bs-target="#advancedReconcileValuePanel" data-coreui-target="#advancedReconcileValuePanel" type="button" role="tab">
                                        ${__('Value at date')}
                                    </button>
                                </li>
                            </ul>
                            <div class="tab-content border border-top-0 p-3 mb-3">
                                <div class="tab-pane fade show active" id="advancedReconcilePricePanel" role="tabpanel" tabindex="0">
                                    <label class="form-label" for="advancedReconcilePriceInput">${__('Investment price')}</label>
                                    <input type="number" min="0.0000000001" step="0.0000000001" class="form-control" id="advancedReconcilePriceInput" required>
                                </div>
                                <div class="tab-pane fade" id="advancedReconcileValuePanel" role="tabpanel" tabindex="0">
                                    <label class="form-label" for="advancedReconcileValueInput">${__('Holding value')}</label>
                                    <input type="number" min="0.01" step="0.01" class="form-control" id="advancedReconcileValueInput">
                                    <div class="form-text" id="advancedReconcileValueHelp"></div>
                                </div>
                            </div>
                            <div class="alert alert-danger d-none" id="advancedReconcilePriceError"></div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-coreui-dismiss="modal" data-bs-dismiss="modal">${__('Cancel')}</button>
                            <button type="submit" class="btn btn-primary">${__('Save price')}</button>
                        </div>
                    </form>
                </div>
            </div>
        `);

        element = document.getElementById('advancedReconcilePriceModal');
        document.getElementById('advancedReconcilePriceForm').addEventListener('submit', saveAdvancedReconcilePrice);
    }

    if (!advancedReconcilePriceModal) {
        if (window.coreui && window.coreui.Modal) {
            advancedReconcilePriceModal = new window.coreui.Modal(element);
        } else {
            advancedReconcilePriceModal = new window.bootstrap.Modal(element);
        }
    }

    return element;
}

function openAdvancedReconcilePriceModal(button) {
    const element = ensureAdvancedReconcilePriceModal();
    const quantity = Number(button.dataset.priceQuantity || 0);
    const currentPrice = button.dataset.priceCurrent ? Number(button.dataset.priceCurrent) : null;
    const currentValue = button.dataset.priceCurrentValue ? Number(button.dataset.priceCurrentValue) : null;

    advancedReconcilePriceContext = {
        investmentId: button.dataset.priceInvestment,
        storedPriceId: button.dataset.priceStoredId || null,
        date: button.dataset.priceDate,
        side: button.dataset.priceSide,
        quantity: quantity,
    };

    element.querySelector('#advancedReconcilePriceInvestment').textContent = button.dataset.priceInvestmentName || '';
    element.querySelector('#advancedReconcilePriceMeta').textContent = __(':side price for :date', {
        side: button.dataset.priceSide === 'open' ? __('Opening') : __('Closing'),
        date: button.dataset.priceDate,
    });
    element.querySelector('#advancedReconcilePriceInput').value = currentPrice ?? '';
    element.querySelector('#advancedReconcileValueInput').value = currentValue || '';
    element.querySelector('#advancedReconcileValueHelp').textContent = __('Quantity at this date: :quantity', {
        quantity: quantity,
    });
    element.querySelector('#advancedReconcilePriceError').classList.add('d-none');

    const valueTab = element.querySelector('#advancedReconcileValueTab');
    valueTab.disabled = quantity === 0;
    element.querySelector('#advancedReconcilePriceTab').click();

    advancedReconcilePriceModal.show();
}

function saveAdvancedReconcilePrice(event) {
    event.preventDefault();

    const modalElement = document.getElementById('advancedReconcilePriceModal');
    const errorElement = modalElement.querySelector('#advancedReconcilePriceError');
    const useValue = modalElement.querySelector('#advancedReconcileValuePanel').classList.contains('active');
    const rawPrice = Number(modalElement.querySelector('#advancedReconcilePriceInput').value);
    const rawValue = Number(modalElement.querySelector('#advancedReconcileValueInput').value);
    let price = rawPrice;

    errorElement.classList.add('d-none');

    if (useValue) {
        if (!advancedReconcilePriceContext.quantity) {
            errorElement.textContent = __('A holding value can only be converted when the quantity is not zero.');
            errorElement.classList.remove('d-none');
            return;
        }

        price = rawValue / advancedReconcilePriceContext.quantity;
    }

    if (!Number.isFinite(price) || price <= 0) {
        errorElement.textContent = __('Enter a price greater than zero.');
        errorElement.classList.remove('d-none');
        return;
    }

    const payload = {
        investment_id: advancedReconcilePriceContext.investmentId,
        date: advancedReconcilePriceContext.date,
        price: Number(price.toFixed(10)),
    };

    const request = advancedReconcilePriceContext.storedPriceId
        ? axios.put('/api/v1/investment-prices/' + advancedReconcilePriceContext.storedPriceId, {
            ...payload,
            id: advancedReconcilePriceContext.storedPriceId,
        })
        : axios.post('/api/v1/investment-prices', payload);

    request.then(() => {
        toastHelpers.showSuccessToast(__('Investment price saved'));
        advancedReconcilePriceModal.hide();
        loadAdvancedReconcile();
    }).catch((error) => {
        errorElement.textContent = error.response?.data?.message || error.message;
        errorElement.classList.remove('d-none');
    });
}

loadAdvancedReconcile();

// Set up event listener for new standard transaction button
$('#create-standard-transaction-button').on('click', function () {
    // TODO: should this data passed back and forth instead of storing it?
    recentTransactionDraftId = undefined;

    // Create transaction daft
    const transaction = {
        transaction_type: 'withdrawal',
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
        transaction_type: 'buy',
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
    // Transform incoming data — helpers.processTransaction() already converts dates
    let transaction = processTransaction(helpers.processTransaction(event.detail.transaction));

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

    axios.post(window.route(
        'api.v1.accounts.monthly-summary',
        {accountEntity: window.account.id}
    ))
        .then(function (response) {
            const data = response.data;
            toastHelpers.showSuccessToast(data.message);

            // Reload the account balance with a static delay
            setTimeout(getAccountBalance, 5000);
        })
        .catch(function (error) {
            toastHelpers.showErrorToast(error.message);
        })
        .finally(function () {
            button.classList.remove('busy');
        });
});

// Initialize Vue for the quick view
import { createApp } from 'vue';
import { installRouteGlobal } from '@/shared/lib/vue/installRouteGlobal';

const app = createApp({})

// Add global translator function
app.config.globalProperties.__ = window.__;
installRouteGlobal(app);

import TransactionShowModal from '@/transactions/components/display/Modal.vue'
import CreateStandardTransactionModal from '@/transactions/components/form/ModalStandard.vue'
import CreateInvestmentTransactionModal from '@/transactions/components/form/ModalInvestment.vue'

app.component('transaction-show-modal', TransactionShowModal)
app.component('transaction-create-standard-modal', CreateStandardTransactionModal)
app.component('transaction-create-investment-modal', CreateInvestmentTransactionModal)

app.mount('#app')

// Initialize tooltips in table
$(document).ready(function () {
    helpers.initializeBootstrapTooltips();
    initializeTwoColumnLeftControlPanelToggle({
        leftControlPanelSelector: selectorLeftControlPanel,
        mainContentSelector: selectorMainContent,
        toggleButtonSelector: selectorLeftControlPanelToggleButton,
    });
});
