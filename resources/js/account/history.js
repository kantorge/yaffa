require('datatables.net');
require('datatables.net-bs');

import * as dataTableHelpers from './../components/dataTableHelper'

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

var numberRenderer = $.fn.dataTable.render.number('&nbsp;', ',', 0).display;

// Define some settings, that are common for the two tables
var dtColumnSettingPayee = {
    title: 'Payee',
    render: function (_data, _type, row) {
        if (row.transaction_type.type == 'standard') {
            if (row.transaction_type.name == 'withdrawal') {
                return row.account_to_name;
            }
            if (row.transaction_type.name == 'deposit') {
                return row.account_from_name;
            }
            if (row.transaction_type.name == 'transfer') {
                if (row.transactionOperator == 'minus') {
                    return 'Transfer to ' + row.account_to_name;
                } else {
                    return 'Transfer from ' + row.account_from_name;
                }
            }
        } else if (row.transaction_type.type === 'investment') {
            return row.account_to_name;
        } else if (row.transaction_type.type === 'Opening balance') {
            return 'Opening balance';
        }
        return null;
    },
};
var dtColumnSettingCategories = {
    title: "Category",
    render: function (_data, _type, row) {
        //standard transaction
        if (row.transaction_type.type == 'standard') {
            //empty
            if (row.categories.length == 0) {
                return '';
            }

            if (row.categories.length > 1) {
                return 'Split transaction';
            } else {
                return row.categories[0];
            }
        }
        //investment transaction
        if (row.transaction_type.type === 'investment') {
            if (!row.quantityOperator) {
                return row.transaction_type.name;
            }
            if (!row.transactionOperator) {
                return row.transaction_type.name + " " + row.quantity;
            }

            return row.transaction_type.name + " " + row.quantity + " @ " + numberRenderer(row.price);
        }

        return '';
    },
    orderable: false
};
var dtColumnSettingComment = {
    data: "comment",
    title: "Comment",
    render: function (data, type) {
        if (type === 'display') {
            data = truncateString(data, 20);
        }

        return data;
    },
    createdCell: function (td, cellData) {
        $(td).prop('title', cellData);
    }
};

var dtColumnSettingTags = {
    data: "tags",
    title: "Tags",
    render: function (data) {
        return data.join(', ');
    }
}

$('#historyTable').DataTable({
    data: transactionData,
    columns: [
        {
            data: "date",
            title: "Date",
            render: function (data) {
                if (!data) {
                    return data;
                }
                return data.toLocaleDateString('Hu-hu');
            },
            className: "dt-nowrap",
        },
        {
            data: "reconciled",
            title: '<span title="Reconciled">R</span>',
            className: "text-center",
            render: function (_data, type, row) {
                if (type == 'filter') {
                    return (!row.schedule
                        && (row.transaction_type.type === 'standard' || row.transaction_type.type === 'investment')
                        ? (row.reconciled == 1
                            ? 'Reconciled'
                            : 'Uncleared'
                        )
                        : 'Unavailable'
                    );
                }
                return (!row.schedule
                    && (row.transaction_type.type === 'standard' || row.transaction_type.type === 'investment')
                    ? (row.reconciled == 1
                        ? '<i class="fa fa-check-circle text-success reconcile" data-reconciled="true" data-id="' + row.id + '"></i>'
                        : '<i class="fa fa-circle text-info reconcile" data-reconciled="false" data-id="' + row.id + '"></i>'
                    )
                    : '<i class="fa fa-circle text-muted""></i>'
                );
            },
            orderable: false,
        },
        dtColumnSettingPayee,
        dtColumnSettingCategories,
        {
            title: "Withdrawal",
            render: function (_data, _type, row) {
                return (row.transactionOperator == 'minus' ? row.amount_from.toLocalCurrency(currency, true) : null);
            },
        },
        {
            title: "Deposit",
            render: function (_data, _type, row) {
                return (row.transactionOperator == 'plus' ? row.amount_to.toLocalCurrency(currency, true) : null);
            },
        },
        {
            data: 'running_total',
            title: 'Running total',
            render: function (data) {
                return data.toLocalCurrency(currency, true);
            },
            createdCell: function (td, cellData) {
                if (cellData < 0) {
                    $(td).addClass('text-danger');
                }
            }
        },
        dtColumnSettingComment,
        dtColumnSettingTags,
        {
            data: 'id',
            title: "Actions",
            render: function (data, _type, row) {
                if (row.transaction_type.type == 'Opening balance') {
                    return null;
                }
                if (row.schedule) {
                    if (row.schedule_first_instance) {
                        data = row.originalId;
                        return '<a href="' + route('transactions.open.standard', { transaction: data, action: 'enter' }) + '" class="btn btn-xs btn-success" title="Edit and insert instance"><i class="fa fa-fw fa-pencil"></i></a> ' +
                            '<button class="btn btn-xs btn-warning data-skip" data-id="' + data + '" type="button" title=Skip current schedule"><i class="fa fa-fw fa-forward"></i></i></button> ';
                    }
                    return null;
                }

                if (row.transaction_type.type === 'standard') {
                    return dataTableHelpers.dataTablesActionButton(data, 'standardQuickView') +
                        dataTableHelpers.dataTablesActionButton(data, 'standardShow') +
                        dataTableHelpers.dataTablesActionButton(data, 'edit', 'standard') +
                        dataTableHelpers.dataTablesActionButton(data, 'clone', 'standard') +
                        dataTableHelpers.dataTablesActionButton(data, 'delete');
                }

                // Investment
                return '<a href="' + route('transactions.open.investment', { transaction: data, action: 'edit' }) + '" class="btn btn-xs btn-primary" title="Edit"><i class="fa fa-fw fa-edit"></i></a> ' +
                    '<a href="' + route('transactions.open.investment', { transaction: data, action: 'clone' }) + '" class="btn btn-xs btn-primary" title="Clone"><i class="fa fa-fw fa-clone"></i></a> ' +
                    '<button class="btn btn-xs btn-danger data-delete" data-id="' + data + '" type="button" title="Delete"><i class="fa fa-fw fa-trash"></i></button>';
            },
            orderable: false
        }
    ],
    createdRow: function (row, data) {
        if (data.schedule) {
            $(row).addClass('text-muted text-italic');
        }
    },
    order: [
        [0, "asc"]
    ],
    deferRender: true,
    scrollY: '400px',
    scrollCollapse: true,
    scroller: true,
    stateSave: true,
    processing: true,
    paging: false,
});

