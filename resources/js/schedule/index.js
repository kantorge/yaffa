require( 'datatables.net' );
require( 'datatables.net-bs' );
import { RRule } from 'rrule';

$(function() {
    var csrfToken = $('meta[name="csrf-token"]').attr('content');
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
                    return booleanToTableIcon(data, type);
                },
                className: "text-center",
            },
            {
                data: "budget",
                title: "Budget",
                render: function (data, type) {
                    return booleanToTableIcon(data, type);
                },
                className: "text-center",
            },
            {
                data: "schedule_config.active",
                title: "Active",
                render: function (data, type) {
                    return booleanToTableIcon(data, type);
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
                render: function (data, type, row) {
                    if (!row.comment) {
                        return null;
                    }

                    if (type === 'filter') {
                        return row.comment;
                    }

                    return '<i class="fa fa-comment text-primary" data-toggle="tooltip" data-placement="top" title="' + row.comment + '"></i>';
                },
                className: "text-center",
            },
            {
                data: "tags",
                title: "Tags",
                render: function (data, type) {
                    if (data.length === 0) {
                        return '';
                    }

                    if (type === 'filter') {
                        return data.join(', ');
                    }

                    if (data) {
                        return '<i class="fa fa-tag text-primary" data-toggle="tooltip" data-placement="top" title="' + data.join(', ') + '"></i>';
                    }
                },
                className: "text-center",
            },
            {
                data: 'id',
                title: "Actions",
                render: function (data, type, row) {
                    return  '' +
                            (row.transaction_type == 'Standard'
                             ? '<a href="' + route('transactions.openStandard', {transaction: data, action: 'edit'}) + '" class="btn btn-xs btn-primary"><i class="fa fa-fw fa-edit" title="Edit"></i></a> ' +
                               '<a href="' + route('transactions.openStandard', {transaction: data, action: 'clone'}) + '" class="btn btn-xs btn-primary"><i class="fa fa-fw fa-clone" title="Clone"></i></a> '
                             : '<a href="' + route('transactions.openInvestment', {transaction: data, action: 'edit'}) + '" class="btn btn-xs btn-primary"><i class="fa fa-fw fa-edit" title="Edit"></i></a> ' +
                               '<a href="' + route('transactions.openInvestment', {transaction: data, action: 'clone'}) + '" class="btn btn-xs btn-primary"><i class="fa fa-fw fa-clone" title="Clone"></i></a> ' ) +
                            '<button class="btn btn-xs btn-danger data-delete" data-form="' + data + '"><i class="fa fa-fw fa-trash" title="Delete"></i></button> ' +
                            '<form id="form-delete-' + data + '" action="' + route('transactions.destroy', {transaction: data}) + '" method="POST" style="display: none;"><input type="hidden" name="_method" value="DELETE"><input type="hidden" name="_token" value="' + csrfToken + '"></form>' +
                            '<a href="' + (row.transaction_type == 'Standard' ? route('transactions.openStandard', {transaction: data, action: 'enter'}) : route('transactions.openInvestment', {transaction: data, action: 'enter'})) + '" class="btn btn-xs btn-success"><i class="fa fa-fw fa-pencil" title="Edit and insert instance"></i></a> ' +
                            '<button class="btn btn-xs btn-warning data-skip" data-form="' + data + '"><i class="fa fa-fw fa-forward" title=Skip current schedule"></i></i></button> ' +
                            '<form id="form-skip-' + data + '" action="' + route('transactions.skipScheduleInstance', {transaction: data}) + '" method="POST" style="display: none;"><input type="hidden" name="_method" value="PATCH"><input type="hidden" name="_token" value="' + csrfToken + '"></form>';
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

    $('.data-skip').on('click', function (e) {
        e.preventDefault();
        $('#form-skip-' + $(this).data('form')).submit();
    });

    $("#table").on("click", ".data-delete", function(e) {
        if (!confirm('Are you sure to want to delete this item?')) return;
        e.preventDefault();
        $('#form-delete-' + $(this).data('form')).submit();
    });

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

function booleanToTableIcon (data, type) {
    if (type == 'filter') {
        return  (data ? 'Yes' : 'No');
    }
    return (  data
            ? '<i class="fa fa-check-square text-success" title="Yes"></i>'
            : '<i class="fa fa-square text-danger" title="No"></i>');
}
