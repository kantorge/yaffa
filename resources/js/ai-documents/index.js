import { createApp } from 'vue';
import { installRouteGlobal } from '@/vue/installRouteGlobal';
import AiDocumentManager from '../components/AiDocuments/AiDocumentManager.vue';
import { __ } from '@/i18n';

const app = createApp({});

app.config.globalProperties.__ = window.__;
installRouteGlobal(app);
app.component('ai-document-manager', AiDocumentManager);
app.mount('#app');

window.onboardingTourSteps = [
  {
    element: '#ai-document-table',
    popover: {
      title: __('AI documents'),
      description: __(
        'AI documents list the files and emails that were processed into transaction drafts.',
      ),
    },
  },
  {
    element: '#ai-document-table',
    popover: {
      title: __('Manage documents'),
      description: __(
        'Use the action menu (three vertical dots) next to each document to review, reprocess, or delete it.',
      ),
    },
  },
  {
    element: '#ai-document-table',
    popover: {
      title: __('Context menu'),
      description: __(
        'You can also right-click on a row to open the contextual actions menu.',
      ),
    },
  },
  {
    element: '#cardActions',
    popover: {
      title: __('Actions'),
      description: __(
        'Quick links to configure AI providers and Google Drive imports are available here.',
      ),
    },
  },
  {
    element: '#cardFilters',
    popover: {
      title: __('Search documents'),
      description: __(
        'Search by status, source, or file name using the filter and search tools.',
      ),
    },
  },
];