$('#scheduleTable').DataTable({
    data: scheduleData,
    columns: [
        {
            data: "transaction_schedule.next_date",
            title: "Next date",
            render: function (data) {
                if (!data) {
                    return data;
                }
                return data.toLocaleDateString('Hu-hu').replace(/\s/g, '&nbsp;');
            }
        },
        dtColumnSettingPayee,
        dtColumnSettingCategories,
        {
            title: "Withdrawal",
            render: function (_data, _type, row) {
                return (row.transactionOperator == 'minus' ? row.amount_from.toLocalCurrency(currency, true) : null);
            },
        },
        {
            title: "Deposit",
            render: function (_data, _type, row) {
                return (row.transactionOperator == 'plus' ? row.amount_to.toLocalCurrency(currency, true) : null);
            },
        },
        dtColumnSettingComment,
        dtColumnSettingTags,
        {
            data: 'id',
            title: "Actions",
            render: function (data, _type, row) {
                return '<a href="' + route('transactions.open.' + row.transaction_type.type, { transaction: data, action: 'enter' }) + '" class="btn btn-xs btn-success"><i class="fa fa-fw fa-pencil" title="Edit and insert instance"></i></a> ' +
                    '<button class="btn btn-xs btn-warning data-skip" data-id="' + data + '" type="button"><i class="fa fa-fw fa-forward" title=Skip current schedule"></i></i></button> ' +
                    dataTableHelpers.dataTablesActionButton(data, 'edit', row.transaction_type.type) +
                    dataTableHelpers.dataTablesActionButton(data, 'clone', row.transaction_type.type) +
                    dataTableHelpers.dataTablesActionButton(data, 'replace', row.transaction_type.type) +
                    dataTableHelpers.dataTablesActionButton(data, 'delete');
            },
            orderable: false
        }
    ],

    createdRow: function (row, data) {
        var nextDate = new Date(data.transaction_schedule.next_date);
        if (nextDate < new Date(new Date().setHours(0, 0, 0, 0))) {
            $(row).addClass('danger');
        } else if (nextDate < new Date(new Date().setHours(24, 0, 0, 0))) {
            $(row).addClass('warning');
        }
    },
    order: [
        [0, "asc"]
    ],
    deferRender: true,
    scrollY: '400px',
    scrollCollapse: true,
    scroller: true,
    stateSave: true,
    processing: true,
    paging: false,
});

dataTableHelpers.initializeSkipInstanceButton("#historyTable, #scheduleTable");
dataTableHelpers.initializeDeleteButton("#historyTable, #scheduleTable");

$('input[name=reconciled]').on("change", function () {
    $('#historyTable').DataTable().column(1).search(this.value).draw();
});

$("#historyTable").on("click", "i.reconcile", function () {
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
        success: function (data) {
            if (data.success) {
                currentState = !currentState;
            }

            $(this).removeClass()
                .addClass('fa reconcile')
                .addClass((currentState ? "fa-check-circle text-success" : "fa-circle text-info"))
                .data("reconciled", currentState);
        }
    });
});

// DataTables helper: truncate a string
function truncateString(str, max, add) {
    add = add || '...';
    return (typeof str === 'string' && str.length > max ? str.substring(0, max) + add : str);
}

$('#historyTable').on('click', 'button.transaction-quickview', function () {
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
                    show: true,
                    edit: true,
                    clone: true,
                    skip: true,
                    enter: true,
                    delete: true,
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

import { createApp } from 'vue'

import TransactionShowModal from './../components/TransactionDisplay/Modal.vue'
const app = createApp({})

app.component('transaction-show-modal', TransactionShowModal)

app.mount('#app')
