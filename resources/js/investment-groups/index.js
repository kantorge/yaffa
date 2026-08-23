import 'datatables.net-bs5';

import {
    genericDataTablesActionButton,
    renderDeleteAssetButton,
    initializeStandardExternalSearch,
    initializeDeleteAssetButtonListener
} from '@/shared/lib/datatable';

import { __, getDataTablesLanguageOptions } from '@/shared/lib/i18n';

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
    language: getDataTablesLanguageOptions() || undefined,
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
                return  genericDataTablesActionButton(data, 'edit', 'investment-groups.edit') +
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
});

// Listener for delete button
initializeDeleteAssetButtonListener(dataTableSelector, 'api.v1.investment-groups.destroy', __('Investment group deleted'), function (id, tr) {
    window.investmentGroups = window.investmentGroups.filter(investmentGroup => investmentGroup.id !== id);
    table.row(tr).remove().draw();
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
import OnboardingCard from '@/dashboard/components/widgets/OnboardingCard.vue';
import { createApp } from 'vue';
import { installRouteGlobal } from '@/shared/lib/vue/installRouteGlobal';
const app = createApp({});
installRouteGlobal(app);
app.component('onboarding-card', OnboardingCard);
app.mount('#onboarding-card');
