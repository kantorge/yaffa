require('datatables.net');
require('datatables.net-bs');
import * as dataTableHelpers from './../components/dataTableHelper';

$('#table').DataTable({
    data: window.investments.map(c => { c.investment_price_provider = c.investment_price_provider || { name: '' }; return c; }),
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
                return dataTableHelpers.booleanToTableIcon(data, type);
            },
            className: "text-center activeIcon",
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
            data: "investment_price_provider_name",
            title: "Price provider"
        },
        {
            data: "auto_update",
            title: "Auto update",
            render: function (data, type) {
                return dataTableHelpers.booleanToTableIcon(data, type);
            },
            className: "text-center",
        },
        {
            data: "id",
            title: "Actions",
            render: function (data) {
                return '' +
                    '<a href="' + route('investment.edit', data) + '" class="btn btn-xs btn-primary"><i class="fa fa-edit" title="Edit"></i></a> ' +
                    '<button class="btn btn-xs btn-danger data-delete" data-id="' + data + '" type="button"><i class="fa fa-trash" title="Delete"></i></button> ';
            },
            orderable: false
        }
    ],
    order: [[1, 'asc']],
    initComplete: function (settings) {
        $(settings.nTable).on("click", "td.activeIcon > i:not(.inProgress)", function () {
            var row = $(settings.nTable).DataTable().row($(this).parents('tr'));

            // Change icon to spinner
            $(this).removeClass().addClass('fa fa-spinner fa-spin inProgress');

            // Send request to change payee active state
            $.ajax({
                type: 'PUT',
                url: '/api/assets/investment/' + row.data().id + '/active/' + (row.data().active ? 0 : 1),
                data: {
                    "_token": csrfToken,
                },
                dataType: "json",
                context: this,
                success: function (data) {
                    // Update row in table data souerce
                    investments.filter(investment => investment.id === data.id)[0].active = data.active;
                },
                error: function (_data) {
                    alert('Error changing investment active state');
                },
                complete: function (_data) {
                    // Re-render row
                    row.invalidate();
                }
            });
        });
    }
});

$("#table").on("click", ".data-delete", function () {
    if (!confirm('Are you sure to want to delete this item?')) {
        return;
    }

    let form = document.getElementById('form-delete');
    form.action = route('investment.destroy', { investment: this.dataset.id });
    form.submit();
});
