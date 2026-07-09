import { createApp } from 'vue';
import { installRouteGlobal } from '@/shared/lib/vue/installRouteGlobal';
import ImportPage from './components/ImportPage.vue';
import CreateStandardTransactionModal from '@/transactions/components/form/ModalStandard.vue';
import QuickViewTransactionModal from '@/transactions/components/display/Modal.vue';

const app = createApp({});

app.config.globalProperties.__ = window.__;
installRouteGlobal(app);

app.component('import-page', ImportPage);
app.component('transaction-create-standard-modal', CreateStandardTransactionModal);
app.component('transaction-quickview-modal', QuickViewTransactionModal);

app.mount('#app');

// Define the steps for the onboarding guided tour
import { __ } from '@/shared/lib/i18n';
window.onboardingTourSteps = [
    {
        element: '#import-source-type',
        popover: {
            title: __('Choose your file type'),
            description: __('Select QIF or CSV depending on your bank\'s export format. QIF is the traditional format; CSV gives you more flexibility with a custom import profile.'),
        },
    },
    {
        element: '#import-file',
        popover: {
            title: __('Upload your file'),
            description: __('Select the file exported from your bank. Accepted formats change based on the selected source type.'),
        },
    },
    {
        element: '#import-profile-selector',
        popover: {
            title: __('Select an import profile'),
            description: __('Profiles tell the parser how to interpret your file\'s fields. System profiles are provided out of the box; you can also create your own custom profiles below.'),
        },
    },
    {
        element: '#import-draft-table-card',
        popover: {
            title: __('Review parsed drafts'),
            description: __('After uploading, each row from your file becomes a draft. Review the date, amount, and payee, then finalize or ignore each one individually.'),
        },
    },
    {
        element: '#import-draft-filters',
        popover: {
            title: __('Filter by status'),
            description: __('Use these checkboxes to show or hide drafts, ignored entries, and finalized transactions to keep the list manageable.'),
        },
    },
    {
        element: '#import-profile-manager',
        popover: {
            title: __('Manage import profiles'),
            description: __('Create, edit, and delete your custom import profiles here. For CSV files a step-by-step wizard helps you configure column mappings; for QIF you can remap field markers.'),
        },
    },
];

// Initialize the onboarding widget
import OnboardingCard from '@/dashboard/components/widgets/OnboardingCard.vue';
const onboardingApp = createApp({});
installRouteGlobal(onboardingApp);
onboardingApp.component('onboarding-card', OnboardingCard);
onboardingApp.mount('#onboarding-card');
