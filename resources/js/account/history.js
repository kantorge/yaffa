import 'datatables.net-bs5';
import 'datatables.net-select-bs5';
import 'datatables.net-scroller-bs5';
import 'datatables-contextual-actions';

import * as dataTableHelpers from '@/shared/lib/datatable';
import { getDataTablesLanguageOptions } from '@/shared/lib/i18n';
import { parseIsoDate } from '@/shared/lib/helpers';

const selectorScheduleTable = '#scheduleTable';
const selectorHistoryTable = '#historyTable';

// Table data transformation
window.transactionData = window.transactionData.map(function (transaction) {
    if (transaction.date) {
        transaction.date = parseIsoDate(transaction.date);
    }

    return transaction;
});

window.scheduleData = window.scheduleData.map(function (transaction) {
    if (transaction.transaction_schedule && transaction.transaction_schedule.next_date) {
        transaction.transaction_schedule.next_date = parseIsoDate(transaction.transaction_schedule.next_date);
    }

    return transaction;
});

// Define some settings, that are common for the two tables
var dtColumnSettingPayee = {
    title: __('Payee'),
    defaultContent: '',
    render: function (_data, _type, row) {
        if (row.config_type === 'standard') {
            if (row.transaction_type === 'withdrawal') {
                return row.account_to_name;
            }
            if (row.transaction_type === 'deposit') {
                return row.account_from_name;
            }
            if (row.transaction_type === 'transfer') {
                if (row.transactionOperator === -1) {
                    return __('Transfer to :account', {account: row.account_to_name});
                } else {
                    return __('Transfer from :account', {account: row.account_from_name});
                }
            }
        }
        if (row.config_type === 'investment') {
            return row.account_to_name;
        }
        if (row.transaction_type === 'Opening balance') {
            return __('Opening balance');
        }
        return '';
    },
};

