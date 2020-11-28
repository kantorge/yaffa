require( 'datatables.net' );
require( 'datatables.net-bs4' );

$(function () {
    var csrfToken = $('meta[name="csrf-token"]').attr('content');

    $('#table').DataTable({
        data: currencies,
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
            data: "iso_code",
            title: "ISO Code"
        },
        {
            data: "num_digits",
            title: "Number of decimal digits"
        },
        {
            data: "suffix",
            title: "Suffix"
        },
        {
            data: "base",
            title: "Base currency",
            render: function ( data, type, row, meta ) {
                if (type == 'filter') {
                    return  (data ? 'Yes' : 'No');
                }
                return (  data
                        ? '<i class="fa fa-check-square text-success" title="Yes"></i>'
                        : '');
            },
            className: "text-center",
        },
        {
            data: "auto_update",
            title: "Auto update",
            render: function ( data, type, row, meta ) {
                if (type == 'filter') {
                    return  (data ? 'Yes' : 'No');
                }
                return (  data
                        ? '<i class="fa fa-check-square text-success" title="Yes"></i>'
                        : '<i class="far fa-square text-danger" title="No"></i>');
            },
            className: "text-center",
        },
        {
            data: "latest_rate",
            title: "Latest rate to base currency"
        },
        {
            data: "id",
            title: "Actions",
            render: function ( data, type, row, meta ) {
                return '' +
                       '<a href="' + row.edit_url +'" class="btn btn-sm btn-primary"><i class="fa fa-edit" title="Edit"></i></a> ' +
                       //base currency cannot be deleted
                       ( !row.base
                         ? '<a href="/currencyrates/' + row.id + '/' + baseCurrency.id + '" class="btn btn-sm btn-info"><i class="fa fa-chart-line" title="Rates"></i></a> ' +
                           '<button class="btn btn-sm btn-danger data-delete" data-form="' + row.id + '"><i class="fa fa-trash" title="Delete"></i></button> ' +
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
