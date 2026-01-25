import 'datatables.net-bs5';
import 'datatables.net-select-bs5';
import 'datatables-contextual-actions';

import Swal from 'sweetalert2'

import * as dataTableHelpers from './../components/dataTableHelper';
import { __, toFormattedCurrency } from '../helpers';
import * as toastHelpers from '../toast';

let ajaxIsBusy = false;

/**
 * Define the conditions for the delete button, as required by the DataTables helper.
 */
const deleteButtonConditions = [
    {
        property: 'transactions_count',
        value: 0,
        negate: false,
        errorMessage: __('It is already used in transactions.'),
    },
];


let table = $('#investmentSummary').DataTable({
    data: window.investments,
    columns: [
        {
            data: "name",
            title: __("Name"),
            render: function (data, type, row) {
                if (type !== 'display') {
                    return data;
                }

                // Display the name AND the contextual action trigger icon
                return `
                    <div class="d-flex justify-content-start align-items-center">
                        <i class="hover-icon me-2 fa-fw fa-solid fa-ellipsis-vertical"></i>
                        <span>
                            <a href="${window.route('investment.show', row.id)}" title="${__('View investment details')}">${data}</a>
                        </span>
                    </div>`;
            },
            type: "html",
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
            data: "investment_group.name",
            title: __("Group"),
            type: "string"
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
            data: "quantity",
            title: __("Quantity"),
            render: function (data, type) {
                if (type === 'display') {
                    return data.toLocaleString(window.YAFFA.locale, {maximumFractionDigits: 2, useGrouping: true});
                }
                return data;
            },
            type: "num",
            className: 'dt-nowrap',
        },
        {
            data: "price",
            title: __("Latest price"),
            render: function (data, type, row) {
                if (type === 'display' && !isNaN(data) && typeof data === "number") {
                    return toFormattedCurrency(data, window.YAFFA.locale, row.currency);
                }

                return data;
            },
            type: "num",
            className: 'dt-nowrap',
        },
        {
            defaultContent: "",
            title: __("Value"),
            render: function (_data, type, row) {
                const value = row.quantity * row.price;

                if (type === 'display') {
                    return toFormattedCurrency(value, window.YAFFA.locale, row.currency);
                }

                return value;
            },
            type: "num",
            className: 'dt-nowrap',
        }
    ],
    order: [
        [0, 'asc']
    ],
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
                    // Update row in table data source
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

        // Listener for delete button
        $(settings.nTable).on("click", "td > button.deleteIcon:not(.busy)", function () {
            // Confirm the action with the user
            if (!confirm(__('Are you sure to want to delete this item?'))) {
                return;
            }

            let row = $(settings.nTable).DataTable().row($(this).parents('tr'));

            // Change icon to spinner
            let element = $(this);
            element.addClass('busy');

            // Send request to change investment active state
            $.ajax({
                type: 'DELETE',
                url: window.route('api.investment.destroy', row.data().id),
                data: {
                    "_token": csrfToken,
                },
                dataType: "json",
                context: this,
                success: function (data) {
                    // Update row in table data source
                    window.investments = window.investments.filter(investment => investment.id !== data.investment.id);

                    // Remove row from table
                    $(settings.nTable).DataTable().row($(this).parents('tr')).remove().draw();

                    toastHelpers.showSuccessToast(__('Investment deleted'));
                },
                error: function (_data) {
                    toastHelpers.showErrorToast(__('Error while trying to delete investment'));
                },
                complete: function (_data) {
                    // Restore button icon
                    element.removeClass('busy');
                }
            });
        });
    },
    select: {
        select: true,
        info: false,
        style: 'os'
    },
    deferRender: true,
    scrollY: '500px',
    scrollCollapse: true,
    stateSave: false,
    processing: true,
    paging: false,
});

