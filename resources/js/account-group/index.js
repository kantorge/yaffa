require( 'datatables.net' );
require( 'datatables.net-bs' );

$(function() {
    var csrfToken = $('meta[name="csrf-token"]').attr('content');

    $('#table').DataTable({
        data: accountGroups,
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
            data: "id",
            title: "Actions",
            render: function ( data, type, row, meta ) {
                return '' +
                    '<a href="' + route('account-group.edit', data) + '" class="btn btn-sm btn-primary"><i class="fa fa-edit" title="Edit"></i></a> ' +
                    '<button class="btn btn-sm btn-danger data-delete" data-form="' + data + '"><i class="fa fa-trash" title="Delete"></i></button> ' +
                    '<form id="form-delete-' + data + '" action="' + route('account-group.destroy', data) + '" method="POST" style="display: none;"><input type="hidden" name="_method" value="DELETE"><input type="hidden" name="_token" value="' + csrfToken + '"></form>';
            },
            orderable: false
        }
        ],
        order: [[ 1, 'asc' ]]
    });

    $("#table").on("click", ".data-delete", function(e) {
        if (!confirm('Are you sure to want to delete this item?')) return;
        e.preventDefault();
        $('#form-delete-' + $(this).data('form')).submit();
    });
});