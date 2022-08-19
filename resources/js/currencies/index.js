require('datatables.net');
require('datatables.net-bs');

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
            render: function (data, type) {
                if (type == 'filter') {
                    return (data ? 'Yes' : 'No');
                }
                return (data
                    ? '<i class="fa fa-check-square text-success" title="Yes"></i>'
                    : '');
            },
            className: "text-center",
        },
        {
            data: "auto_update",
            title: "Auto update",
            render: function (data, type) {
                if (type == 'filter') {
                    return (data ? 'Yes' : 'No');
                }
                return (data
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
            render: function (data, _type, row) {
                return '' +
                    '<a href="' + route('currencies.edit', data) + '" class="btn btn-xs btn-primary"><i class="fa fa-edit" title="Edit"></i></a> ' +
                    // Base currency cannot be deleted or set as default
                    (!row.base
                        ? '<a href="/currencyrates/' + data + '/' + baseCurrency.id + '" class="btn btn-xs btn-info"><i class="fa fa-line-chart" title="Rates"></i></a> ' +
                        '<button class="btn btn-xs btn-danger data-delete" data-id="' + data + '" type="button"><i class="fa fa-trash" title="Delete"></i></button> ' +
                        '<a href="' + route('currencies.setDefault', data) + '" class="btn btn-xs btn-primary"><i class="fa fa-bank" title="Set as default"></i></a>'
                        : '');
            },
            orderable: false
        }
    ],
    order: [[1, 'asc']]
});

$("#table").on("click", ".data-delete", function() {
    if (!confirm('Are you sure to want to delete this item?')) {
        return;
    }

    let form = document.getElementById('form-delete');
    form.action = route('currency.destroy', {investment: this.dataset.id});
    form.submit();
});
