require('datatables.net-bs5');
require("datatables.net-responsive-bs5");

import { toFormattedCurrency } from '../helpers';

import {
    booleanToTableIcon,
    genericDataTablesActionButton, initializeDeleteButtonListener,
    // initializeDeleteButtonListener
} from '../components/dataTableHelper';

const dataTableSelector = '#table';

window.table = $(dataTableSelector).DataTable({
    data: window.accounts,
    columns: [
        {
            data: "name",
            title: __('Name'),
            render: function (data, type, row) {
                // Return name with link for display
                if (type === 'display') {
                    return '<a href="' + window.route('account-entity.show', {account_entity: row.id}) + '" title="' + __('Show details') + '">' + data + '</a>';
                }

                // Raw value is returned otherwise
                return data;
            },
        },
        {
            data: "active",
            title: __('Active'),
            render: function (data, type) {
                return booleanToTableIcon(data, type);
            },
            className: "text-center activeIcon",
        },
        {
            data: "config.currency.name",
            title: __("Currency")
        },
        {
            data: "config.opening_balance",
            title: __("Opening balance"),
            render: function (data, type, row) {
                if (type === 'display') {
                    return toFormattedCurrency(data, window.YAFFA.locale, row.config.currency);
                }
                return data;
            },
            className: "dt-nowrap",
            searchable: false,
            type: 'num',
        },
        {
            data: "transactions_count",
            title: __("Transactions"),
            render: function (data, type) {
                if (type === 'display') {
                    return data.toLocaleString(window.YAFFA.locale, {maximumFractionDigits: 0, useGrouping: true});
                }
                return data;
            },
            type: "num",
        },
        {
            data: "config.account_group.name",
            title: __("Account group"),
        },
        {
            data: "id",
            title: __("Actions"),
            render: function (data, _type, row) {
                return  '<a href="' + window.route('account-entity.show', {account_entity: data}) + '" class="btn btn-xs btn-success" title="' + __('Show details') + '"><i class="fa fa-magnifying-glass"></i></a> ' +
                        '<a href="' + window.route('account-entity.edit', {type: 'account', account_entity: data}) + '" class="btn btn-xs btn-primary" title="' + __('Edit') + '"><i class="fa fa-edit"></i></a> ' +
                        renderDeleteButton(row);
            },
            className: "dt-nowrap",
            orderable: false,
            searchable: false,
        }
    ],
    order: [
        [ 0, 'asc' ]
    ],
    deferRender: true,
    scrollY: '500px',
    scrollCollapse: true,
    scroller: true,
    stateSave: false,
    processing: true,
    paging: false,
    responsive: true,
    initComplete : function(settings) {
        $(settings.nTable).on("click", "td.activeIcon > i", function() {
            var row = $(settings.nTable).DataTable().row( $(this).parents('tr') );

            // Do not request change if previous request is still in progress
            if ($(this).hasClass("fa-spinner")) {
                return false;
            }

            // Change icon to spinner
            $(this).removeClass().addClass('fa fa-spinner fa-spin');

            // Send request to change account active state
            $.ajax ({
                type: 'PUT',
                url: window.route('api.accountentity.updateActive', {accountEntity: row.data().id, active: (row.data().active ? 0 : 1)}),
                data: {
                    "_token": csrfToken,
                },
                dataType: "json",
                context: this,
                success: function (data) {
                    // Update row in table data souerce
                    window.accounts.filter(account => account.id === data.id)[0].active = data.active;
                },
                error: function (_data) {
                    // Emit a custom event to global scope about the problem
                    let notificationEvent = new CustomEvent('notification', {
                        detail: {
                            notification: {
                                type: 'danger',
                                message: 'Error while changing account active state',
                                title: null,
                                icon: null,
                                dismissible: true,
                            }
                        },
                    });
                    window.dispatchEvent(notificationEvent);
                },
                complete: function(_data) {
                    // Re-render row
                    row.invalidate();
                }
            });
        });

        // Listener for delete button
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
                url: window.route('api.accountentity.destroy', +row.data().id),
                data: {
                    "_token": csrfToken,
                },
                dataType: "json",
                context: this,
                success: function (data) {
                    // Update row in table data souerce
                    window.accounts = window.accounts.filter(account => account.id !== data.accountEntity.id);
                },
                error: function (_data) {
                    let notificationEvent = new CustomEvent('notification', {
                        detail: {
                            notification: {
                                type: 'danger',
                                message: __('Error while trying to delete account'),
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
                                message: __('Account deleted'),
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

initializeDeleteButtonListener(dataTableSelector, 'account-entity.destroy');

function renderDeleteButton(row) {
    if (row.transactions_count === 0) {
        return '<button class="btn btn-xs btn-danger deleteIcon" data-id="' + row.id + '" type="button" title="' + __('Delete') + '"><i class="fa fa-fw fa-trash"></i></button> '
    }

    return '<button class="btn btn-xs btn-outline-danger" type="button" title="' + __('Account is already used, it cannot be deleted') + '"><i class="fa fa-fw fa-trash"></i></button> '
}

// Listeners for filters
$('input[name=table_filter_active]').on("change", function() {
    table.column(1).search(this.value).draw();
});
$('#table_filter_search_text').keyup(function(){
    table.search($(this).val()).draw() ;
})
