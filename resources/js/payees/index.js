require('datatables.net-bs5');
require("datatables.net-responsive-bs5");

import * as dataTableHelpers from './../components/dataTableHelper';

// Loop payees and prepare data for datatable
window.payees = window.payees.map(function(payee) {
    // Parse first date if it exists
    if (payee.transactions_min_date) {
        payee.transactions_min_date = new Date(Date.parse(payee.transactions_min_date));
    }
    // Parse last date if it exists
    if (payee.transactions_max_date) {
        payee.transactions_max_date = new Date(Date.parse(payee.transactions_max_date));
    }

    return payee;
});

const dataTableSelector = '#table';

window.table = $(dataTableSelector).DataTable({
    data: payees,
    columns: [
        {
            data: "name",
            title: __('Name')
        },
        {
            data: "active",
            title: __("Active"),
            render: function (data, type) {
                return dataTableHelpers.booleanToTableIcon(data, type);
            },
            className: "text-center activeIcon",
        },
        {
            data: "category_full_name",
            title: __("Default category"),
            render: function(data) {
                return (data ? data : __('Not set'));
            }
        },
        {
            // Display count of associated transactions
            data: "transactions_count",
            title: __("Transactions"),
            render: function(data, type) {
                if (type === 'display') {
                    return (data > 0 ? data : __('Never used'));
                }
                return data;
            },
            type: 'num',
        },
        {
            // Display first transaction date
            data: "transactions_min_date",
            title: __("First transaction"),
            render: function(data, type) {
                if (type === 'display') {
                    return (data ? data.toLocaleDateString(window.YAFFA.locale) : __('Never used'));
                }

                return data || null;
            },
            type: 'date',
        },
        {
            // Display last transaction date
            data: "transactions_max_date",
            title: __("Last transaction"),
            render: function(data, type) {
                if (type === 'display') {
                    return (data ? data.toLocaleDateString(window.YAFFA.locale) : __('Never used'));
                }

                return data || null;
            },
            type: 'date',
        },
        {
            data: 'import_alias',
            title: __('Import alias'),
            render: function(data, type) {
                if (type === 'display') {
                    return (data ? data.replace('\n', '<br>') : __('Not set'));
                }
                return data;
            }
        },
        {
            data: "id",
            title: __("Actions"),
            render: function(data) {
                return  '<a href="' + route('account-entity.edit', {type: 'payee', account_entity: data}) + '" class="btn btn-sm btn-primary" title="' + __('Edit') + '"><i class="fa fa-edit"></i></a> ' +
                        '<button class="btn btn-sm btn-danger data-delete" data-id="' + data + '" type="button" title="' + __('Delete') + '"><i class="fa fa-trash"></i></button> ' +
                        '<a href="' + route('payees.merge.form', {payeeSource: data}) + '" class="btn btn-sm btn-primary" title="' + __('Merge into an other payee') + '"><i class="fa fa-random"></i></a> ';
            },
            className: "dt-nowrap",
            orderable: false,
            searchable: false,
        }
    ],
    createdRow: function(row, data) {
        if (!data.category_full_name) {
            $('td:eq(2)', row).addClass("text-muted text-italic");
        }
        if (data.transactions_count === 0) {
            $('td:eq(3)', row).addClass("text-muted text-italic");
        }
        if (!data.transactions_min_date) {
            $('td:eq(4)', row).addClass("text-muted text-italic");
        }
        if (!data.transactions_max_date) {
            $('td:eq(5)', row).addClass("text-muted text-italic");
        }
        if (!data.import_alias) {
            $('td:eq(6)', row).addClass("text-muted text-italic");
        }
    },
    order: [[ 0, 'asc' ]],
    responsive: true,
    initComplete : function(settings) {
        $(settings.nTable).on("click", "td.activeIcon > i", function() {
            var row = $(settings.nTable).DataTable().row( $(this).parents('tr') );

            // Do not request change if previous request is still in progress
            if ($(this).hasClass("fa-spinner")) {
                return false;
            }

            // Change icon to spinner
            $(this).removeClass().addClass('fa fa-spinner fa-spin');

            // Send request to change payee active state
            $.ajax ({
                type: 'PUT',
                url: '/api/assets/accountentity/' + row.data().id + '/active/' + (row.data().active ? 0 : 1),
                data: {
                    "_token": csrfToken,
                },
                dataType: "json",
                context: this,
                success: function (data) {
                    // Update row in table data souerce
                    payees.filter(payee => payee.id === data.id)[0].active = data.active;
                },
                error: function (_data) {
                    alert('Error changing payee active state');
                },
                complete: function(_data) {
                    // Re-render row
                    row.invalidate();
                }
            });
        });
    }
});

$(dataTableSelector).on("click", ".data-delete", function() {
    if (!confirm('Are you sure to want to delete this item?')) {
        return;
    }

    let form = document.getElementById('form-delete');
    form.action = route('account-entity.destroy', {type: 'payee', account_entity: this.dataset.id});
    form.submit();
});

// Listeners for button filter(s)
$('input[name=active]').on("change", function() {
    table.column(1).search(this.value).draw();
});
