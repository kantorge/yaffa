require( 'datatables.net' );
require( 'datatables.net-bs' );

$(function () {
    var csrfToken = $('meta[name="csrf-token"]').attr('content');
    window.baseCurrency = window.baseCurrency || {};

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
                        : '<i class="fa fa-square text-danger" title="No"></i>');
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
                        '<a href="' + editUrl.replace('#ID#', data) + '" class="btn btn-sm btn-primary"><i class="fa fa-edit" title="Edit"></i></a> ' +
                       //base currency cannot be deleted
                       ( !row.base
                         ? '<a href="/currencyrates/' + data + '/' + baseCurrency.id + '" class="btn btn-sm btn-info"><i class="fa fa-line-chart" title="Rates"></i></a> ' +
                           '<button class="btn btn-sm btn-danger data-delete" data-form="' + data + '"><i class="fa fa-trash" title="Delete"></i></button> ' +
                           '<form id="form-delete-' + data + '" action="' + deleteUrl.replace('#ID#', data) + '" method="POST" style="display: none;"><input type="hidden" name="_method" value="DELETE"><input type="hidden" name="_token" value="' + csrfToken + '"></form>'
                         : '');
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
