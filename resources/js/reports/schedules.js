import 'datatables.net-bs5';
import 'datatables.net-select-bs5';
import 'datatables-contextual-actions';

import Swal from 'sweetalert2'

import * as dataTableHelpers from '@/shared/lib/datatable';
import * as helpers from '@/shared/lib/helpers';
import * as toastHelpers from '@/shared/lib/toast';
import { __, getDataTablesLanguageOptions } from '@/shared/lib/i18n';
import OnboardingCard from '@/dashboard/components/widgets/OnboardingCard.vue';
import BudgetForm from '@/reports/components/BudgetForm.vue';
import { createApp } from 'vue';
import { installRouteGlobal } from '@/shared/lib/vue/installRouteGlobal';

let ajaxIsBusy = true;

const tableSelector = '#table';

// A Budget row and a Transaction schedule row are separate database tables with their own
// auto-increment ids, so `id` alone cannot identify a row in this merged listing - row_type must
// always be checked alongside it.
function findRowByIdentity(id, rowType) {
    return $(tableSelector).dataTable().api().row(function (_idx, data) {
        const dataRowType = data.row_type === 'budget' ? 'budget' : 'schedule';

        return data.id === id && dataRowType === rowType;
    });
}

// A Budget row is already shaped close to a Transaction row by the backend (FR-6): a synthetic
// transaction_schedule (no next_date/automatic_recording), and categories/comment matching the
// shape processTransaction() would otherwise build from transaction_items. It deliberately does
// NOT go through processTransaction() itself, since that function unconditionally overwrites
// row.categories from row.transaction_items, which a Budget row doesn't have.
function normalizeBudgetRow(row) {
    if (row.transaction_schedule) {
        if (row.transaction_schedule.start_date) {
            row.transaction_schedule.start_date = helpers.parseIsoDate(row.transaction_schedule.start_date);
        }
        if (row.transaction_schedule.end_date) {
            row.transaction_schedule.end_date = helpers.parseIsoDate(row.transaction_schedule.end_date);
        }
    }

    return helpers.processScheduledTransaction(row);
}

function normalizeRow(row) {
    if (row.row_type === 'budget') {
        return normalizeBudgetRow(row);
    }

    return helpers.processScheduledTransaction(helpers.processTransaction(row));
}

// Payee/next date/amount rendering for a Budget row: a Budget has no config relation (no
// account_from/to, no amount_to) and no next_date (Non-Goals - no "enter/skip instance"
// workflow for a Budget), so these columns can't go through the shared
// transactionColumnDefinition renderers as-is. Both cells show plain 'N/A' text (muted/italic
// via createdRow below), matching the existing convention for an empty category cell.
function budgetAwarePayee(data, type, row) {
    if (row.row_type === 'budget') {
        return __('N/A');
    }

    return dataTableHelpers.transactionColumnDefinition.payee.render(data, type, row);
}

const nextDateColumnDefinition = dataTableHelpers.transactionColumnDefinition.dateFromCustomField(
    'transaction_schedule.next_date',
    __('Next date'),
    window.YAFFA.userSettings.locale
);

function budgetAwareNextDate(data, type, row) {
    if (row.row_type === 'budget' && type === 'display') {
        return __('N/A');
    }

    return nextDateColumnDefinition.render(data, type, row);
}

// The row-type column replaces the old plain-boolean 'Budget' filter/column: it now shows which
// kind of row this is (a real Transaction schedule vs. a standalone Budget target), since 'Budget'
// as a yes/no property on a schedule row no longer exists post-redesign (FR-6).
function rowTypeIcon(data, type) {
    const isBudget = data === 'budget';

    if (type === 'filter' || type === 'sort' || type === 'type') {
        return isBudget ? __('Budget') : __('Schedule');
    }

    return isBudget
        ? '<i class="fa fa-piggy-bank text-primary" title="' + __('Budget') + '"></i>'
        : '<i class="fa fa-repeat text-primary" title="' + __('Schedule') + '"></i>';
}

function budgetAwareAmount(data, type, row) {
    if (row.row_type === 'budget') {
        if (type !== 'display') {
            return row.amount;
        }

        const prefix = row.transaction_type === 'withdrawal' ? '- ' : '+ ';

        return prefix + dataTableHelpers.toFormattedCurrency(
            type,
            row.amount,
            window.YAFFA.userSettings.locale,
            row.transaction_currency
        );
    }

    return dataTableHelpers.transactionColumnDefinition.amount.render(data, type, row);
}

