require('datatables.net-bs5');
require("datatables.net-responsive-bs5");

import * as dataTableHelpers from '../components/dataTableHelper';

import { RRule } from 'rrule';
import { toFormattedCurrency } from '../helpers';

var numberRenderer = $.fn.dataTable.render.number('&nbsp;', ',', 0).display;

window.table = $('#table').DataTable({
    ajax: {
        url: '/api/transactions/get_scheduled_items/any',
        type: 'GET',
        dataSrc: function(data) {
            return data.transactions.map(function(transaction) {
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
            });
        },
        deferRender: true
    },
    columns: [
        dataTableHelpers.transactionColumnDefiniton.dateFromCustomField('schedule_config.start_date', __('Start date'), window.YAFFA.locale),
        {
            data: "schedule_config.rule",
            title: __("Schedule"),
            render: function (data) {
                // Return human readable format of RRule
                // TODO: translation
                return data.toText();
            }
        },
        dataTableHelpers.transactionColumnDefiniton.dateFromCustomField('schedule_config.start_date', __('Next date'), window.YAFFA.locale),
        {
            data: "schedule",
            title: "Schedule",
            render: function (data, type) {
                return dataTableHelpers.booleanToTableIcon(data, type);
            },
            className: "text-center",
        },
        {
            data: "budget",
            title: __("Budget"),
            render: function (data, type) {
                return dataTableHelpers.booleanToTableIcon(data, type);
            },
            className: "text-center",
        },
        {
            data: "schedule_config.active",
            title: __("Active"),
            render: function (data, type) {
                return dataTableHelpers.booleanToTableIcon(data, type);
            },
            className: "text-center",
        },
        {
            data: "transaction_type.type",
            title: __("Type"),
            render: function (data, type) {
                if (type === 'filter') {
                    // TODO: this should be translated
                    return  data;
                }
                return (  data === 'standard'
                        ? '<i class="fa fa-money-bill text-primary" title="' + __('Standard') + '"></i>'
                        : '<i class="fa fa-line-chart text-primary" title="' + __('Investment') + '"></i>');
            },
            className: "text-center",
        },
        {
            title: 'Payee',
            defaultContent: '',
            render: function (_data, _type, row) {
                if (row.transaction_type.type === 'standard') {
                    if (row.transaction_type === 'withdrawal') {
                        return row.config.account_to.name;
                    }
                    if (row.transaction_type === 'deposit') {
                        return row.config.account_from.name;
                    }
                    if (row.transaction_type === 'transfer') {
                        if (row.transaction_operator === 'minus') {
                            return __('Transfer to :account', {account: row.config.account_to.name});
                        } else {
                            return __('Transfer from :account', {account: row.config.account_from.name});
                        }
                    }
                } else if (row.transaction_type.type === 'investment') {
                    return row.investment_name;
                }

                return null;
            },
        },
        {
            title: __("Category"),
            defaultContent: '',
            render: function (_data, _type, row) {
                // Standard transaction
                if (row.transaction_type.type === 'standard') {
                    // Empty
                    if (row.categories.length === 0) {
                        return '';
                    }

                    if (row.categories.length > 1) {
                        return 'Split transaction';
                    } else {
                        return row.categories[0];
                    }
                }
                // Investment transaction
                if (row.transaction_type.type === 'investment') {
                    if (!row.quantity_operator) {
                        return row.transaction_type.name;
                    }
                    if (!row.transaction_operator) {
                        return row.transaction_type.name + " " + row.quantity;
                    }

                    return row.transaction_type.name + " " + row.quantity + " @ " + numberRenderer(row.price);
                }

                return '';
            },
            orderable: false
        },
        {
            title: __("Amount"),
            defaultContent: '',
            render: function (_data, type, row) {
                if (type === 'display') {
                    let prefix = '';
                    if (row.transaction_operator == 'minus') {
                        prefix = '- ';
                    }
                    if (row.transaction_operator == 'plus') {
                        prefix = '+ ';
                    }
                    return prefix + toFormattedCurrency(row.config.amount_to, window.YAFFA.locale, row.currency);
                }

                return row.config.amount_to;
            },
            className: 'dt-nowrap',
        },
        {
            data: 'comment',
            title: __("Comment"),
            defaultContent: '',
            render: function (data, type) {
                return dataTableHelpers.commentIcon(data, type);
            },
            className: "text-center",
        },
        {
            data: "tags",
            title: __("Tags"),
            defaultContent: '',
            render: function (data, type) {
                return dataTableHelpers.tagIcon(data, type);
            },
            className: "text-center",
        },
        {
            data: 'id',
            title: __("Actions"),
            defaultContent: '',
            render: function (data, _type, row) {
                return  dataTableHelpers.dataTablesActionButton(data, 'edit', row.transaction_type.type) +
                        dataTableHelpers.dataTablesActionButton(data, 'clone', row.transaction_type.type) +
                        dataTableHelpers.dataTablesActionButton(data, 'replace', row.transaction_type.type) +
                        dataTableHelpers.dataTablesActionButton(data, 'delete') +
                        (row.schedule
                            ? '<a href="' + route('transactions.open.' + row.transaction_type.type, {transaction: data, action: 'enter'}) + '" class="btn btn-xs btn-success" title="' + __('Edit and insert instance') + '"><i class="fa fa-fw fa-pencil"></i></a> ' +
                              '<button class="btn btn-xs btn-warning data-skip" data-id="' + data + '" type="button" title="' + __('Skip current schedule') + '"><i class="fa fa-fw fa-forward"></i></i></button> '
                            : '');
            },
            className: "dt-nowrap",
            orderable: false,
            searchable: false,
        }
    ],
    createdRow: function (row, data) {
        if (!data.schedule_config.next_date) {
            return;
        }

        if (data.schedule_config.next_date  < new Date(new Date().setHours(0,0,0,0)) ) {
            $(row).addClass('danger');
        } else if (data.schedule_config.next_date  < new Date(new Date().setHours(24,0,0,0)) ) {
            $(row).addClass('warning');
        }
    },
    initComplete: function (_settings, _json) {
        $('[data-toggle="tooltip"]').tooltip();
    },
    order: [
        // Start date is the first column
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

dataTableHelpers.initializeSkipInstanceButton('#table');
dataTableHelpers.initializeDeleteButton('#table');

// Listeners for button filters
$('input[name=schedule]').on("change", function() {
    table.column(3).search(this.value).draw();
});

$('input[name=budget]').on("change", function() {
    table.column(4).search(this.value).draw();
});

$('input[name=active]').on("change", function() {
    table.column(5).search(this.value).draw();
});
