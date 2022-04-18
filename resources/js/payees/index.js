require( 'datatables.net' );
require( 'datatables.net-bs' );

$(function () {
    var csrfToken = $('meta[name="csrf-token"]').attr('content');

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
                render: function(data, type) {
                    if (type == 'filter') {
                        return  (data ? 'Yes' : 'No');
                    }
                    return (  data
                            ? '<i class="fa fa-check-square text-success" title="Yes"></i>'
                            : '<i class="fa fa-square text-danger" title="No"></i>');
                },
                className: "text-center",
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
                            '<button class="btn btn-xs btn-danger data-delete" data-form="' + data + '"><i class="fa fa-trash" title="Delete"></i></button> ' +
                            '<form id="form-delete-' + data + '" action="' + route('account-entity.destroy', {type: 'payee', account_entity: data}) + '" method="POST" style="display: none;"><input type="hidden" name="_method" value="DELETE"><input type="hidden" name="_token" value="' + csrfToken + '"></form>' +
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
        order: [[ 1, 'asc' ]]
    });

    $("#table").on("click", ".data-delete", function(e) {
        if (!confirm('Are you sure to want to delete this item?')) return;
        e.preventDefault();
        $('#form-delete-' + $(this).data('form')).submit();
    });
});
