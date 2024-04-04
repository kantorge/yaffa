require('datatables.net-bs5');
require('datatables.net-responsive-bs5');

import {
    booleanToTableIcon,
    genericDataTablesActionButton,
    initializeDeleteButtonListener
} from '../components/dataTableHelper';
import {toFormattedCurrency} from '../helpers';

const dataTableSelector = '#table';

$(dataTableSelector).DataTable({
    data: window.currencies,
    columns: [
        {
            data: "name",
            title: __("Name"),
            render: function (data, type, row) {
                // Return name with optional base currency icon for display
                if (type === 'display') {
                    return data + (row.base ? ' <i class="fa fa-star text-primary" title="' + __('Base currency') + '"></i>' : '');
                }

                // Raw value is returned otherwise
                return data;
            },
            className: 'dt-nowrap',
            // Make the base class bold
            createdCell: function (td, _cellData, rowData) {
                if (rowData.base) {
                    $(td).addClass('fw-bold');
                }
            },
        },
        {
            data: "iso_code",
            title: __("ISO Code"),
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
                    return row.latest_rate;
                }
                // Formatted text is returned for display in a specific way
                const targetCurrency = Object.assign({}, window.YAFFA.baseCurrency, {max_digits: 4});

                return toFormattedCurrency(1, window.YAFFA.locale, row) +
                    " = " +
                    toFormattedCurrency(parseFloat(row.latest_rate), window.YAFFA.locale, targetCurrency);
            },
            className: "dt-nowrap",
            searchable: false,
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
                    return 1 / row.latest_rate;
                }
                // Formatted text is returned for display in a specific way
                const targetCurrency = Object.assign({}, row, {max_digits: 4});

                return toFormattedCurrency(1, window.YAFFA.locale, window.YAFFA.baseCurrency) +
                    " = " +
                    toFormattedCurrency((1 / parseFloat(row.latest_rate)), window.YAFFA.locale, targetCurrency);
            },
            className: "dt-nowrap",
            searchable: false,
        },
        {
            data: "id",
            title: __('Actions'),
            render: function (data, _type, row) {
                return genericDataTablesActionButton(data, 'edit', 'currency.edit') +
                    // Base currency cannot be deleted or set as default
                    (!row.base
                        ? '<a href="/currencyrates/' + data + '/' + window.YAFFA.baseCurrency.id + '" class="btn btn-xs btn-info" title="' + __('Rates') + '"><i class="fa-solid fa-fw fa-chart-line"></i></a> ' +
                        genericDataTablesActionButton(data, 'delete') +
                        '<a href="' + window.route('currency.setDefault', data) + '" class="btn btn-xs btn-primary data-set-default" title="' + __('Set as default') + '"><i class="fa-solid fa-fw fa-building-columns"></i></a>'
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

initializeDeleteButtonListener(dataTableSelector, 'currency.destroy');

// Create a confirmation dialog for the "Set as default" button
$(dataTableSelector).on('click', 'a.data-set-default', function (event) {
    event.preventDefault();
    const url = $(this).attr('href');
    if (!confirm(__('Are you sure you want to set this currency as the default one?\n\nWhile this action is reversible, it starts a process that may take a while to complete.'))) {
        return;
    }
    window.location.href = url;
});
