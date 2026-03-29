import { createApp } from 'vue';
import { installRouteGlobal } from '@/shared/lib/vue/installRouteGlobal';
import ImportPage from './components/ImportPage.vue';

const app = createApp({});

app.config.globalProperties.__ = window.__;
installRouteGlobal(app);

app.component('import-page', ImportPage);

app.mount('#app');
