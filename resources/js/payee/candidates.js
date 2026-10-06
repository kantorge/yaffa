import { createApp } from 'vue';
import { installRouteGlobal } from '@/shared/lib/vue/installRouteGlobal';
import AutoRecordCandidates from './components/AutoRecordCandidates.vue';

const app = createApp(AutoRecordCandidates);

// Add global translator function
app.config.globalProperties.__ = window.__;
installRouteGlobal(app);

app.mount('#autoRecordCandidates');
