import 'datatables.net';
import 'datatables.net-bs';

import 'select2';

import { DateRangePicker } from 'vanillajs-datepicker';
import * as dataTableHelpers from './../components/dataTableHelper'

$(function() {
    const dateRangePicker = new DateRangePicker(
        document.getElementById('dateRangePicker'),
        {
            allowOneSidedRange: true,
            weekStart: 1,
            todayBtn: true,
            todayBtnMode: 1,
            todayHighlight: true,
            format: 'yyyy-mm-dd',
            autohide: true,
            buttonClass: 'btn',
        }
    );

    window.table = $("#dataTable").DataTable({
        ajax:  {
            url: '/api/transactions',
            data: function(d) {
                const dates = dateRangePicker.getDates('yyyy-mm-dd');

                d.categories = $("#select_category").val() || undefined;
                d.payees = $("#select_payee").val() || undefined;
                d.accounts = $("#select_account").val() || undefined;
                d.tags = $("#select_tag").val() || undefined;
                d.date_from = dates[0];
                d.date_to = dates[1];
            },
            dataSrc: function (json) {
                return json.data.map(function(transaction) {
                    transaction.date = new Date(transaction.date);

                    return transaction;
                });
            }
        },
        processing: true,
        language: {
            processing: '<i class="fa fa-spinner fa-spin fa-2x fa-fw"></i><span class="sr-only">Loading...</span>'
        },
        columns: [
            {
                data: "date",
                title: 'Date',
                render: function (data) {
                    if (!data) {
                        return data;
                    }
                    return data.toLocaleDateString('Hu-hu');
                },
                className : "dt-nowrap",
            },
            {
                title: 'Type',
                render: function(data, type, row) {
                    return dataTableHelpers.transactionTypeIcon(row.transaction_type, row.transaction_name);
                },
                className: "text-center",
            },
            {
                title: 'From',
                data: 'account_from_name',
            },
            {
                title: 'To',
                data: 'account_to_name',
            },
            {
                title: "Category",
                render: function (data, type, row) {
                    //standard transaction
                    if (row.transaction_type === 'Standard') {
                        //empty
                        if (row.categories.length === 0) {
                            return '';
                        }

                        if (row.categories.length > 1) {
                            return 'Split transaction';
                        } else {
                            return row.categories[0];
                        }
                    }
                    //investment transaction
                    if (row.transaction_type === 'Investment') {
                        if (!row.quantityOperator) {
                            return row.transaction_name;
                        }
                        if (!row.transactionOperator) {
                            return row.transaction_name + " " + row.quantity;
                        }

                        return row.transaction_name + " " + row.quantity + " @ " + numberRenderer(row.price);
                    }

                    return '';
                },
                orderable: false
            },
            {
                title: 'Amount',
                render: function (data, type, row) {
                    //standard transaction
                    if (row.transaction_type === 'Standard') {
                        let prefix = '';
                        if (row.transaction_operator == 'minus') {
                            prefix = '- ';
                        }
                        if (row.transaction_operator == 'plus') {
                            prefix = '+ ';
                        }
                        return prefix + row.amount;//.toLocalCurrency(row.currency);
                    }
                    // Investment transaction
                    /* not implemented yet
                    if (row.transaction_type === 'Investment') {
                        if (!row.quantityOperator) {
                            return row.transaction_name;
                        }
                        if (!row.transactionOperator) {
                            return row.transaction_name + " " + row.quantity;
                        }

                        return row.transaction_name + " " + row.quantity + " @ " + numberRenderer(row.price);
                    }
                    */

                    return '';
                },
                className : "dt-nowrap",
            },
            {
                title: "Extra",
                render: function (data, type, row) {
                    return dataTableHelpers.commentIcon(row.comment, type) + dataTableHelpers.tagIcon(row.tags, type);
                },
                className: "text-center",
                orderable: false,
            },
            {
                data: 'id',
                title: "Actions",
                render: function(data, type, row) {
                    if (row.transaction_type === 'Standard') {
                        return  dataTableHelpers.dataTablesActionButton(data, 'standardQuickView') +
                                dataTableHelpers.dataTablesActionButton(data, 'standardShow') +
                                dataTableHelpers.dataTablesActionButton(data, 'standardEdit') +
                                dataTableHelpers.dataTablesActionButton(data, 'standardClone') +
                                dataTableHelpers.dataTablesActionButton(data, 'delete');
                    }

                    /* Not implemnted yet
                    return '<a href="' + route('transactions.openInvestment', {transaction: data, action: 'edit'}) + '" class="btn btn-xs btn-primary"><i class="fa fa-fw fa-edit" title="Edit"></i></a> ' +
                               '<a href="' + route('transactions.openInvestment', {transaction: data, action: 'clone'}) + '" class="btn btn-xs btn-primary"><i class="fa fa-fw fa-clone" title="Clone"></i></a> ' +
                               '<button class="btn btn-xs btn-danger data-delete" data-id="' + data + '" type="button"><i class="fa fa-fw fa-trash" title="Delete"></i></button>';
                    */
                },
                orderable: false,
            }
        ]
    });

    // Delete transaction icon
    dataTableHelpers.initializeDeleteButton('#dataTable');

    $("#reload").on('click', function() {
        document.getElementById('reload').setAttribute('disabled','disabled');
        table.ajax.reload(function() {
            document.getElementById('reload').removeAttribute('disabled');

            // (Re-)Initialize tooltips in table
            $('[data-toggle="tooltip"]').tooltip()
        });
    });

    $("#clear_dates").on('click', function() {
        dateRangePicker.setDates(
            {clear: true},
            {clear: true}
        );
    })

    $(".clear-select").on('click', function() {
        $("#" + $(this).data("target")).val(null).trigger('change');
    })

    $('#select_account').select2({
        multiple: true,
        ajax: {
            url: '/api/assets/account',
            dataType: 'json',
            delay: 150,
            data: function (params) {
                return {
                    q: params.term,
                    withInactive: true,
                };
            },
            processResults: function (data) {
                return {
                    results: data,
                };
            },
            cache: true
        },
        selectOnClose: true,
        placeholder: "Select account",
        allowClear: true
    });

    $('#select_payee').select2({
        multiple: true,
        ajax: {
            url: '/api/assets/payee',
            dataType: 'json',
            delay: 150,
            data: function (params) {
                return {
                    q: params.term,
                    withInactive: true,
                };
            },
            processResults: function (data) {
                return {
                    results: data,
                };
            },
            cache: true
        },
        selectOnClose: true,
        placeholder: "Select payee",
        allowClear: true
    });

    $('#select_category').select2({
        multiple: true,
        ajax: {
            url: '/api/assets/category',
            dataType: 'json',
            delay: 150,
            data: function (params) {
                return {
                    q: params.term,
                    withInactive: true,
                };
            },
            processResults: function (data) {
                return {
                    results: data,
                };
            },
            cache: true
        },
        selectOnClose: true,
        placeholder: "Select category",
        allowClear: true
    });

    $('#select_tag').select2({
        multiple: true,
        ajax: {
            url:  '/api/assets/tag',
            dataType: 'json',
            delay: 150,
            data: function (params) {
                return {
                    q: params.term,
                };
            },
            processResults: function (data) {
                return {
                    results: data
                };
            },
            cache: true
        },
        placeholder: "Select tag(s)",
        allowClear: true
    });
});

import { createApp } from 'vue'

import TransactionShowModal from './../components/TransactionDisplay/Modal.vue'
const app = createApp({})

app.component('transaction-show-modal', TransactionShowModal)

app.mount('#app')
