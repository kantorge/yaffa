import { createApp } from 'vue';
import { installRouteGlobal } from '@/vue/installRouteGlobal';
import AiDocumentViewer from '../components/AiDocuments/AiDocumentViewer.vue';

const app = createApp({});

app.config.globalProperties.__ = window.__;
installRouteGlobal(app);
app.component('ai-document-viewer', AiDocumentViewer);
app.mount('#app');
