const { filter } = require('@amcharts/amcharts4/.internal/core/utils/Iterator');

require( 'datatables.net' );
require( 'datatables.net-bs' );

//var tableRunningTotal = 0;

$(function() {
    var csrfToken = $('meta[name="csrf-token"]').attr('content');
    var numberRenderer = $.fn.dataTable.render.number( '&nbsp;', ',', 0 ).display;

    //define some settings, that are common for the two tables
    var dtColumnSettingPayee = {
        title: 'Payee',
        render: function ( data, type, row, meta ) {
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
            } else if (row.transaction_type == 'Opening balance') {
                return 'Opening balance';
            }
            return null;
        },
    };
    var dtColumnSettingCategories = {
        title: "Category",
        render: function ( data, type, row, meta ) {
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
    };
    var dtColumnSettingComment = {
        data: "comment",
        title: "Comment",
        render: function(data, type, row, meta){
            if(type === 'display'){
               data = truncateString(data, 20);
            }

            return data;
         },
        createdCell: function (td, cellData, rowData, row, col) {
            $(td).prop('title', cellData);
        }
    };
    var dtColumnSettingTags = {
        data: "tags",
        title: "Tags",
        render: function ( data, type, row, meta ) {
            return data.join(', ');
        }
    }

    $('#historyTable').DataTable({
        data: transactionData,
        columns: [
            {
                data: "date",
                title: "Date"
            },
            {
                data: "reconciled",
                title: '<span title="Reconciled">R</span>',
                className: "text-center",
                render: function ( data, type, row, meta ) {
                    if (type == 'filter') {
                        return  (   !row.schedule
                                 && (row.transaction_type == 'Standard' || row.transaction_type == 'Investment')
                                ?   (row.reconciled == 1
                                        ? 'Reconciled'
                                        : 'Uncleared'
                                    )
                                : 'Unavailable'
                            );
                    }
                    return  (   !row.schedule
                             && (row.transaction_type == 'Standard' || row.transaction_type == 'Investment')
                                ?   (row.reconciled == 1
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
                render: function ( data, type, row, meta ) {
                    return (row.transaction_operator == 'minus' ? numberRenderer(row.amount_from) : null);
                },
            },
            {
                title: "Deposit",
                render: function ( data, type, row, meta ) {
                    return (row.transaction_operator == 'plus' ? numberRenderer(row.amount_to) : null);
                },
            },
            {
                data: 'running_total',
                title: 'Running total',
                render: function ( data, type, row, meta ) {
                    return numberRenderer(data);
                },
                createdCell: function (td, cellData, rowData, row, col) {
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
                render: function ( data, type, row, meta ) {
                    if (row.transaction_type == 'Opening balance') {
                        return null;
                    }
                    if (row.schedule) {
                        if (row.schedule_is_first) {
                            return  '' +
                                    '<a href="' + urlEnterWithEditStandard.replace('#ID#', data) + '" class="btn btn-xs btn-success"><i class="fa fa-fw fa-pencil" title="Edit and insert instance"></i></a> ' +
                                    '<button class="btn btn-xs btn-warning data-skip" data-form="' + data + '"><i class="fa fa-fw fa-forward" title=Skip current schedule"></i></i></button> ' +
                                    '<form id="form-skip-' + data + '" action="' + urlSkip.replace('#ID#', data) + '" method="POST" style="display: none;"><input type="hidden" name="_method" value="PATCH"><input type="hidden" name="_token" value="' + csrfToken + '"></form>';
                        }
                        return null;
                    }

                    return  '' +
                            (row.transaction_type == 'Standard'
                             ? '<a href="' + urlEditStandard.replace('#ID#', data) + '" class="btn btn-xs btn-primary"><i class="fa fa-fw fa-edit" title="Edit"></i></a> ' +
                               '<a href="' + urlCloneStandard.replace('#ID#', data) + '" class="btn btn-xs btn-primary"><i class="fa fa-fw fa-clone" title="Clone"></i></a> '
                             : '<a href="' + urlEditInvestment.replace('#ID#', data) + '" class="btn btn-xs btn-primary"><i class="fa fa-fw fa-edit" title="Edit"></i></a> ' +
                               '<a href="' + urlCloneInvestment.replace('#ID#', data) + '" class="btn btn-xs btn-primary"><i class="fa fa-fw fa-clone" title="Clone"></i></a> ' ) +
                            '<button class="btn btn-xs btn-danger data-delete" data-form="' + data + '"><i class="fa fa-fw fa-trash" title="Delete"></i></button> ' +
                            '<form id="form-delete-' + data + '" action="' + urlDelete.replace('#ID#', data) + '" method="POST" style="display: none;"><input type="hidden" name="_method" value="DELETE"><input type="hidden" name="_token" value="' + csrfToken + '"></form>';
                },
                orderable: false
            }
        ],
        createdRow: function( row, data, dataIndex ) {
            if (data.schedule) {
                $(row).addClass('text-muted text-italic');
            }
        },
        order: [
            [ 0, "asc" ]
        ],
        deferRender:    true,
        scrollY:        '400px',
        scrollCollapse: true,
        scroller:       true,
        stateSave:      true,
        processing:     true,
        paging:         false,
        initComplete : function() {
            $("#historyTable").on("click","i.reconcile",function() {
                if ($(this).hasClass("fa-spinner")) {
                    return false;
                }

                var currentState = $(this).data("reconciled");

                $(this).removeClass().addClass('fa fa-spinner fa-spin');

                $.ajax ({
                    type: 'PUT',
                    url: '/api/transaction/' + $(this).data("id") + '/reconciled/' + (!currentState ? 1 : 0),
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
        }
    });

    $('#scheduleTable').DataTable({
        data: scheduleData,
        columns: [
            {
                data: "next_date",
                title: "Next date"
            },
            dtColumnSettingPayee,
            dtColumnSettingCategories,
            {
                title: "Withdrawal",
                render: function ( data, type, row, meta ) {
                    return (row.transaction_operator == 'minus' ? numberRenderer(row.amount_from) : null);
                },
            },
            {
                title: "Deposit",
                render: function ( data, type, row, meta ) {
                    return (row.transaction_operator == 'plus' ? numberRenderer(row.amount_to) : null);
                },
            },
            dtColumnSettingComment,
            dtColumnSettingTags,
            {
                data: 'id',
                title: "Actions",
                render: function ( data, type, row, meta ) {
                    return  '' +
                            '<a href="' + urlEnterWithEditStandard.replace('#ID#', data) + '" class="btn btn-xs btn-success"><i class="fa fa-fw fa-pencil" title="Edit and insert instance"></i></a> ' +
                            '<button class="btn btn-xs btn-warning data-skip" data-form="' + data + '"><i class="fa fa-fw fa-forward" title=Skip current schedule"></i></i></button> ' +
                            '<form id="form-skip-' + data + '" action="' + urlSkip.replace('#ID#', data) + '" method="POST" style="display: none;"><input type="hidden" name="_method" value="PATCH"><input type="hidden" name="_token" value="' + csrfToken + '"></form>' +
                            '<a href="' + (row.transaction_type == 'Standard' ? urlEditStandard : urlEditInvestment).replace('#ID#', data) + '" class="btn btn-xs btn-primary"><i class="fa fa-fw fa-edit" title="Edit"></i></a> ' +
                            '<button class="btn btn-xs btn-danger data-delete" data-form="' + data + '"><i class="fa fa-fw fa-trash" title="Delete"></i></button> ' +
                            '<form id="form-delete-' + data + '" action="' + urlDelete.replace('#ID#', data) + '" method="POST" style="display: none;"><input type="hidden" name="_method" value="DELETE"><input type="hidden" name="_token" value="' + csrfToken + '"></form>';
                },
                orderable: false
            }
        ],

        createdRow: function( row, data, dataIndex ) {
            var nextDate = new Date(data.next_date.replace( /(\d{4})-(\d{2})-(\d{2})/, "$2/$3/$1"));
            if ( nextDate  < new Date(new Date().setHours(0,0,0,0)) ) {
                $(row).addClass('danger');
            } else if ( nextDate  < new Date(new Date().setHours(24,0,0,0)) ) {
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
        stateSave:      true,
        processing:     true,
        paging:         false,
    });

    $('.data-skip').on('click', function (e) {
        e.preventDefault();
        $('#form-skip-' + $(this).data('form')).submit();
    });

    $("#historyTable, #scheduleTable").on("click", ".data-delete", function(e) {
        if (!confirm('Are you sure to want to delete this item?')) return;
        e.preventDefault();
        $('#form-delete-' + $(this).data('form')).submit();
    });
});

// DataTables helper: truncate a string
function truncateString(str, max, add){
    add = add || '...';
    return (typeof str === 'string' && str.length > max ? str.substring(0, max) + add : str);
 };