// Initialize the contextual actions for the table
table.contextualActions({
    contextMenuClasses: ['text-primary'],
    deselectAfterAction: true,
    contextMenu: {
        enabled: true,
        isMulti: false,
        headerRenderer: function(selectedRows) {
            return selectedRows[0].name;
        },
        triggerButtonSelector: '.hover-icon',
    },
    buttonList: {
        enabled: false
    },
    items: [
        {
            type: 'option',
            title: __('View investment details'),
            iconClass: 'fa fa-fw fa-search',
            contextMenuClasses: ['text-success fw-bold'],
            action: function(selectedRows) {
                window.location.href = window.route('investment.show', {
                    investment: selectedRows[0].id
                });
            }
        },
        {
            type: 'option',
            title: __('View investment price list'),
            iconClass: 'fa fa-fw fa-dollar',
            contextMenuClasses: ['text-info fw-bold'],
            action: function(selectedRows) {
                window.location.href = window.route('investment-price.list', {
                    investment: selectedRows[0].id
                });
            }
        },
        {
            type: 'divider'
        },
        {
            type: 'option',
            title: __('New transaction for investment'),
            iconClass: 'fa fa-fw fa-plus',
            contextMenuClasses: ['text-info fw-bold'],
            action: function(selectedRows) {
                window.location.href = window.route('transaction.create', {
                    type: 'investment',
                    investment: selectedRows[0].id
                });
            }
        },
        {
            type: 'divider'
        },
        {
            type: 'option',
            title: __('Edit investment'),
            iconClass: 'fa fa-fw fa-edit',
            contextMenuClasses: ['text-primary fw-bold'],
            action: function(selectedRows) {
                window.location.href = window.route('investment.edit', {
                    investment: selectedRows[0].id
                });
            }
        },
        {
            type: 'divider',
        },
        {
            type: 'option',
            title: __('Delete investment'),
            iconClass: 'fa fa-trash',
            contextMenuClasses: ['text-danger fw-bold'],
            isHidden: function(row) {
                return row.transactions_count > 0;
            },
            isDisabled: function() {
                return ajaxIsBusy;
            },
            action: function(selectedRows) {
                const id = selectedRows[0].id;
                const name = selectedRows[0].name;

                ajaxIsBusy = true;

                // Get confirmation from user using SweetAlert2
                Swal.fire({
                    text: __('Are you sure you want to delete this investment?'),
                    icon: 'warning',
                    showCancelButton: true,
                    cancelButtonText: __('Cancel'),
                    confirmButtonText: __('Delete'),
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

                    // Emit a custom event to global scope to indicate that an investment delete is in progress
                    toastHelpers.showLoaderToast(
                        __('Deleting investment: :investmentName', {investmentName: name}),
                        `toast-investment-${id}`
                    );

                    window.axios.delete(window.route('api.investment.destroy', {investment: id}))
                        .then(response => {
                            // Remove investment from data source
                            window.investments = window.investments.filter(investment => investment.id !== response.data.investment.id);

                            // Remove row from table
                            table.row(function (idx, data, node) {
                                return data.id === id;
                            }).remove().draw();

                            // Emit success toast
                            toastHelpers.showSuccessToast(__('Investment deleted'));
                        })
                        .catch(error => {
                            toastHelpers.showErrorToast(
                                __('Error while trying to delete investment: :errorMessage', {errorMessage: error.response.data.message || error.message})
                            );
                        })
                        .finally(() => {
                            ajaxIsBusy = false;

                            // Close the toast with a small delay
                            toastHelpers.hideToast(`.toast-investment-${id}`);
                        });
                });
            }
        },
        {
            type: 'option',
            title: __('Cannot be deleted, already in use'),
            iconClass: 'fa fa-fw fa-info-circle',
            contextMenuClasses: ['text-muted fw-bold'],
            isHidden: function(row) {
                return row.transactions_count === 0;
            },
            action: function(_selectedRows) {
                // No action
            }
        }
    ]
});

// Initialize the "tree" for the investment group filter list
const selectorTreeContainer = '#investment-group-tree-container';
dataTableHelpers.investmentGroupTree(
    selectorTreeContainer,
    window.investmentGroups,
    filterInvestmentGroup
);

// Listeners for filters
dataTableHelpers.initializeFilterToggle(table, 1, 'table_filter_active');
dataTableHelpers.initializeStandardExternalSearch(table);
function filterInvestmentGroup() {
    const selectedNodes = $(selectorTreeContainer).jstree().get_checked(true);
    const selectedInvestmentGroupNames = selectedNodes.map(node => '^' + node.text + '$');
    table.column(2).search(selectedInvestmentGroupNames.join('|'), true, false).draw();
}

// Set the active toggle to active by default
document.getElementById('table_filter_active_yes').click();

// Define the steps for the onboarding widget
window.onboardingTourSteps = [
    {
        element: '#investmentSummary',
        popover: {
            title: __('Investments'),
            description: __('Investments represent your holdings in various financial instruments such as stocks, bonds, or mutual funds.'),
        }
    },
    {
        element: '#investmentSummary',
        popover: {
            title: __('Manage Investments'),
            description: __('Use the action menu (three vertical dots) next to each investment to view details, edit, or delete the investment. You can also initiate new transactions directly from this menu.'),
        }
    },
    {
        element: '#investmentSummary',
        popover: {
            title: __('Manage Investments'),
            description: __('You can also right-click on any investment row to access the contextual actions menu for the available operations.'),
        }
    },
    {
        element: '#cardActions',
        popover: {
            title: __('New investment'),
            description: __('You can create new investments to capture details of your portfolio.'),
        }
    },
    {
        element: '#button-manage-investment-groups',
        popover: {
            title: __('Investment Groups'),
            description: __('Investments are organized into groups to help you categorize and manage them effectively. You can create, edit, or delete investment groups as needed.'),
        }
    },
    {
        element: '#table_filter_search_text',
        popover: {
            title: __('Search investments'),
            description: __('Use this search box to quickly find investments by any table filed, like name, symbol, or ISIN number.'),
        }
    }
];

// Initialize the onboarding widget
import OnboardingCard from "../components/Widgets/OnboardingCard.vue";
import { createApp } from 'vue';
const app = createApp({});
app.component('onboarding-card', OnboardingCard);
app.mount('#onboarding-card');
