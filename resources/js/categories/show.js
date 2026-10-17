import { createApp } from 'vue';
import { installRouteGlobal } from '@/shared/lib/vue/installRouteGlobal';
import CategoryShowPage from './components/CategoryShowPage.vue';

const app = createApp(CategoryShowPage);

// Add global translator function
app.config.globalProperties.__ = window.__;
installRouteGlobal(app);

app.mount('#categoryShow');