// The Vue app (onboarding widget + Budget create/edit modals) must mount BEFORE DataTables
// initializes #table below. Vue's in-DOM template compilation replaces the DOM nodes under its
// mount point on mount; mounting after DataTables had already initialized the table left
// DataTables holding references to now-detached nodes, so the table never rendered fetched rows
// (a real bug caught via a Dusk smoke test - see ScheduleBudgetMergedPageTest).
const vueApp = createApp({
    components: {
        OnboardingCard,
        BudgetForm,
    },
    methods: {
        showNewBudgetModal() {
            this.$refs.budgetFormNew.show();
        },
        showEditBudgetModal(budgetId) {
            this.$refs.budgetFormEdit.show(budgetId);
        },
        onBudgetSaved() {
            toastHelpers.showSuccessToast(__('Budget saved'));
            table.ajax.reload(null, false);
        },
    },
});
installRouteGlobal(vueApp);
const app = vueApp.mount('#schedulesPageApp');

// Listener for the new budget button
$('#button-new-budget').on('click', function () {
    app.showNewBudgetModal();
});

const categoryTreeSelector = '#categoryTree';

let table = $(tableSelector).DataTable({
    language: getDataTablesLanguageOptions() || undefined,
    ajax: {
        url: '/api/v1/transactions/scheduled-items?type=schedule&includeBudgets=1',
        type: 'GET',
        // getScheduledItems() already filters both schedule transactions and Budget rows by
        // category (including child-category expansion) - no client-side filtering needed.
        data: function (d) {
            // .jstree(true) fetches an existing instance without creating one - the table's own
            // first ajax call fires synchronously during DataTable() construction, before the
            // category tree below has initialized, and a bare .jstree() call in that window
            // would auto-create an empty, unconfigured instance on #categoryTree instead.
            const treeInstance = $(categoryTreeSelector).jstree(true);
            d.categories = treeInstance ? treeInstance.get_checked() : [];
        },
        dataSrc: function(data) {
            ajaxIsBusy = false;

            return data.transactions.map(normalizeRow);
        },
        deferRender: true
    },
    columns: [
        {
            data: "transaction_schedule.rule",
            title: __("Schedule settings"),
            render: function (data) {
                // Return human readable format of RRule AND the contextual action trigger icon
                // TODO: translation of rrule strings
                return `<div class="d-flex justify-content-start align-items-center">
                    <i class="hover-icon me-2 fa-fw fa-solid fa-ellipsis-vertical"></i><span>${data.toText()}</span>
                </div>`;
            }
        },
        dataTableHelpers.transactionColumnDefinition.dateFromCustomField('transaction_schedule.start_date', __('Start date'), window.YAFFA.userSettings.locale),
        {
            ...nextDateColumnDefinition,
            render: budgetAwareNextDate,
        },
        {
            data: 'row_type',
            title: __('Type'),
            render: rowTypeIcon,
            className: 'text-center',
        },
        dataTableHelpers.transactionColumnDefinition.iconFromBooleanField('transaction_schedule.active', __('Active')),
        {
            ...dataTableHelpers.transactionColumnDefinition.type(true),
            title: __('Transaction type'),
        },
        {
            ...dataTableHelpers.transactionColumnDefinition.payee,
            render: budgetAwarePayee,
        },
        dataTableHelpers.transactionColumnDefinition.category,
        {
            ...dataTableHelpers.transactionColumnDefinition.amount,
            render: budgetAwareAmount,
        },
        dataTableHelpers.transactionColumnDefinition.extra,
    ],
    createdRow: function (row, data) {
        $(row).attr('data-id', data.id);
        $(row).attr('data-row-type', data.row_type === 'budget' ? 'budget' : 'schedule');

        // TODO: unify with similar tables, e.g. account/show

        if (data.transaction_schedule.next_date) {
            if (data.transaction_schedule.next_date < new Date(new Date().setHours(0, 0, 0, 0))) {
                $(row).addClass('table-danger');
            } else if (data.transaction_schedule.next_date < new Date(new Date().setHours(24, 0, 0, 0))) {
                $(row).addClass('table-warning');
            }
        }

        // Mute category cell with 'not set' value
        if (data.row_type !== 'budget' && data.config_type === 'standard' && data.categories.length === 0) {
            $('td', row).eq(7).addClass('text-muted text-italic');
        }

        // Mute the 'N/A' next date/payee cells on a Budget row - neither column applies to it.
        if (data.row_type === 'budget') {
            $('td', row).eq(2).addClass('text-muted text-italic');
            $('td', row).eq(6).addClass('text-muted text-italic');
        }
    },
    drawCallback: function () {
        helpers.initializeBootstrapTooltips();
    },
    order: [
        // Start date, which is the second column
        [ 1, "asc" ]
    ],
    select: {
        select: true,
        info: false,
        style: 'os'
    },
    deferRender:    true,
    scrollY:        '500px',
    scrollCollapse: true,
    stateSave:      false,
    processing:     true,
    paging:         false
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
            title: __('Edit and insert instance'),
            iconClass: 'fa fa-pencil',
            contextMenuClasses: ['text-success fw-bold'],
            action: function (row) {
                window.location.href = window.route('transaction.open', {
                    transaction: row[0].id,
                    action: 'enter'
                })
            },
            isHidden: function (row) {
                return row.row_type === 'budget' || !row.schedule || !row.transaction_schedule.active;
            }
        },
        {
            type: 'option',
            title: __('Skip instance'),
            iconClass: 'fa fa-forward',
            contextMenuClasses: ['text-warning fw-bold'],
            action: function (row) {
                const id = row[0].id;
                ajaxIsBusy = true;

                // Emit a custom event to global scope to indicate that a background task is running
                toastHelpers.showLoaderToast(
                    __('Skipping schedule instance for transaction #:transactionId', {transactionId: id}),
                    `toast-transaction-${id}`
                );

                window.axios.patch(window.route('api.v1.transactions.skip', {transaction: id}))
                    .then(function(response) {
                        // Find and update the original row in the table
                        let row = findRowByIdentity(id, 'schedule');

                        // Process the transaction similarly to the DataTables initialization
                        let transaction = helpers.processTransaction(response.data.transaction);
                        transaction = helpers.processScheduledTransaction(transaction);

                        row.data(transaction).draw();

                        // Emit a custom event to global scope about the result
                        toastHelpers.showSuccessToast(
                            __('Transaction instance skipped (#:transactionId)', {transactionId: id})
                        );
                    })
                    .catch(function (error) {
                        // Emit a custom event to global scope about the result
                        toastHelpers.showErrorToast(
                            __('Error skipping transaction (#:transactionId): :error', {transactionId: id, error: error})
                        );
                    })
                    .finally(function () {
                        ajaxIsBusy = false;

                        // Close the toast with a small delay
                        toastHelpers.hideToast(`.toast-transaction-${id}`);
                    });
            },
            isHidden: function (row) {
                return row.row_type === 'budget' || !row.schedule || !row.transaction_schedule.active;
            }
        },
        {
            type: 'divider',
        },
        {
            type: 'option',
            title: __('Edit budget'),
            iconClass: 'fa fa-edit',
            contextMenuClasses: ['text-primary'],
            action: function (row) {
                app.showEditBudgetModal(row[0].id);
            },
            isHidden: function (row) {
                return row.row_type !== 'budget';
            }
        },
        {
            type: 'option',
            title: __('Edit transaction'),
            iconClass: 'fa fa-edit',
            contextMenuClasses: ['text-primary'],
            action: function (row) {
                window.location.href = route('transaction.open', {
                    transaction: row[0].id,
                    action: 'edit',
                    callback: 'back'
                })
            },
            isHidden: function (row) {
                return row.row_type === 'budget';
            }
        },
        {
            type: 'option',
            title: __('Clone transaction'),
            iconClass: 'fa fa-clone',
            action: function (row) {
                window.location.href = route('transaction.open', {
                    transaction: row[0].id,
                    action: 'clone'
                })
            },
            isHidden: function (row) {
                return row.row_type === 'budget';
            }
        },
        {
            type: 'option',
            title: __('Edit and create new schedule'),
            iconClass: 'fa fa-calendar',
            action: function (row) {
                window.location.href = route('transaction.open', {
                    transaction: row[0].id,
                    action: 'replace'
                })
            },
            isHidden: function (row) {
                return row.row_type === 'budget';
            }
        },
        {
            type: 'divider'
        },
        {
            type: 'option',
            title: __('Delete'),
            iconClass: 'fa fa-trash',
            contextMenuClasses: ['text-danger'],
            isDisabled: function () {
                return ajaxIsBusy;
            },
            action: function (row) {
                const id = row[0].id;
                const rowType = row[0].row_type === 'budget' ? 'budget' : 'schedule';
                ajaxIsBusy = true;

                // Get confirmation from the user using SweetAlert
                Swal.fire({
                    animation: false,
                    text: __('Are you sure to want to delete this item?'),
                    icon: "warning",
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

                    // Emit a custom event to global scope to indicate that an item is being deleted
                    toastHelpers.showLoaderToast(
                        __('Deleting #:id', {id: id}),
                        `toast-transaction-${id}`
                    );

                    const deleteUrl = rowType === 'budget'
                        ? window.route('api.v1.budgets.destroy', {budget: id})
                        : window.route('api.v1.transactions.destroy', {transaction: id});

                    window.axios.delete(deleteUrl)
                        .then(function () {
                            // Find and remove original row in schedule table
                            findRowByIdentity(id, rowType).remove().draw();

                            // Emit a custom event to global scope about the result
                            toastHelpers.showSuccessToast(
                                __('Deleted (#:id)', {id: id})
                            );
                        })
                        .catch(function (error) {
                            // Emit a custom event to global scope about the result
                            toastHelpers.showErrorToast(
                                __('Error deleting (#:id): :error', {id: id, error: error})
                            );
                        })
                        .finally(function () {
                            ajaxIsBusy = false;

                            // Close the toast with a small delay
                            toastHelpers.hideToast(`.toast-transaction-${id}`);
                        });
                });
            }
        }
    ]
});

