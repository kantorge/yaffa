// Initialize the Vue component for Currency Rate Management
import { createApp } from 'vue';
const app = createApp({});

// Add global translator function
app.config.globalProperties.__ = window.__;

import CurrencyRateManager from './../components/CurrencyRateManager.vue';
app.component('currency-rate-manager', CurrencyRateManager);

app.mount('#currencyRateApp');

