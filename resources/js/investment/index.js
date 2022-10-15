require('datatables.net-bs');
import * as dataTableHelpers from './../components/dataTableHelper';

const dataTableSelector = '#table';

window.table = $(dataTableSelector).DataTable({
    data: window.investments.map(c => { c.investment_price_provider = c.investment_price_provider || { name: '' }; return c; }),
    columns: [
        {
            data: "id",
            title: __("Id"),
        },
        {
            data: "name",
            title: __("Name"),
        },
        {
            data: "active",
            title: __("Active"),
            render: function (data, type) {
                return dataTableHelpers.booleanToTableIcon(data, type);
            },
            className: "text-center activeIcon",
        },
        {
            data: "symbol",
            title: __("Symbol"),
        },
        {
            data: "isin",
            title: __("ISIN number"),
        },
        {
            data: "investment_group.name",
            title: __("Investment group"),
        },
        {
            data: "currency.name",
            title: __("Currency"),
        },
        {
            data: "investment_price_provider_name",
            title: __("Price provider"),
        },
        {
            data: "auto_update",
            title: __("Automatic update"),
            render: function (data, type) {
                return dataTableHelpers.booleanToTableIcon(data, type);
            },
            className: "text-center",
        },
        {
            data: "id",
            title: __("Actions"),
            render: function (data) {
                return '<a href="' + route('investment.edit', data) + '" class="btn btn-xs btn-primary"><i class="fa fa-edit" title="' + __('Edit') + '"></i></a> ' +
                       '<button class="btn btn-xs btn-danger data-delete" data-id="' + data + '" type="button"><i class="fa fa-trash" title="' + __('Delete') + '"></i></button> ';
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
                    alert(__('Error changing investment active state'));
                },
                complete: function (_data) {
                    // Re-render row
                    row.invalidate();
                }
            });
        });
    }
});

dataTableHelpers.initializeDeleteButtonListener(dataTableSelector, 'investment.destroy');

// Listeners for button filter(s)
$('input[name=active]').on("change", function() {
    table.column(2).search(this.value).draw();
});
