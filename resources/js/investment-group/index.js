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
        property: 'investments_count',
        value: 0,
        negate: false,
        errorMessage: __("This investment group cannot be deleted because it is still in use.")
    }
];

/** @property {Array} investmentGroups */
let table = $(dataTableSelector).DataTable({
    data: window.investmentGroups,
    columns: [
        {
            data: "name",
            title: __("Name"),
        },
        {
            data: "id",
            title: __("Actions"),
            render: function (data, _type, row) {
                return  genericDataTablesActionButton(data, 'edit', 'investment-group.edit') +
                        renderDeleteAssetButton(row, deleteButtonConditions, __("This investment group cannot be deleted."));
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

            // Send request to delete the investment group
            $.ajax({
                type: 'DELETE',
                url: window.route('api.investmentgroup.destroy', row.data().id),
                data: {
                    "_token": csrfToken,
                },
                dataType: "json",
                context: this,
                success: function (data) {
                    // Update row in table data source
                    window.investmentGroups = window.investmentGroups
                        .filter(investmentGroup => investmentGroup.id !== data.investmentGroup.id);

                    row.remove().draw();

                    toastHelpers.showSuccessToast(__('Investment group deleted'));
                },
                error: function (data) {
                    toastHelpers.showErrorToast(__('Error while trying to delete investment group: ') + data.responseJSON.error);
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
            title: __('Investment Groups'),
            description: __('Investment groups serve as an organizational tool to streamline your investment overview.'),
        }
    },
    {
        element: '#cardActions',
        popover: {
            title: __('New investment group'),
            description: __('You can create new investment groups to organize your investments.'),
        }
    }
];

// Initialize the onboarding widget
import OnboardingCard from "../components/Widgets/OnboardingCard.vue";
import { createApp } from 'vue';
const app = createApp({});
app.component('onboarding-card', OnboardingCard);
app.mount('#onboarding-card');