// Category filter tree - a category id in the `categories[]` URL param (the same preset-filter
// convention used by the budget chart report, see resources/js/reports/budgetchart.js) is
// pre-checked on load, e.g. when arriving from a category's transaction/budget count link.
const presetCategories = helpers.getArrayParamFromUrl(new URLSearchParams(window.location.search), 'categories')
    .map(category => parseInt(category, 10));

dataTableHelpers.categoryTree(categoryTreeSelector, function () {
    table.ajax.reload(null, false);
}, presetCategories, { syncUrl: true });

document.getElementById('category-tree-all').addEventListener('click', function () {
    $(categoryTreeSelector).jstree('check_all');
});

document.getElementById('category-tree-clear').addEventListener('click', function () {
    $(categoryTreeSelector).jstree('uncheck_all');
});

// Listeners for button filters
dataTableHelpers.initializeFilterToggle(table, 3, 'table_filter_row_type');
dataTableHelpers.initializeFilterToggle(table, 4, 'table_filter_active');
dataTableHelpers.initializeFilterToggle(table, 5, 'table_filter_transaction_type');

// Set the active toggle to active by default
document.getElementById('table_filter_active_yes').click();

// Listener for external search field
dataTableHelpers.initializeStandardExternalSearch(table);

// Define the steps for the onboarding widget
window.onboardingTourSteps = [
    {
        element: '#cardActions',
        popover: {
            title: __('Schedules and budgets'),
            description: __(
                'This page lists both scheduled transactions (a recurring withdrawal, deposit, ' +
                'transfer or investment transaction) and standalone budgets (a spending or income ' +
                'target for a category with no linked transaction). Use these actions to create ' +
                'either one.',
            ),
        }
    },
    {
        element: '#filter-row-type',
        popover: {
            title: __('Filter by type'),
            description: __(
                'Switch this to show only scheduled transactions, only budgets, or both.',
            ),
        }
    },
    {
        element: '#cardFilters',
        popover: {
            title: __('Apply filters'),
            description: __('Use these controls to narrow down the list of transactions.'),
        }
    },
    {
        element: tableSelector,
        popover: {
            title: __('Actions'),
            description: __(
                'Right click on a row to open a context menu with actions. The available ' +
                'actions depend on whether the row is a scheduled transaction or a budget.',
            ),
        }
    }
];
