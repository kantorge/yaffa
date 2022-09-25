require('datatables.net-bs');
import {
    booleanToTableIcon,
    genericDataTablesActionButton,
    initializeDeleteButtonListener
} from './../components/dataTableHelper';

const dataTableSelector = '#table';

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
            title: "Number of decimal digits displayed"
        },
        {
            data: "suffix",
            title: "Suffix displayed"
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
            title: "Latest rate to base currency",
            defaultContent: "",
            render: function (_data, _type, row) {
                if (row.base) {
                    return;
                }
                if (!row.latest_rate) {
                    return "Not available";
                }
                return "1 " + row.suffix + " = " + parseFloat(row.latest_rate).toLocalCurrency({iso_code: baseCurrency.iso_code, num_digits: 4}, false);
            },
            className: "dt-nowrap",
        },
        {
            title: "Latest rate from base currency",
            defaultContent: "",
            render: function (_data, _type, row) {
                if (row.base) {
                    return;
                }
                if (!row.latest_rate) {
                    return "Not available";
                }
                return "1 " + baseCurrency.iso_code + " = " + (1 / parseFloat(row.latest_rate)).toLocalCurrency({iso_code: row.iso_code, num_digits: 4}, false);
            },
            className: "dt-nowrap",
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
