require('datatables.net-bs5');
require("datatables.net-responsive-bs5");

import * as dataTableHelpers from './../components/dataTableHelper';

const dataTableSelector = '#table';

let table = $(dataTableSelector).DataTable({
    data: window.investments.map(c => { c.investment_price_provider = c.investment_price_provider || { name: '' }; return c; }),
    columns: [
        {
            data: "name",
            title: __("Name"),
            render: function (data, _type, row) {
                return `<a href="${window.route('investment.show', row.id)}">${data}</a>`;
            },
            type: "html"
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
            render: function (data, _type, row) {
                return '<a href="' + window.route('investment.edit', data) + '" class="btn btn-xs btn-primary"><i class="fa fa-fw fa-edit" title="' + __('Edit') + '"></i></a> ' +
                       renderDeleteButton(row);
            },
            className: "dt-nowrap",
            orderable: false,
            searchable: false,
        }
    ],
    order: [
        [0, 'asc']  // Default ordering by name
    ],
    responsive: true,
    initComplete: function (settings) {
        $(settings.nTable).on("click", "td.activeIcon > i:not(.inProgress)", function () {
            var row = $(settings.nTable).DataTable().row($(this).parents('tr'));

            // Change icon to spinner
            $(this).removeClass().addClass('fa fa-spinner fa-spin inProgress');

            // Send request to change investment active state
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
                    window.investments.filter(investment => investment.id === data.id)[0].active = data.active;
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

        $(settings.nTable).on("click", "td > button.deleteIcon:not(.busy)", function () {
            // Confirm the action with the user
            if (!confirm(__('Are you sure to want to delete this item?'))) {
                return;
            }

            let row = $(settings.nTable).DataTable().row($(this).parents('tr'));

            // Change icon to spinner
            $(this).addClass('busy');
            $(this).children('i').removeClass().addClass('fa fa-fw fa-spinner fa-spin');

            // Send request to change investment active state
            $.ajax({
                type: 'DELETE',
                url: window.route('api.investment.destroy', + row.data().id),
                data: {
                    "_token": csrfToken,
                },
                dataType: "json",
                context: this,
                success: function (data) {
                    // Update row in table data souerce
                    window.investments = window.investments.filter(investment => investment.id !== data.investment.id);
                },
                error: function (_data) {
                    let notificationEvent = new CustomEvent('notification', {
                        detail: {
                            notification: {
                                type: 'danger',
                                message: __('Error while trying to delete investment'),
                                title: null,
                                icon: null,
                                dismissible: true,
                            }
                        },
                    });
                    window.dispatchEvent(notificationEvent);
                },
                complete: function (_data) {
                    row.remove().draw();
                    let notificationEvent = new CustomEvent('notification', {
                        detail: {
                            notification: {
                                type: 'success',
                                message: __('Investment deleted'),
                                title: null,
                                icon: null,
                                dismissible: true,
                            }
                        },
                    });
                    window.dispatchEvent(notificationEvent);
                }
            });
        });
    }
});

// Listeners for button filter(s)
dataTableHelpers.initializeFilterButtonsActive(table, 1);

// Listener for delete button
function renderDeleteButton(row) {
    if (row.transactions_count === 0) {
        return '<button class="btn btn-xs btn-danger deleteIcon" data-id="' + row.id + '" type="button" title="' + __('Delete') + '"><i class="fa fa-fw fa-trash"></i></button> '
    }

    return '<button class="btn btn-xs btn-outline-danger" type="button" title="' + __('Investment is already used, it cannot be deleted') + '"><i class="fa fa-fw fa-trash"></i></button> '
}
