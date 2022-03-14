require( 'datatables.net' );
require( 'datatables.net-bs' );

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
            data: "symbol",
            title: "Symbol"
        },
        {
            data: "isin",
            title: "ISIN number"
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
                    '<a href="' + route('investment.edit', data) + '" class="btn btn-sm btn-primary"><i class="fa fa-edit" title="Edit"></i></a> ' +
                    '<button class="btn btn-sm btn-danger data-delete" data-id="' + data + '" type="button"><i class="fa fa-trash" title="Delete"></i></button> ';
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
        form.action = route('investment.destroy', {investment: this.dataset.id});
        form.submit();
    });
});
