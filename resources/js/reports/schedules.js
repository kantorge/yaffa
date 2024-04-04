import 'datatables.net-bs5';
import 'datatables.net-select-bs5';
import 'datatables-contextual-actions';

import * as dataTableHelpers from '../components/dataTableHelper';
import * as helpers from '../helpers';
import { __ } from '../helpers';

let ajaxIsBusy = true;

const tableSelector = '#table';
let table = $(tableSelector).DataTable({
    ajax: {
        url: '/api/transactions/get_scheduled_items/any',
        type: 'GET',
        dataSrc: function(data) {
            ajaxIsBusy = false;

            return data.transactions
                .map(helpers.processTransaction)
                .map(helpers.processScheduledTransaction);
        },
        deferRender: true
    },
    columns: [
        dataTableHelpers.transactionColumnDefinition.dateFromCustomField('transaction_schedule.start_date', __('Start date'), window.YAFFA.locale),
        {
            data: "transaction_schedule.rule",
            title: __("Schedule settings"),
            render: function (data) {
                // Return human readable format of RRule
                // TODO: translation of rrule strings
                return data.toText();
            }
        },
        dataTableHelpers.transactionColumnDefinition.dateFromCustomField('transaction_schedule.next_date', __('Next date'), window.YAFFA.locale),
        dataTableHelpers.transactionColumnDefinition.iconFromBooleanField('schedule', __('Schedule')),
        dataTableHelpers.transactionColumnDefinition.iconFromBooleanField('budget', __('Budget')),
        dataTableHelpers.transactionColumnDefinition.iconFromBooleanField('transaction_schedule.active', __('Active')),
        dataTableHelpers.transactionColumnDefinition.type,
        dataTableHelpers.transactionColumnDefinition.payee,
        dataTableHelpers.transactionColumnDefinition.category,
        dataTableHelpers.transactionColumnDefinition.amount,
        dataTableHelpers.transactionColumnDefinition.extra,
    ],
    createdRow: function (row, data) {
        $(row).attr('data-id', data.id);

        // TODO: unify with similar tables, e.g. account/show

        if (data.transaction_schedule.next_date) {
            if (data.transaction_schedule.next_date < new Date(new Date().setHours(0, 0, 0, 0))) {
                $(row).addClass('table-danger');
            } else if (data.transaction_schedule.next_date < new Date(new Date().setHours(24, 0, 0, 0))) {
                $(row).addClass('table-warning');
            }
        }

        // Mute category cell with 'not set' value
        if (data.config_type === 'standard' && data.categories.length === 0) {
            $('td', row).eq(8).addClass('text-muted text-italic');
        }
    },
    initComplete: function (_settings, _json) {
        $('[data-toggle="tooltip"]').tooltip();
    },
    order: [
        // Start date is the first column
        [ 0, "asc" ]
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
        headerRenderer: false
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
                return !row.schedule || !row.transaction_schedule.active;
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
                let notificationEvent = new CustomEvent('toast', {
                    detail: {
                        body: __('Skipping schedule instance for transaction #:transactionId', {transactionId: id}),
                        toastClass: `bg-info toast-transaction-${id}`,
                        delay: Infinity,
                    }
                });
                window.dispatchEvent(notificationEvent);

                window.axios.patch(window.route('api.transactions.skipScheduleInstance', {transaction: id}))
                    .then(function(response) {
                        // Find and update the original row in the table
                        let row = $(tableSelector).dataTable().api().row(function (_idx, data, _node) {
                            return data.id === id;
                        });

                        // Process the transaction similarly to the DataTables initialization
                        let transaction = helpers.processTransaction(response.data.transaction);
                        transaction = helpers.processScheduledTransaction(transaction);

                        row.data(transaction).draw();

                        // Emit a custom event to global scope about the result
                        let notificationEvent = new CustomEvent('toast', {
                            detail: {
                                header: __('Success'),
                                body: __('Transaction instance skipped (#:transactionId)', {transactionId: id}),
                                toastClass: "bg-success",
                            }
                        });
                        window.dispatchEvent(notificationEvent);
                    })
                    .catch(function (error) {
                        // Emit a custom event to global scope about the result
                        let notificationEvent = new CustomEvent('toast', {
                            detail: {
                                header: __('Error'),
                                body: __('Error skipping transaction (#:transactionId): :error', {transactionId: id, error: error}),
                                toastClass: "bg-danger"
                            }
                        });
                        window.dispatchEvent(notificationEvent);
                    })
                    .finally(function () {
                        ajaxIsBusy = false;

                        // Close the toast with a small delay
                        setTimeout(function () {
                            let toastElement = document.querySelector(`.toast-transaction-${id}`);
                            let toastInstance = new window.bootstrap.Toast(toastElement);
                            toastInstance.hide();
                        }, 250);
                    });
            },
            isHidden: function (row) {
                return !row.schedule || !row.transaction_schedule.active;
            }
        },
        {
            type: 'divider',
        },
        {
            type: 'option',
            title: __('Edit transaction'),
            iconClass: 'fa fa-edit',
            action: function (row) {
                window.location.href = route('transaction.open', {
                    transaction: row[0].id,
                    action: 'edit',
                    callback: 'back'
                })
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
                ajaxIsBusy = true;

                // Emit a custom event to global scope to indicate that a transaction is being deleted
                let notificationEvent = new CustomEvent('toast', {
                    detail: {
                        body: __('Deleting transaction #:transactionId', {transactionId: id}),
                        toastClass: `bg-info toast-transaction-${id}`,
                        delay: Infinity,
                    }
                });
                window.dispatchEvent(notificationEvent);

                window.axios.delete(window.route('api.transactions.destroy', {transaction: id}))
                    .then(function () {
                        // Find and remove original row in schedule table
                        let row = $(tableSelector).dataTable().api().row(function (_idx, data, _node) {
                            return data.id === id;
                        });

                        row.remove().draw();

                        // Emit a custom event to global scope about the result
                        let notificationEvent = new CustomEvent('toast', {
                            detail: {
                                header: __('Success'),
                                body: __('Transaction deleted (#:transactionId)', {transactionId: id}),
                                toastClass: "bg-success",
                            }
                        });
                        window.dispatchEvent(notificationEvent);
                    })
                    .catch(function (error) {
                        // Emit a custom event to global scope about the result
                        let notificationEvent = new CustomEvent('toast', {
                            detail: {
                                header: __('Error'),
                                body: __('Error deleting transaction (#:transactionId): :error', {transactionId: id, error: error}),
                                toastClass: "bg-danger"
                            }
                        });
                        window.dispatchEvent(notificationEvent);
                    })
                    .finally(function () {
                        ajaxIsBusy = false;

                        // Close the toast with a small delay
                        setTimeout(function () {
                            let toastElement = document.querySelector(`.toast-transaction-${id}`);
                            let toastInstance = new window.bootstrap.Toast(toastElement);
                            toastInstance.hide();
                        }, 250);
                    });
            }
        }
    ]
});

// Listeners for button filters
dataTableHelpers.initializeFilterToggle(table, 3, 'table_filter_schedule');
dataTableHelpers.initializeFilterToggle(table, 4, 'table_filter_budget');
dataTableHelpers.initializeFilterToggle(table, 5, 'table_filter_active');

// Set the active toggle to active by default
document.getElementById('table_filter_active_yes').click();

// Listener for external search field
dataTableHelpers.initializeStandardExternalSearch(table);

// Define the steps for the onboarding widget
window.onboardingTourSteps = [
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
            description: __('Right click on a transaction to open a context menu with actions.'),
        }
    }
];

// Initialize the onboarding widget
import OnboardingCard from "../components/Widgets/OnboardingCard.vue";
import { createApp } from 'vue';
const app = createApp({});
app.component('onboarding-card', OnboardingCard);
app.mount('#onboarding-card');
