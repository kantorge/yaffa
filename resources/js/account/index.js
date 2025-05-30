import 'datatables.net-bs5';
import 'datatables.net-select-bs5';
import 'datatables-contextual-actions';

import Swal from 'sweetalert2'

import * as helpers from "../helpers";
import { toFormattedCurrency } from '../helpers';

import {
    booleanToTableIcon,
} from '../components/dataTableHelper';


const dataTableSelector = '#table';
let ajaxIsBusy = false;

window.table = $(dataTableSelector).DataTable({
    data: window.accounts,
    columns: [
        {
            data: "name",
            title: __('Name'),
            render: function (data, type, row) {
                // Return name with link for display
                if (type === 'display') {
                    return `<div class="d-flex justify-content-start align-items-center">
                        <i class="hover-icon me-2 fa-fw fa-solid fa-ellipsis-vertical"></i>
                        <a href="${window.route('account-entity.show', {account_entity: row.id})}" title="${__('Show details')}">${data}</a>
                    </div>`;
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
            render: function (data, type, row) {
                if (type === 'display') {
                    if (data > 0) {
                        return `<a href="${window.route('reports.transactions', {accounts: [row.id]})}"
                        title="${__('Show transactions')}"
                        >${data.toLocaleString(window.YAFFA.locale, {maximumFractionDigits: 0, useGrouping: true})}</a>`;
                    }
                    return data;
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
            data: 'alias',
            title: __('Import alias'),
            render: function(data, type) {
                if (type === 'display') {
                    return (data ? data.replace('\n', '<br>') : __('Not set'));
                }
                return data;
            }
        }
    ],
    createdRow: function(row, data) {
        if (!data.alias) {
            $('td:eq(6)', row).addClass("text-muted text-italic");
        }
    },
    order: [
        [ 0, 'asc' ]
    ],
    deferRender: true,
    scrollY: '500px',
    scrollCollapse: true,
    stateSave: false,
    processing: true,
    paging: false,
    responsive: true,
    initComplete : function(settings) {
        $(settings.nTable).on("click", "td.activeIcon > i", function() {
            let row = $(settings.nTable).DataTable().row( $(this).parents('tr') );

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
                    // Update row in table data source
                    window.accounts.filter(account => account.id === data.id)[0].active = data.active;
                },
                error: function (_data) {
                    // Emit a custom event to global scope about the problem
                    let notificationEvent = new CustomEvent('toast', {
                        detail: {
                            header: __('Error'),
                            headerSmall: helpers.transactionLink(row.data().id, __('Go to transaction')),
                            body: __('Error while changing account active state.'),
                            toastClass: "bg-danger",
                        }
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

// Initialize the contextual actions plugin
table.contextualActions({
    contextMenuClasses: ['text-primary'],
    deselectAfterAction: true,
    contextMenu: {
        enabled: true,
        isMulti: false,
        headerRenderer: false,
        triggerButtonSelector: '.hover-icon',
    },
    buttonList: {
        enabled: false
    },
    items: [
        {
            type: 'option',
            title: __('Show details'),
            iconClass: 'fa fa-magnifying-glass',
            action: function (row) {
                window
                    .location
                    .href = window.route('account-entity.show', {account_entity: row[0].id});
            }
        },
        {
            type: 'option',
            title: __('Edit'),
            iconClass: 'fa fa-edit',
            action: function (row) {
                window
                    .location
                    .href = window.route('account-entity.edit', {type: 'account', account_entity: row[0].id});
            }
        },
        {
            type: 'option',
            title: __('Show transactions'),
            iconClass: 'fa fa-list',
            action: function (row) {
                window
                    .location
                    .href = window.route('reports.transactions', {accounts: [row[0].id]});
            }
        },
        {
            type: 'divider'
        },
        {
            type: 'option',
            title: helpers.__('Delete'),
            iconClass: 'fa fa-trash',
            contextMenuClasses: ['text-danger'],
            isDisabled: function (row) {
                // Check if the account can be deleted
                return row.transactions_count > 0;
            },
            action: function (row) {
                const account = row[0];
                ajaxIsBusy = true;

                // Get confirmation from the user using SweetAlert
                Swal.fire({
                    animation: false,
                    text: __('Are you sure to want to delete this item?'),
                    icon: "warning",
                    showCancelButton: true,
                    cancelButtonText: __('Cancel'),
                    confirmButtonText: __('Confirm'),
                    buttonsStyling: false,
                    customClass: {
                        confirmButton: 'btn btn-danger',
                        cancelButton: 'btn btn-outline-secondary ms-3'
                    }
                }).then((result) => {
                    if (!result.isConfirmed) {
                        ajaxIsBusy = false;
                        return;
                    }

                    // Send request to delete account
                    axios.delete(window.route('api.accountentity.destroy', account.id))
                    .then((response) => {
                        // Update row in table data source
                        window.accounts = window.accounts.filter(account => account.id !== response.data.accountEntity.id);

                        table
                            .row(account)
                            .remove()
                            .draw();

                        let notificationEvent = new CustomEvent('toast', {
                            detail: {
                                header: __('Success'),
                                body: __('Account deleted'),
                                toastClass: "bg-success",
                            }
                        });
                        window.dispatchEvent(notificationEvent);
                    })
                    .catch((error) => {
                        let notificationEvent = new CustomEvent('toast', {
                            detail: {
                                header: __('Error'),
                                body: __('Error while trying to delete account'),
                                toastClass: "bg-danger",
                            }
                        });
                        window.dispatchEvent(notificationEvent);
                    })
                    .finally(() => {
                        ajaxIsBusy = false;
                    });
                });
            }
        }
    ]
});

// Listeners for filters
$('input[name=table_filter_active]').on("change", function() {
    table.column(1).search(this.value).draw();
});
$('#table_filter_search_text').keyup(function(){
    table.search($(this).val()).draw() ;
})
