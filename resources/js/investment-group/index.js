require( 'datatables.net' );
require( 'datatables.net-bs' );

$(function() {
    $('#table').DataTable({
        data: investmentGroups,
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
            data: "id",
            title: "Actions",
            render: function (data) {
                return '' +
                    '<a href="' + route('investment-group.edit', data) + '" class="btn btn-xs btn-primary"><i class="fa fa-fw fa-edit" title="Edit"></i></a> ' +
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
        form.action = route('investment-group.destroy', this.dataset.id);
        form.submit();
    });
});
