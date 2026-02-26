import { createApp } from 'vue';
import AiDocumentViewer from '../components/AiDocuments/AiDocumentViewer.vue';

const app = createApp({});

app.config.globalProperties.__ = window.__;
app.component('ai-document-viewer', AiDocumentViewer);
app.mount('#app');
