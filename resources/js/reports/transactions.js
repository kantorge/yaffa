// Import external libraries
import 'datatables.net';
import 'datatables.net-bs';
import 'select2';
import { DateRangePicker } from 'vanillajs-datepicker';

// Import dataTable helper functions
import * as dataTableHelpers from './../components/dataTableHelper'

// Initialize an object which checks if preset filters are populated. This is used to trigger initial dataTable content.
let presetFilters = {
    ready: function() {
        for (let key in presetFilters) {
            if (presetFilters[key] === false) {
                return false;
            }
        }
        return true;
    }
};

// Loop filter object keys and populate presetFilters array.
for (let key in filters) {
    presetFilters[key] = false;
}

// Disable table refresh, if any filters are preset
if (!presetFilters.ready()) {
    document.getElementById('reload').setAttribute('disabled','disabled');
}

// Initialize date range picker
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
                // Standard transaction
                if (row.transaction_type === 'Standard') {
                    // Empty
                    if (row.categories.length === 0) {
                        return 'Not set';
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

                return 'Not set';
            },
            orderable: false
        },
        {
            title: 'Amount',
            render: function (data, type, row) {
                // Standard transaction
                if (row.transaction_type === 'Standard') {
                    let prefix = '';
                    if (row.transaction_operator == 'minus') {
                        prefix = '- ';
                    }
                    if (row.transaction_operator == 'plus') {
                        prefix = '+ ';
                    }
                    return prefix + row.amount.toLocalCurrency(row.currency);
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
            render: function (_data, type, row) {
                return dataTableHelpers.commentIcon(row.comment, type) + dataTableHelpers.tagIcon(row.tags, type);
            },
            className: "text-center",
            orderable: false,
        },
        {
            data: 'id',
            title: "Actions",
            render: function(data, _type, row) {
                if (row.transaction_type === 'Standard') {
                    return  dataTableHelpers.dataTablesActionButton(data, 'standardQuickView') +
                            dataTableHelpers.dataTablesActionButton(data, 'standardShow') +
                            dataTableHelpers.dataTablesActionButton(data, 'edit', 'Standard') +
                            dataTableHelpers.dataTablesActionButton(data, 'clone', 'Standard') +
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

// Delete transaction icon functionality
dataTableHelpers.initializeDeleteButton('#dataTable');

// Function to reload table data
function reloadTable() {
    document.getElementById('reload').setAttribute('disabled','disabled');
    table.ajax.reload(function() {
        document.getElementById('reload').removeAttribute('disabled');

        // (Re-)Initialize tooltips in table
        $('[data-toggle="tooltip"]').tooltip();
    });
}

// Reload button functionality
$("#reload").on('click', reloadTable);

$("#clear_dates").on('click', function() {
    dateRangePicker.setDates(
        {clear: true},
        {clear: true}
    );
})

$(".clear-select").on('click', function() {
    $("#" + $(this).data("target")).val(null).trigger('change');
})

// Account filter select2 functionality
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

// Append preset accounts, if any
if (filters.accounts) {
    filters.accounts.forEach(function(account) {
        $.ajax({
            url:  '/api/assets/account/' + account,
            data: {}
        })
        .done(data => {
            $('#select_account')
            .append(new Option(data.name, data.id, true, true))
            .trigger('change')
            .trigger({
                type: 'select2:select',
                params: {
                    data: {
                        id: data.id,
                        name: data.name,
                    }
                }
            });

            // Set account filter to true
            presetFilters.accounts = true;

            // If all preset filters are ready, reload table data
            if (presetFilters.ready()) {
                reloadTable();
            }
        });
    });
}

// Payee select2 functionality
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

// Append preset payees, if any
if (filters.payees) {
    filters.payees.forEach(function(payee) {
        $.ajax({
            url:  '/api/assets/payee/' + payee,
            data: {},
            success: function(data) {
                $('#select_payee')
                .append(new Option(data.name, data.id, true, true))
                .trigger('change')
                .trigger({
                    type: 'select2:select',
                    params: {
                        data: {
                            id: data.id,
                            name: data.name,
                        }
                    }
                });

                // Set payee filter to true
                presetFilters.payees = true;

                // If all preset filters are ready, reload table data
                if (presetFilters.ready()) {
                    reloadTable();
                }
            }
        });
    });
}

// Category select2 functionality
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

// Append preset categories, if any
if (filters.categories) {
    filters.categories.forEach(function(category) {
        $.ajax({
            url:  '/api/assets/category/' + category,
            data: {},
            success: function(data) {
                $('#select_category')
                .append(new Option(data.full_name, data.id, true, true))
                .trigger('change')
                .trigger({
                    type: 'select2:select',
                    params: {
                        data: {
                            id: data.id,
                            name: data.full_name,
                        }
                    }
                });

                // Set category filter to true
                presetFilters.categories = true;

                // If all preset filters are ready, reload table data
                if (presetFilters.ready()) {
                    reloadTable();
                }
            }
        });
    });
}

// Tag select2 functionality
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

// Append preset tags, if any
if (filters.tags) {
    filters.tags.forEach(function(tag) {
        $.ajax({
            url:  '/api/assets/tag/' + tag,
            data: {},
            success: function(data) {
                $('#select_tag')
                .append(new Option(data.name, data.id, true, true))
                .trigger('change')
                .trigger({
                    type: 'select2:select',
                    params: {
                        data: {
                            id: data.id,
                            name: data.name,
                        }
                    }
                });

                // Set tag filter to true
                presetFilters.tags = true;

                // If all preset filters are ready, reload table data
                if (presetFilters.ready()) {
                    reloadTable();
                }
            }
        });
    });
}

import { createApp } from 'vue'

import TransactionShowModal from './../components/TransactionDisplay/Modal.vue'
const app = createApp({})

app.component('transaction-show-modal', TransactionShowModal)

app.mount('#app')
