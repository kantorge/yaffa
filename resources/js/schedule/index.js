require( 'datatables.net' );
require( 'datatables.net-bs' );
import { RRule } from 'rrule';
import * as dataTableHelpers from './../components/dataTableHelper';

$(function() {
    var numberRenderer = $.fn.dataTable.render.number('&nbsp;', ',', 0).display;

    // Parse dates in transactionData, and initialize RRule
    let tableData = transactionData.map(function(transaction) {
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

    window.table = $('#table').DataTable({
        data: tableData,
        columns: [
            {
                data: "schedule_config.start_date",
                title: "Start date",
                render: function (data) {
                    return data.toLocaleDateString('hu-HU'); //TODO: make this dynamic
                },
                className: "cell-no-break",
            },
            {
                data: "schedule_config.rule",
                title: "Schedule",
                render: function (data) {
                    // Return human readable format
                    return data.toText();
                }
            },
            {
                data: "schedule_config.next_date",
                title: "Next date",
                render: function(data) {
                    if (!data) {
                        return '';
                    }

                    return data.toLocaleDateString('hu-HU'); //TODO: make this dynamic
                },
                className: "cell-no-break",
            },
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
                title: "Budget",
                render: function (data, type) {
                    return dataTableHelpers.booleanToTableIcon(data, type);
                },
                className: "text-center",
            },
            {
                data: "schedule_config.active",
                title: "Active",
                render: function (data, type) {
                    return dataTableHelpers.booleanToTableIcon(data, type);
                },
                className: "text-center",
            },
            {
                data: "transaction_type",
                title: "Type",
                render: function (data, type) {
                    if (type == 'filter') {
                        return  data;
                    }
                    return (  data == 'Standard'
                            ? '<i class="fa fa-money text-primary" title="Standard"></i>'
                            : '<i class="fa fa-line-chart text-primary" title="Investment"></i>');
                },
                className: "text-center",
            },
            {
                title: 'Payee',
                render: function (data, type, row) {
                    if (row.transaction_type == 'Standard') {
                        if (row.transaction_name == 'withdrawal') {
                            return row.account_to_name;
                        }
                        if (row.transaction_name == 'deposit') {
                            return row.account_from_name;
                        }
                        if (row.transaction_name == 'transfer') {
                            if (row.transaction_operator == 'minus') {
                                return 'Transfer to ' + row.account_to_name;
                            } else {
                                return 'Transfer from ' + row.account_from_name;
                            }
                        }
                    } else if (row.transaction_type == 'Investment') {
                        return row.investment_name;
                    }

                    return null;
                },
            },
            {
                title: "Category",
                render: function (data, type, row) {
                    //standard transaction
                    if (row.transaction_type == 'Standard') {
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
                    if (row.transaction_type == 'Investment') {
                        if (!row.quantity_operator) {
                            return row.transaction_name;
                        }
                        if (!row.transaction_operator) {
                            return row.transaction_name + " " + row.quantity;
                        }

                        return row.transaction_name + " " + row.quantity + " @ " + numberRenderer(row.price);
                    }

                    return '';
                },
                orderable: false
            },
            {
                title: "Amount",
                render: function (data, type, row) {
                    let prefix = '';
                    if (row.transaction_operator == 'minus') {
                        prefix = '- ';
                    }
                    if (row.transaction_operator == 'plus') {
                        prefix = '+ ';
                    }
                    return prefix + numberRenderer(row.amount);
                },
            },
            {
                title: "Comment",
                render: function (data, type) {
                    return dataTableHelpers.commentIcon(data, type);                },
                className: "text-center",
            },
            {
                data: "tags",
                title: "Tags",
                render: function (data, type) {
                    return dataTableHelpers.tagIcon(data, type);
                },
                className: "text-center",
            },
            {
                data: 'id',
                title: "Actions",
                render: function (data, _type, row) {
                    return  dataTableHelpers.dataTablesActionButton(data, 'edit', row.transaction_type.type) +
                            dataTableHelpers.dataTablesActionButton(data, 'clone', row.transaction_type.type) +
                            dataTableHelpers.dataTablesActionButton(data, 'replace', row.transaction_type.type) +
                            dataTableHelpers.dataTablesActionButton(data, 'delete') +
                            (row.schedule
                             ? '<a href="' + (row.transaction_type.type == 'Standard' ? route('transactions.openStandard', {transaction: data, action: 'enter'}) : route('transactions.openInvestment', {transaction: data, action: 'enter'})) + '" class="btn btn-xs btn-success"><i class="fa fa-fw fa-pencil" title="Edit and insert instance"></i></a> ' +
                               '<button class="btn btn-xs btn-warning data-skip" data-id="' + data + '" type="button"><i class="fa fa-fw fa-forward" title=Skip current schedule"></i></i></button> '
                             : '');
                },
                orderable: false
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
        order: [
            [ 0, "asc" ]
        ],
        deferRender:    true,
        scrollY:        '400px',
        scrollCollapse: true,
        scroller:       true,
        stateSave:      false,
        processing:     true,
        paging:         false,
    });

    $("#table").on("click", ".data-skip", function() {
        let form = document.getElementById('form-skip');
        form.action = route('transactions.skipScheduleInstance', {transaction: this.dataset.id});
        form.submit();
    });

    // Delete functionality
    dataTableHelpers.initializeDeleteButton('#table');

    $('input[name=schedule]').on("change", function() {
        table.column(3).search(this.value).draw();
    });

    $('input[name=budget]').on("change", function() {
        table.column(4).search(this.value).draw();
    });

    $('input[name=active]').on("change", function() {
        table.column(5).search(this.value).draw();
    });

    // Injitialize tooltips on this page
    $(function () {
        $('[data-toggle="tooltip"]').tooltip()
    });
});
