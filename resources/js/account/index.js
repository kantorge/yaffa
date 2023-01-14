require('datatables.net-bs5');
require("datatables.net-responsive-bs5");

import { toFormattedCurrency } from '../helpers';

import {
    booleanToTableIcon,
    genericDataTablesActionButton,
    // initializeDeleteButtonListener
} from '../components/dataTableHelper';

const dataTableSelector = '#table';

window.table = $(dataTableSelector).DataTable({
    data: window.accounts,
    columns: [
        {
            data: "name",
            title: __('Name')
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
                // Raw number is returned for sorting
                if (type === 'sort') {
                    return data;
                }
                return toFormattedCurrency(data, window.YAFFA.locale, row.config.currency);
            },
            className: "dt-nowrap",
            searchable: false,
            type: 'num',
        },
        {
            data: "config.account_group.name",
            title: __("Account group"),
        },
        {
            data: "id",
            title: __("Actions"),
            render: function (data) {
                return  '<a href="' + route('account-entity.edit', {type: 'account', account_entity: data}) + '" class="btn btn-sm btn-primary"><i class="fa fa-edit" title="' + __('Edit') + '"></i></a> ' +
                        genericDataTablesActionButton(data, 'delete');
            },
            className: "dt-nowrap",
            orderable: false,
            searchable: false,
        }
    ],
    order: [
        [ 0, 'asc' ]
    ],
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
                url: '/api/assets/accountentity/' + row.data().id + '/active/' + (row.data().active ? 0 : 1),
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
    }
});

// Setup listener for delete button
// https://github.com/kantorge/yaffa/issues/37
$(dataTableSelector).on("click", ".data-delete", function() {
    if (!confirm(__('Are you sure to want to delete this item?'))) {
        return;
    }

    let form = document.getElementById('form-delete');
    form.action = window.route('account-entity.destroy', {type: 'account', account_entity: this.dataset.id});
    form.submit();
});

// Listeners for button filter(s)
$('input[name=active]').on("change", function() {
    table.column(1).search(this.value).draw();
});
