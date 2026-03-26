import { createApp } from 'vue';
import { installRouteGlobal } from '@/shared/lib/vue/installRouteGlobal';
import AiDocumentViewer from './components/AiDocumentViewer.vue';

const app = createApp({});

app.config.globalProperties.__ = window.__;
installRouteGlobal(app);
app.component('ai-document-viewer', AiDocumentViewer);
app.mount('#app');