let historyTable = $(selectorHistoryTable).DataTable({
    language: getDataTablesLanguageOptions() || undefined,
    data: transactionData,
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
                        ? (row.reconciled
                            ? __('Reconciled')
                            : __('Uncleared')
                        )
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
        dtColumnSettingPayee,
        dataTableHelpers.transactionColumnDefinition.category,
        {
            title: __('Withdrawal'),
            defaultContent: '',
            render: function (_data, type, row) {
                if (row.transactionOperator !== -1) {
                    return;
                }
                return dataTableHelpers.toFormattedCurrency(type, row.amount_from, window.YAFFA.userSettings.locale, currency);
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
                return dataTableHelpers.toFormattedCurrency(type, row.amount_to, window.YAFFA.userSettings.locale, currency);
            },
            className: 'dt-nowrap',
        },
        {
            data: 'running_total',
            title: __('Running total'),
            defaultContent: '',
            render: function (data, type) {
                return dataTableHelpers.toFormattedCurrency(type, data, window.YAFFA.userSettings.locale, currency);
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
            // Rendering 5 buttons (10+ DOM nodes) per row here, for every row in a
            // multi-thousand-row account history, was a major contributor to slow initial
            // render. The actual actions are now defined once in the contextualActions() call
            // below and shown on demand in a context menu - this cell only needs to render a
            // trigger icon (and only for rows that actually have actions available).
            render: function (_data, _type, row) {
                if (row.transaction_type === 'Opening balance') {
                    return '';
                }
                if (row.schedule && !row.schedule_first_instance) {
                    return '';
                }

                return '<i class="hover-icon fa fa-fw fa-ellipsis-vertical" title="' + __('Actions') + '"></i>';
            },
            className: "text-center",
            orderable: false,
            searchable: false,
        }
    ],
    createdRow: function (row, data) {
        $(row).attr('data-id', data.id);

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
    // Required so the contextualActions plugin below has row selection (.select()/.deselect())
    // to work with; info:false suppresses the extra "(1 row selected)" text it would otherwise
    // append every time a row is right-clicked or its action icon is used.
    select: {
        select: true,
        info: false,
        style: 'os',
    },
    deferRender: true,
    scrollY: '400px',
    scrollCollapse: true,
    // Scroller virtualizes rendering (only the rows near the viewport are ever real DOM nodes),
    // which is what actually keeps a multi-thousand-row account history fast - scrollY alone
    // does not do this, it just makes an already-fully-rendered table scroll in a fixed-height
    // box. Scroller needs DataTables' own paging enabled internally to drive it, but the classic
    // click-to-jump pagination control it would otherwise render fights with Scroller's own
    // virtual-scroll position - clicking a page number reliably blanked the table, while
    // scrolling (which drives Scroller correctly) worked fine. `dom` below drops the pagination
    // control (and the now-meaningless length menu) from the rendered UI while leaving `paging`
    // itself on, keeping just the processing indicator, the table, and the "Showing X to Y of Z"
    // info text (which Scroller keeps in sync with the actual scroll position).
    scroller: true,
    paging: true,
    dom: 'rti',
    stateSave: true,
    processing: true,
});

// Contextual actions for historyTable, replacing the always-rendered per-row action buttons
// (see the Actions column's render() above). Items mirror exactly what those buttons used to
// do; isHidden mirrors the same row-type checks the old render() used to decide which buttons
// to print.
historyTable.contextualActions({
    contextMenuClasses: ['text-primary'],
    deselectAfterAction: true,
    contextMenu: {
        enabled: true,
        isMulti: false,
        headerRenderer: false,
        triggerButtonSelector: '.hover-icon',
    },
    buttonList: {
        enabled: false
    },
    items: [
        {
            type: 'option',
            title: __('Quick view'),
            iconClass: 'fa fa-eye',
            contextMenuClasses: ['text-success'],
            action: function (row) {
                dataTableHelpers.triggerTransactionQuickView(row[0].id);
            },
            isHidden: function (row) {
                return row.transaction_type === 'Opening balance' || !!row.schedule;
            },
        },
        {
            type: 'option',
            title: __('View details'),
            iconClass: 'fa fa-search',
            contextMenuClasses: ['text-success'],
            action: function (row) {
                window.location.href = route('transaction.open', { transaction: row[0].id, action: 'show' });
            },
            isHidden: function (row) {
                return row.transaction_type === 'Opening balance' || !!row.schedule;
            },
        },
        {
            type: 'option',
            title: __('Edit'),
            iconClass: 'fa fa-edit',
            contextMenuClasses: ['text-primary'],
            action: function (row) {
                window.location.href = route('transaction.open', { transaction: row[0].id, action: 'edit' });
            },
            isHidden: function (row) {
                return row.transaction_type === 'Opening balance' || !!row.schedule;
            },
        },
        {
            type: 'option',
            title: __('Clone'),
            iconClass: 'fa fa-clone',
            contextMenuClasses: ['text-primary'],
            action: function (row) {
                window.location.href = route('transaction.open', { transaction: row[0].id, action: 'clone' });
            },
            isHidden: function (row) {
                return row.transaction_type === 'Opening balance' || !!row.schedule;
            },
        },
        {
            type: 'divider',
            isHidden: function (row) {
                return row.transaction_type === 'Opening balance' || !!row.schedule;
            },
        },
        {
            type: 'option',
            title: __('Delete'),
            iconClass: 'fa fa-trash',
            contextMenuClasses: ['text-danger'],
            action: function (row) {
                dataTableHelpers.deleteTransactionRow(selectorHistoryTable, row[0].id);
            },
            isHidden: function (row) {
                return row.transaction_type === 'Opening balance' || !!row.schedule;
            },
        },
        {
            type: 'option',
            title: __('Edit and insert instance'),
            iconClass: 'fa fa-pencil',
            contextMenuClasses: ['text-success fw-bold'],
            action: function (row) {
                window.location.href = route('transaction.open', { transaction: row[0].originalId, action: 'enter' });
            },
            isHidden: function (row) {
                return !row.schedule || !row.schedule_first_instance;
            },
        },
        {
            type: 'option',
            title: __('Skip current schedule'),
            iconClass: 'fa fa-forward',
            contextMenuClasses: ['text-warning fw-bold'],
            action: function (row) {
                const form = document.getElementById('form-skip');
                form.action = route('transactions.skipScheduleInstance', { transaction: row[0].originalId });
                form.submit();
            },
            isHidden: function (row) {
                return !row.schedule || !row.schedule_first_instance;
            },
        },
    ],
});

let scheduleTable = $(selectorScheduleTable).DataTable({
    language: getDataTablesLanguageOptions() || undefined,
    data: scheduleData,
    columns: [
        dataTableHelpers.transactionColumnDefinition.dateFromCustomField('transaction_schedule.next_date', __('Next date'), window.YAFFA.userSettings.locale),
        dtColumnSettingPayee,
        dataTableHelpers.transactionColumnDefinition.category,
        {
            title: "Withdrawal",
            defaultContent: '',
            render: function (_data, type, row) {
                if (row.transactionOperator !== -1) {
                    return;
                }
                return dataTableHelpers.toFormattedCurrency(type, row.amount_from, window.YAFFA.userSettings.locale, currency);
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
                return dataTableHelpers.toFormattedCurrency(type, row.amount_to, window.YAFFA.userSettings.locale, currency);
            },
            className: 'dt-nowrap'
        },
        dataTableHelpers.transactionColumnDefinition.comment,
        dataTableHelpers.transactionColumnDefinition.tags,
        {
            title: __("Actions"),
            defaultContent: '',
            render: function (_data, _type, _row) {
                return '<i class="hover-icon fa fa-fw fa-ellipsis-vertical" title="' + __('Actions') + '"></i>';
            },
            className: "text-center",
            orderable: false,
            searchable: false,
        }
    ],

    createdRow: function (row, data) {
        $(row).attr('data-id', data.id);

        var nextDate = data.transaction_schedule.next_date;
        if (nextDate < new Date(new Date().setHours(0, 0, 0, 0))) {
            $(row).addClass('table-danger');
        } else if (nextDate < new Date(new Date().setHours(24, 0, 0, 0))) {
            $(row).addClass('table-warning');
        }
    },
    order: [
        [0, "asc"]
    ],
    // Required so the contextualActions plugin below has row selection to work with; info:false
    // suppresses the extra "(1 row selected)" text (see historyTable above for the same setup).
    select: {
        select: true,
        info: false,
        style: 'os',
    },
    deferRender: true,
    scrollY: '400px',
    scrollCollapse: true,
    stateSave: true,
    processing: true,
    paging: false,
});

// Contextual actions for scheduleTable, replacing the always-rendered per-row action buttons
// (see the Actions column's render() above). Items mirror exactly what those buttons used to do -
// same routes/ids, same #form-skip submission for skip, same confirmation-less delete.
scheduleTable.contextualActions({
    contextMenuClasses: ['text-primary'],
    deselectAfterAction: true,
    contextMenu: {
        enabled: true,
        isMulti: false,
        headerRenderer: false,
        triggerButtonSelector: '.hover-icon',
    },
    buttonList: {
        enabled: false
    },
    items: [
        {
            type: 'option',
            title: __('Edit and insert instance'),
            iconClass: 'fa fa-pencil',
            contextMenuClasses: ['text-success fw-bold'],
            action: function (row) {
                window.location.href = route('transaction.open', { transaction: row[0].id, action: 'enter' });
            },
        },
        {
            type: 'option',
            title: __('Skip current schedule'),
            iconClass: 'fa fa-forward',
            contextMenuClasses: ['text-warning fw-bold'],
            action: function (row) {
                const form = document.getElementById('form-skip');
                form.action = route('transactions.skipScheduleInstance', { transaction: row[0].id });
                form.submit();
            },
        },
        {
            type: 'option',
            title: __('Edit'),
            iconClass: 'fa fa-edit',
            contextMenuClasses: ['text-primary'],
            action: function (row) {
                window.location.href = route('transaction.open', { transaction: row[0].id, action: 'edit' });
            },
        },
        {
            type: 'option',
            title: __('Clone'),
            iconClass: 'fa fa-clone',
            contextMenuClasses: ['text-primary'],
            action: function (row) {
                window.location.href = route('transaction.open', { transaction: row[0].id, action: 'clone' });
            },
        },
        {
            type: 'option',
            title: __('Edit and create new schedule'),
            iconClass: 'fa fa-calendar',
            contextMenuClasses: ['text-primary'],
            action: function (row) {
                window.location.href = route('transaction.open', { transaction: row[0].id, action: 'replace' });
            },
        },
        {
            type: 'divider',
        },
        {
            type: 'option',
            title: __('Delete'),
            iconClass: 'fa fa-trash',
            contextMenuClasses: ['text-danger'],
            action: function (row) {
                dataTableHelpers.deleteTransactionRow(selectorScheduleTable, row[0].id);
            },
        },
    ],
});

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
        type: 'PATCH',
        url: '/api/v1/transactions/' + $(this).data("id") + '/reconciliation',
        data: JSON.stringify({
            "reconciled": !currentState ? true : false,
        }),
        contentType: 'application/json',
        headers: { 'X-CSRF-TOKEN': csrfToken },
        success: (data) => {
            currentState = data.transaction.reconciled;

            $(this).removeClass()
                .addClass('fa reconcile')
                .addClass((currentState ? "fa-check-circle text-success" : "fa-circle text-info"))
                .data("reconciled", currentState);
        }
    });
});

import { createApp } from 'vue'
import { installRouteGlobal } from '@/shared/lib/vue/installRouteGlobal';
const app = createApp({})

// Add global translator function
app.config.globalProperties.__ = window.__;
installRouteGlobal(app);

import TransactionShowModal from '@/transactions/components/display/Modal.vue'
app.component('transaction-show-modal', TransactionShowModal)

app.mount('#app')
