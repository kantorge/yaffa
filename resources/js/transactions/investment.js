import { createApp } from 'vue';
import { installRouteGlobal } from '@/shared/lib/vue/installRouteGlobal';
const app = createApp({});

// Add global translator function
app.config.globalProperties.__ = window.__;
installRouteGlobal(app);

import TransactionContainerInvestment from './components/form/ContainerInvestment.vue';
app.component('TransactionContainerInvestment', TransactionContainerInvestment);

app.mount('#app');
