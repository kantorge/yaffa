require('datatables.net-bs5');
require("datatables.net-responsive-bs5");

import * as dataTableHelpers from './../components/dataTableHelper';

const selectorScheduleTable = '#scheduleTable';
const selectorHistoryTable = '#historyTable';

// Table data transformation
window.transactionData = window.transactionData.map(function (transaction) {
    if (transaction.date) {
        transaction.date = new Date(transaction.date);
    }

    return transaction;
});

window.scheduleData = window.scheduleData.map(function (transaction) {
    if (transaction.transaction_schedule.next_date) {
        transaction.transaction_schedule.next_date = new Date(transaction.transaction_schedule.next_date);
    }

    return transaction;
});

// Define some settings, that are common for the two tables
var dtColumnSettingPayee = {
    title: __('Payee'),
    defaultContent: '',
    render: function (_data, _type, row) {
        if (row.transaction_type.type === 'standard') {
            if (row.transaction_type.name === 'withdrawal') {
                return row.account_to_name;
            }
            if (row.transaction_type.name === 'deposit') {
                return row.account_from_name;
            }
            if (row.transaction_type.name === 'transfer') {
                if (row.transactionOperator === -1) {
                    return __('Transfer to :account', {account: row.account_to_name});
                } else {
                    return __('Transfer from :account', {account: row.account_from_name});
                }
            }
        }
        if (row.transaction_type.type === 'investment') {
            return row.account_to_name;
        }
        if (row.transaction_type.type === 'Opening balance') {
            return __('Opening balance');
        }
        return '';
    },
};

