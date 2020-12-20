require( 'datatables.net' );
require( 'datatables.net-bs4' );

//var tableRunningTotal = 0;

$(function() {
    var csrfToken = $('meta[name="csrf-token"]').attr('content');

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
            {
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

                    } else if (row.transaction_type == 'Opening balance') {
                        return 'Opening balance';
                    }
                    return null;
                },
            },
            {
                data: "categories",
                title: "Category",
                render: function ( data, type, row, meta ) {
                    //empty
                    if (data.length == 0) {
                        return '';
                    }

                    if (data.length > 1) {
                        return 'Split transaction';
                    } else {
                        return data[0];
                    }
                },
                orderable: false
            },
            {
                title: "Withdrawal",
                render: function ( data, type, row, meta ) {
                    return (row.transaction_operator == 'minus' ? row.amount_from : null);
                },
            },
            {
                title: "Deposit",
                render: function ( data, type, row, meta ) {
                    return (row.transaction_operator == 'plus' ? row.amount_to : null);
                },
            },
            {
                data: 'running_total',
                title: 'Running total',
                createdCell: function (td, cellData, rowData, row, col) {
                    if (cellData < 0) {
                        $(td).addClass('text-danger');
                    }
                }
            },
            {
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
            },
            {
                data: "tags",
                title: "Tags",
                render: function ( data, type, row, meta ) {
                    return data.join(', ');
                }
            },
            {
                title: "Actions",
                render: function ( data, type, row, meta ) {
                    if (row.transaction_type == 'Opening balance') {
                        return null;
                    }
                    if (row.schedule) {
                        if (row.schedule_is_first) {
                            return  '' +
                                    '<a href="' + row.enterwithedit_url +'" class="btn btn-xs btn-success"><i class="fa fa-pen" title="Edit and insert instance"></i></a> ' +
                                    '<a href="' + row.skip_url +'" class="btn btn-xs btn-warning"><i class="fa fa-forward" title=Skip current schedule"></i></a> ';
                        }
                        return null;
                    }

                    return  '' +
                            '<a href="' + row.edit_url +'" class="btn btn-xs btn-primary"><i class="fa fa-edit" title="Edit"></i></a> ' +
                            '<button class="btn btn-xs btn-danger data-delete" data-form="' + row.id + '"><i class="fa fa-trash" title="Delete"></i></button> ' +
                            '<form id="form-delete-' + row.id + '" action="' + row.delete_url + '" method="POST" style="display: none;"><input type="hidden" name="_method" value="DELETE"><input type="hidden" name="_token" value="' + csrfToken + '"></form>';
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

                $(this).removeClass().addClass('fa fa-spinner fa-spin reconcile');

                $.ajax ({
                    type: 'PUT',  //GET
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
            {
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

                    } else if (row.transaction_type == 'Opening balance') {
                        return 'Opening balance';
                    }
                    return '';
                },
            },
            {
                data: "categories",
                title: "Category",
                render: function ( data, type, row, meta ) {
                    //empty
                    if (data.length == 0) {
                        return '';
                    }

                    if (data.length > 1) {
                        return 'Split transaction';
                    } else {
                        return data[0];
                    }
                },
                orderable: false
            },
            {
                title: "Withdrawal",
                render: function ( data, type, row, meta ) {
                    return (row.transaction_operator == 'minus' ? row.amount_from : null);
                },
            },
            {
                title: "Deposit",
                render: function ( data, type, row, meta ) {
                    return (row.transaction_operator == 'plus' ? row.amount_to : null);
                },
            },
            {
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
            },
            {
                data: "tags",
                title: "Tags",
                render: function ( data, type, row, meta ) {
                    return data.join(', ');
                }
            },
            {
                title: "Actions",
                render: function ( data, type, row, meta ) {
                    return  '' +
                            '<a href="' + row.edit_url +'" class="btn btn-sm btn-primary"><i class="fa fa-edit" title="Edit"></i></a> ' +
                            '<button class="btn btn-sm btn-danger data-delete" data-form="' + row.id + '"><i class="fa fa-trash" title="Delete"></i></button> ' +
                            '<form id="form-delete-' + row.id + '" action="' + row.delete_url + '" method="POST" style="display: none;"><input type="hidden" name="_method" value="DELETE"><input type="hidden" name="_token" value="' + csrfToken + '"></form>';
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

    $('.data-delete').on('click', function (e) {
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