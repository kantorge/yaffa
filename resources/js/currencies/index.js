require('datatables.net-bs5');
require("datatables.net-responsive-bs5");

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
            data: "name",
            title: __("Name"),
        },
        {
            data: "iso_code",
            title: __("ISO Code"),
        },
        {
            data: "base",
            title: __("Base currency"),
            render: function (data, type) {
                if (type === 'filter' || type === 'sort') {
                    return (data ? __('Base currency') : '');
                }

                return (data
                        ? '<i class="fa fa-check-square text-success" title="' + __('Yes') + '"></i>'
                        : '');
            },
            className: "text-center",
        },
        {
            data: "num_digits",
            title: __("Number of decimal digits displayed"),
        },
        {
            data: "suffix",
            title: __("Suffix displayed"),
        },
        {
            data: "auto_update",
            title: __("Automatic update"),
            render: function (data, type) {
                return booleanToTableIcon(data, type);
            },
            className: "text-center",
        },
        {
            title: __('Latest rate to base currency'),
            defaultContent: "",
            render: function (_data, type, row) {
                // No data returned for base currency
                if (row.base) {
                    return;
                }
                // Placeholder returned if no data is available
                if (!row.latest_rate) {
                    return __('Not available');
                }
                // Raw number is returned for sorting
                if (type === 'sort') {
                    return row.latest_rate;
                }
                // Formatted text is returned for display
                return "1 " + row.suffix + " = " + parseFloat(row.latest_rate).toLocalCurrency({iso_code: window.YAFFA.baseCurrency.iso_code, num_digits: 4}, false);
            },
            className: "dt-nowrap",
            searchable: false,
        },
        {
            title: __('Latest rate from base currency'),
            defaultContent: "",
            render: function (_data, type, row) {
                // No data returned for base currency
                if (row.base) {
                    return;
                }
                // Placeholder returned if no data is available
                if (!row.latest_rate) {
                    return __('Not available');
                }
                // Raw number is returned for sorting
                if (type === 'sort') {
                    return 1 / row.latest_rate;
                }
                // Formatted text is returned for display
                return "1 " + window.YAFFA.baseCurrency.iso_code + " = " + (1 / parseFloat(row.latest_rate)).toLocalCurrency({iso_code: row.iso_code, num_digits: 4}, false);
            },
            className: "dt-nowrap",
            searchable: false,
        },
        {
            data: "id",
            title: __('Actions'),
            render: function (data, _type, row) {
                return genericDataTablesActionButton(data, 'edit', 'currencies.edit') +
                    // Base currency cannot be deleted or set as default
                    (!row.base
                        ? '<a href="/currencyrates/' + data + '/' + window.YAFFA.baseCurrency.id + '" class="btn btn-sm btn-info" title="' + __('Rates') + '"><i class="cil-chart-line btn-icon"></i></a> ' +
                          genericDataTablesActionButton(data, 'delete') +
                          '<a href="' + route('currencies.setDefault', data) + '" class="btn btn-sm btn-primary" title="' + __('Set as default') + '"><i class="cil-bank btn-icon"></i></a>'
                        : '');
            },
            className: "dt-nowrap",
            orderable: false,
            searchable: false,
        }
    ],
    order: [
        [0, 'asc'] // Name
    ],
    responsive: true,
});

initializeDeleteButtonListener(dataTableSelector, 'currencies.destroy');