$(selectorHistoryTable).DataTable({
    data: transactionData,
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
                        ? (row.reconciled
                            ? __('Reconciled')
                            : __('Uncleared')
                        )
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
        dtColumnSettingPayee,
        dataTableHelpers.transactionColumnDefinition.category,
        {
            title: __('Withdrawal'),
            defaultContent: '',
            render: function (_data, type, row) {
                if (row.transactionOperator !== -1) {
                    return;
                }
                return dataTableHelpers.toFormattedCurrency(type, row.amount_from, window.YAFFA.locale, currency);
            },
            className: 'dt-nowrap',
        },
        {
            title: __('Deposit'),
            defaultContent: '',
            render: function (_data, type, row) {
                if (row.transactionOperator !== 1) {
                    return;
                }
                return dataTableHelpers.toFormattedCurrency(type, row.amount_to, window.YAFFA.locale, currency);
            },
            className: 'dt-nowrap',
        },
        {
            data: 'running_total',
            title: __('Running total'),
            defaultContent: '',
            render: function (data, type) {
                return dataTableHelpers.toFormattedCurrency(type, data, window.YAFFA.locale, currency);
            },
            className: 'dt-nowrap',
            createdCell: function (td, cellData) {
                if (cellData < 0) {
                    $(td).addClass('text-danger');
                }
            }
        },
        dataTableHelpers.transactionColumnDefinition.comment,
        dataTableHelpers.transactionColumnDefinition.tags,
        {
            title: __("Actions"),
            defaultContent: '',
            render: function (_data, _type, row) {
                if (row.transaction_type.type === 'Opening balance') {
                    return null;
                }
                if (row.schedule) {
                    if (row.schedule_first_instance) {
                        return '<a href="' + route('transaction.open', { transaction: row.originalId, action: 'enter' }) + '" class="btn btn-xs btn-success" title="' + __('Edit and insert instance') + '"><i class="fa fa-fw fa-pencil"></i></a> ' +
                               '<button class="btn btn-xs btn-warning data-skip" data-id="' + row.originalId + '" type="button" title="' + __('Skip current schedule') + '"><i class="fa fa-fw fa-forward"></i></i></button> ';
                    }
                    return null;
                }

                return dataTableHelpers.dataTablesActionButton(row.id, 'quickView') +
                       dataTableHelpers.dataTablesActionButton(row.id, 'show') +
                       dataTableHelpers.dataTablesActionButton(row.id, 'edit') +
                       dataTableHelpers.dataTablesActionButton(row.id, 'clone') +
                       dataTableHelpers.dataTablesActionButton(row.id, 'delete');
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
    stateSave: true,
    processing: true,
    paging: false,
});

$(selectorScheduleTable).DataTable({
    data: scheduleData,
    columns: [
        dataTableHelpers.transactionColumnDefinition.dateFromCustomField('transaction_schedule.next_date', __('Next date'), window.YAFFA.locale),
        dtColumnSettingPayee,
        dataTableHelpers.transactionColumnDefinition.category,
        {
            title: "Withdrawal",
            defaultContent: '',
            render: function (_data, type, row) {
                if (row.transactionOperator !== -1) {
                    return;
                }
                return dataTableHelpers.toFormattedCurrency(type, row.amount_from, window.YAFFA.locale, currency);
            },
            className: 'dt-nowrap'
        },
        {
            title: "Deposit",
            defaultContent: '',
            render: function (_data, type, row) {
                if (row.transactionOperator !== 1) {
                    return;
                }
                return dataTableHelpers.toFormattedCurrency(type, row.amount_to, window.YAFFA.locale, currency);
            },
            className: 'dt-nowrap'
        },
        dataTableHelpers.transactionColumnDefinition.comment,
        dataTableHelpers.transactionColumnDefinition.tags,
        {
            data: 'id',
            title: __("Actions"),
            render: function (data, _type, _row) {
                return '<a href="' + route('transaction.open' , { transaction: data, action: 'enter' }) + '" class="btn btn-xs btn-success"><i class="fa fa-fw fa-pencil" title="' + __('Edit and insert instance') +'"></i></a> ' +
                    '<button class="btn btn-xs btn-warning data-skip" data-id="' + data + '" type="button"><i class="fa fa-fw fa-forward" title="' + __('Skip current schedule') + '"></i></i></button> ' +
                    dataTableHelpers.dataTablesActionButton(data, 'edit') +
                    dataTableHelpers.dataTablesActionButton(data, 'clone') +
                    dataTableHelpers.dataTablesActionButton(data, 'replace') +
                    dataTableHelpers.dataTablesActionButton(data, 'delete');
            },
            orderable: false
        }
    ],

    createdRow: function (row, data) {
        var nextDate = new Date(data.transaction_schedule.next_date);
        if (nextDate < new Date(new Date().setHours(0, 0, 0, 0))) {
            $(row).addClass('table-danger');
        } else if (nextDate < new Date(new Date().setHours(24, 0, 0, 0))) {
            $(row).addClass('table-warning');
        }
    },
    order: [
        [0, "asc"]
    ],
    responsive: true,
    deferRender: true,
    scrollY: '400px',
    scrollCollapse: true,
    stateSave: true,
    processing: true,
    paging: false,
});

dataTableHelpers.initializeSkipInstanceButton("#historyTable, #scheduleTable");
dataTableHelpers.initializeAjaxDeleteButton("#historyTable, #scheduleTable");
dataTableHelpers.initializeQuickViewButton(selectorHistoryTable);

$('input[name=reconciled]').on("change", function () {
    $(selectorHistoryTable).DataTable().column(1).search(this.value).draw();
});

$(selectorHistoryTable).on("click", "i.reconcile", function () {
    if ($(this).hasClass("fa-spinner")) {
        return false;
    }

    var currentState = $(this).data("reconciled");

    $(this).removeClass().addClass('fa fa-spinner fa-spin');

    $.ajax({
        type: 'PUT',
        url: '/api/transaction/' + $(this).data("id") + '/reconciled/' + (!currentState ? 1 : 0),
        data: {
            "_token": csrfToken,
        },
        dataType: "json",
        context: this,
        success: function (_data) {
            currentState = !currentState;

            $(this).removeClass()
                .addClass('fa reconcile')
                .addClass((currentState ? "fa-check-circle text-success" : "fa-circle text-info"))
                .data("reconciled", currentState);
        }
    });
});

import { createApp } from 'vue'
const app = createApp({})

// Add global translator function
app.config.globalProperties.__ = window.__;

import TransactionShowModal from './../components/TransactionDisplay/Modal.vue'
app.component('transaction-show-modal', TransactionShowModal)

app.mount('#app')
