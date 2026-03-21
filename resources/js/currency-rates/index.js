// Initialize the Vue component for Currency Rate Management
import { createApp } from 'vue';
import { installRouteGlobal } from '@/shared/lib/vue/installRouteGlobal';
const app = createApp({});

// Add global translator function
app.config.globalProperties.__ = window.__;
installRouteGlobal(app);

import CurrencyRateManager from './components/CurrencyRateManager.vue';
app.component('currency-rate-manager', CurrencyRateManager);

app.mount('#currencyRateApp');

