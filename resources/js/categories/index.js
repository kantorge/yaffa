require( 'datatables.net' );
require( 'datatables.net-bs' );

$(document).ready( function () {
    var csrfToken = $('meta[name="csrf-token"]').attr('content');
    categories = categories.map(c => { c.parent = c.parent || {name: ''};return c;});

    $('#table').DataTable({
        data: categories,
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
            data: "parent.name",
            title: "Parent category"
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
                    '<a href="' + route('categories.edit', data) + '" class="btn btn-xs btn-primary"><i class="fa fa-edit" title="Edit"></i></a> ' +
                    '<button class="btn btn-xs btn-danger data-delete" data-id="' + data + '" type="button"><i class="fa fa-trash" title="Delete"></i></button> ';
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
        form.action = route('categories.destroy', {category: this.dataset.id});
        form.submit();
    });
});
