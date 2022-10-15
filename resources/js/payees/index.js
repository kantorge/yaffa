require('datatables.net-bs');
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

window.table = $('#table').DataTable({
    data: payees,
    columns: [
        {
            data: "id",
            title: "Id"
        },
        {
            data: "name",
            title: "Name"
        },
        {
            data: "active",
            title: "Active",
            render: function (data, type) {
                return dataTableHelpers.booleanToTableIcon(data, type);
            },
            className: "text-center activeIcon",
        },
        {
            data: "category_full_name",
            title: "Default category",
            render: function(data) {
                return (data ? data : 'Not set');
            }
        },
        {
            // Display count of associated transactions
            data: "transactions_count",
            title: "Transactions",
            render: function(data, type) {
                if (type === 'display') {
                    return (data > 0 ? data : 'Never used');
                }
                return data;
            }
        },
        {
            // Display first transaction date
            data: "transactions_min_date",
            title: "First transaction",
            render: function(data, type) {
                if (type === 'display') {
                    return (data ? data.toLocaleDateString('Hu-hu') : 'Never used');
                }

                return (data ? data.toISOString() : null);
            }
        },
        {
            // Display last transaction date
            data: "transactions_max_date",
            title: "Last transaction",
            render: function(data, type) {
                if (type === 'display') {
                    return (data ? data.toLocaleDateString('Hu-hu') : 'Never used');
                }

                return (data ? data.toISOString() : null);
            }
        },
        {
            data: 'import_alias',
            title: 'Import alias',
            render: function(data, type) {
                if (type === 'display') {
                    return (data ? data.replace('\n', '<br>') : 'Not set');
                }
                return data;
            }
        },
        {
            data: "id",
            title: "Actions",
            render: function(data) {
                return  '<a href="' + route('account-entity.edit', {type: 'payee', account_entity: data}) + '" class="btn btn-xs btn-primary"><i class="fa fa-edit" title="Edit"></i></a> ' +
                        '<button class="btn btn-xs btn-danger data-delete" data-id="' + data + '" type="button"><i class="fa fa-trash" title="Delete"></i></button> ' +
                        '<a href="' + route('payees.merge.form', {payeeSource: data}) + '" class="btn btn-xs btn-primary"><i class="fa fa-random" title="Merge into an other payee"></i></a> ';
            },
            orderable: false
        }
    ],
    createdRow: function(row, data) {
        if (!data.category_full_name) {
            $('td:eq(3)', row).addClass("text-muted text-italic");
        }
        if (data.transactions_count === 0) {
            $('td:eq(4)', row).addClass("text-muted text-italic");
        }
        if (!data.transactions_min_date) {
            $('td:eq(5)', row).addClass("text-muted text-italic");
        }
        if (!data.transactions_max_date) {
            $('td:eq(6)', row).addClass("text-muted text-italic");
        }
    },
    order: [[ 1, 'asc' ]],
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

$("#table").on("click", ".data-delete", function() {
    if (!confirm('Are you sure to want to delete this item?')) {
        return;
    }

    let form = document.getElementById('form-delete');
    form.action = route('account-entity.destroy', {type: 'payee', account_entity: this.dataset.id});
    form.submit();
});

// Listeners for button filter(s)
$('input[name=active]').on("change", function() {
    table.column(2).search(this.value).draw();
});
