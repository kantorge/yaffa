require( 'datatables.net' );
require( 'datatables.net-bs' );

$(document).ready( function () {
    var csrfToken = $('meta[name="csrf-token"]').attr('content');

    $('#table').DataTable({
        data: tags,
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
            data: "id",
            title: "Actions",
            render: function (data) {
                return '' +
                    '<a href="' + route('tag.edit', data) + '" class="btn btn-xs btn-primary"><i class="fa fa-fw fa-edit" title="Edit"></i></a> ' +
                    '<button class="btn btn-xs btn-danger data-delete" data-form="' + data + '"><i class="fa fa-fw fa-trash" title="Delete"></i></button> ' +
                    '<form id="form-delete-' + data + '" action="' + route('tag.destroy', data) + '" method="POST" style="display: none;"><input type="hidden" name="_method" value="DELETE"><input type="hidden" name="_token" value="' + csrfToken + '"></form>';
            },
            orderable: false
        }
        ],
        order: [[ 1, 'asc' ]]
    });

    $("#table").on("click", ".data-delete", function(e) {
        if (!confirm('Are you sure to want to delete this tag? It will be removed from all associated transactions, and this action cannot be undone.')) return;
        e.preventDefault();
        $('#form-delete-' + $(this).data('form')).submit();
    });
});
