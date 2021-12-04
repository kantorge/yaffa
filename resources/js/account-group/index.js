require( 'datatables.net' );
require( 'datatables.net-bs' );

$(function() {
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
                return '<a href="' + route('account-group.edit', data) + '" class="btn btn-xs btn-primary"><i class="fa fa-fw fa-edit" title="Edit"></i></a> ' +
                       '<button class="btn btn-xs btn-danger data-delete" data-id="' + data + '" type=submit"><i class="fa fa-fw fa-trash" title="Delete"></i></button> ';
            },
            orderable: false
        }
        ],
        order: [[ 1, 'asc' ]]
    });

    $("#table").on("click", ".data-delete", function() {
        if (!confirm('Are you sure to want to delete this item?')) {
            return;
        }

        let form = document.getElementById('form-delete');
        form.action = route('account-group.destroy', this.dataset.id);
        form.submit();
    });
});
