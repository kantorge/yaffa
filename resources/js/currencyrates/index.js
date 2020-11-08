require( 'datatables.net' );
require( 'datatables.net-bs4' );

$(function () {
    var csrfToken = $('meta[name="csrf-token"]').attr('content');

    $('#table').DataTable({
        data: currencyRates,
        columns: [
        {
            data: "date",
            title: "Date"
        },
        {
            data: "rate",
            title: "Rate"
        },
        {
            data: "id",
            title: "Actions",
            render: function ( data, type, row, meta ) {
                return '' +
                       '<a href="' + row.edit_url +'" class="btn btn-sm btn-primary"><i class="fa fa-edit" title="Edit"></i></a> ' +
                       //base currency cannot be deleted
                       ( !row.base
                         ? '<button class="btn btn-sm btn-danger data-delete" data-form="' + row.id + '"><i class="fa fa-trash" title="Delete"></i></button> ' +
                           '<form id="form-delete-' + row.id + '" action="' + row.delete_url + '" method="POST" style="display: none;"><input type="hidden" name="_method" value="DELETE"><input type="hidden" name="_token" value="' + csrfToken + '"></form>'
                         : '');
            },
            orderable: false
        }
        ],
        order: [[ 1, 'asc' ]]
    });

    $('.data-delete').on('click', function (e) {
        if (!confirm('Are you sure to want to delete this item?')) return;
        e.preventDefault();
        $('#form-delete-' + $(this).data('form')).submit();
    });
});
