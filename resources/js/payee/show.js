import { createApp } from 'vue';
import { installRouteGlobal } from '@/shared/lib/vue/installRouteGlobal';
import PayeeShowPage from './components/PayeeShowPage.vue';

const app = createApp(PayeeShowPage);

// Add global translator function
app.config.globalProperties.__ = window.__;
installRouteGlobal(app);

app.mount('#payeeShow');
