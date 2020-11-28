require( 'datatables.net' );
require( 'datatables.net-bs4' );

$(function() {

    $('#table').DataTable({
        data: investments.map(c => { c.investment_price_provider = c.investment_price_provider || {name: ''};return c;}),
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
            data: "symbol",
            title: "Symbol"
        },
        {
            data: "investment_group.name",
            title: "Investment group"
        },
        {
            data: "currency.name",
            title: "Currency"
        },
        {
            data: "investment_price_provider.name",
            title: "Price provider"
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
            data: "id",
            title: "Actions",
            render: function ( data, type, row, meta ) {
                return '' +
                        '<a href="' + row.edit_url +'" class="btn btn-sm btn-primary"><i class="fa fa-edit" title="Edit"></i></a> ' +
                        '<button class="btn btn-sm btn-danger data-delete" data-form="' + row.id + '"><i class="fa fa-trash" title="Delete"></i></button> ' +
                        '<form id="form-delete-' + row.id + '" action="' + row.delete_url + '" method="POST" style="display: none;"><input type="hidden" name="_method" value="DELETE"><input type="hidden" name="_token" value="{{ csrf_token() }}"></form>';
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