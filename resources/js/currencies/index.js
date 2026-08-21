import 'datatables.net-bs5';
import 'datatables.net-responsive-bs5';

import {
    booleanToTableIcon,
    genericDataTablesActionButton
} from '@/shared/lib/datatable';
import { getDataTablesLanguageOptions, toFormattedCurrency } from '@/shared/lib/i18n';
import { confirmDelete, confirmAction } from '@/shared/lib/confirm';
import * as toastHelpers from '@/shared/lib/toast';

const dataTableSelector = '#table';

const table = $(dataTableSelector).DataTable({
    language: getDataTablesLanguageOptions() || undefined,
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
                return toFormattedCurrency(1, window.YAFFA.userSettings.locale, row, 'detailed') +
                    " = " +
                    toFormattedCurrency(parseFloat(row.latest_rate), window.YAFFA.userSettings.locale, window.YAFFA.userSettings.baseCurrency, 'detailed');
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
                return toFormattedCurrency(1, window.YAFFA.userSettings.locale, window.YAFFA.userSettings.baseCurrency, 'detailed') +
                    " = " +
                    toFormattedCurrency((1 / parseFloat(row.latest_rate)), window.YAFFA.userSettings.locale, row, 'detailed');
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
                        ? '<a href="/currencyrates/' + data + '/' + window.YAFFA.userSettings.baseCurrency.id + '" class="btn btn-xs btn-info" title="' + __('Rates') + '"><i class="fa-solid fa-fw fa-chart-line"></i></a> ' +
                        genericDataTablesActionButton(data, 'delete') +
                        '<a href="' + window.route('currencies.setDefault', data) + '" class="btn btn-xs btn-primary data-set-default" title="' + __('Set as default') + '"><i class="fa-solid fa-fw fa-building-columns"></i></a>'
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

$(dataTableSelector).on('click', '.data-delete:not(.busy)', function () {
    const button = $(this);
    const id = Number(button.data('id'));

    confirmDelete(__('Are you sure to want to delete this item?')).then((result) => {
        if (!result.isConfirmed) {
            return;
        }

        button.addClass('busy');

        axios.delete(window.route('api.v1.currencies.destroy', id))
            .then(function () {
                window.currencies = window.currencies.filter((currency) => currency.id !== id);

                table.row(function (_idx, data) {
                    return data.id === id;
                }).remove().draw();

                toastHelpers.showSuccessToast(__('Currency deleted'));
            })
            .catch(function (error) {
                toastHelpers.showErrorToast(error.response?.data?.error || __('Error while trying to delete currency'));
            })
            .finally(function () {
                button.removeClass('busy');
            });
    });
});

// Create a confirmation dialog for the "Set as default" button
$(dataTableSelector).on('click', 'a.data-set-default', function (event) {
    event.preventDefault();
    const url = $(this).attr('href');
    confirmAction(__('Are you sure you want to set this currency as the default one?')).then((result) => {
        if (result.isConfirmed) {
            window.location.href = url;
        }
    });
});
