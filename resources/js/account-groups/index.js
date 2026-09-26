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
        property: 'account_entities_count',
        value: 0,
        negate: false,
        errorMessage: __("This account group cannot be deleted because it is still in use.")
    }
];

/** @property {Array} accountGroups */
window.table = $(dataTableSelector).DataTable({
    language: getDataTablesLanguageOptions() || undefined,
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
                return  genericDataTablesActionButton(data, 'edit', 'account-groups.edit') +
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
});

// Listener for delete button
initializeDeleteAssetButtonListener(dataTableSelector, 'api.v1.account-groups.destroy', __('Account group deleted'), function (id, tr) {
    window.accountGroups = window.accountGroups.filter(accountGroup => accountGroup.id !== id);
    table.row(tr).remove().draw();
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
import { createApp } from 'vue';
import { installRouteGlobal } from '@/shared/lib/vue/installRouteGlobal';
import OnboardingCard from '@/dashboard/components/widgets/OnboardingCard.vue';
const app = createApp({});
installRouteGlobal(app);
app.component('onboarding-card', OnboardingCard);
app.mount('#onboarding-card');
