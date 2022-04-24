require( 'datatables.net' );
require( 'datatables.net-bs' );
import * as dataTableHelpers from './../components/dataTableHelper';

const csrfToken = $('meta[name="csrf-token"]').attr('content');

$('#table').DataTable({
    data: payees,
    columns: [
        {
            data: "id",
            title: "ID"
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
            data: "config.category_full_name",
            title: "Default category",
            render: function(data) {
                return (data ? data : 'Not set');
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
        if (!data.config.category_full_name) {
            $('td:eq(3)', row).addClass("text-muted text-italic");
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
