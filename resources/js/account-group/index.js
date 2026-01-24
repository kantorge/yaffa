import 'datatables.net-bs5';

import {
    genericDataTablesActionButton,
    renderDeleteAssetButton,
    initializeStandardExternalSearch
} from '../components/dataTableHelper';

import { __ } from '../helpers';
import * as toastHelpers from '../toast';

const dataTableSelector = '#table';

/**
 * Define the conditions for the delete button
 */
const deleteButtonConditions = [
    {
        property: 'account_entities_count',
        value: 0,
        negate: false,
        errorMessage: __("This account group cannot be deleted because it is still in use.")
    }
];

/** @property {Array} accountGroups */
window.table = $(dataTableSelector).DataTable({
    data: window.accountGroups,
    columns: [
        {
            data: "name",
            title: __("Name")
        },
        {
            data: "id",
            title: __("Actions"),
            render: function (data, _type, row) {
                return  genericDataTablesActionButton(data, 'edit', 'account-group.edit') +
                        renderDeleteAssetButton(row, deleteButtonConditions, __("This account group cannot be deleted."));
            },
            className: "dt-nowrap",
            orderable: false,
            searchable: false,
        }
    ],
    order: [
        [0, 'asc']
    ],
    deferRender:    true,
    scrollY:        '500px',
    scrollCollapse: true,
    stateSave:      false,
    processing:     true,
    paging:         false,
    initComplete: function (settings) {
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
                url: window.route('api.accountgroup.destroy', row.data().id),
                data: {
                    "_token": csrfToken,
                },
                dataType: "json",
                context: this,
                success: function (data) {
                    // Update row in table data source
                    window.accountGroups = window.accountGroups
                        .filter(accountGroup => accountGroup.id !== data.accountGroup.id);

                    row.remove().draw();

                    toastHelpers.showSuccessToast(__('Account group deleted'));
                },
                error: function (data) {
                    toastHelpers.showErrorToast(__('Error while trying to delete account group: ') + data.responseJSON.error);
                },
                complete: function (_data) {
                    // Restore button icon
                    element.removeClass('busy');
                }
            });
        });
    }
});

// Listener for external search field
initializeStandardExternalSearch(table);

// Define the steps for the onboarding widget
window.onboardingTourSteps = [
    {
        element: '#table',
        popover: {
            title: __('Account Groups'),
            description: __('Account groups serve as an organizational tool to streamline your financial overview.'),
        }
    },
    {
        element: '#cardActions',
        popover: {
            title: __('New account group'),
            description: __('You can create new account groups to organize your accounts.'),
        }
    }
];

// Initialize the onboarding widget
import OnboardingCard from "../components/Widgets/OnboardingCard.vue";
import { createApp } from 'vue';
const app = createApp({});
app.component('onboarding-card', OnboardingCard);
app.mount('#onboarding-card');
