require('datatables.net-bs');
import {
    booleanToTableIcon,
    genericDataTablesActionButton,
    initializeDeleteButtonListener
} from './../components/dataTableHelper';

const dataTableSelector = '#table';

window.baseCurrency = window.baseCurrency || {};

$(dataTableSelector).DataTable({
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
                return booleanToTableIcon(data, type);
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
                return genericDataTablesActionButton(data, 'edit', 'currencies.edit') +
                    // Base currency cannot be deleted or set as default
                    (!row.base
                        ? '<a href="/currencyrates/' + data + '/' + baseCurrency.id + '" class="btn btn-xs btn-info"><i class="fa fa-line-chart" title="Rates"></i></a> ' +
                          genericDataTablesActionButton(data, 'delete') +
                          '<a href="' + route('currencies.setDefault', data) + '" class="btn btn-xs btn-primary"><i class="fa fa-bank" title="Set as default"></i></a>'
                        : '');
            },
            orderable: false
        }
    ],
    order: [[1, 'asc']]
});

initializeDeleteButtonListener(dataTableSelector, 'currencies.destroy');
