window.calculateYears = function (from, to) {
    const diffMs = to - from;
    const diffDate = new Date(diffMs); // milliseconds from epoch
    return Math.abs(diffDate.getUTCFullYear() - 1970);
}

// Initialize the Vue component for the content display
import { createApp } from 'vue';
import { installRouteGlobal } from '@/shared/lib/vue/installRouteGlobal';
const app = createApp({});

// Add global translator function
app.config.globalProperties.__ = window.__;
installRouteGlobal(app);

import InvestmentDisplayContainer from './components/display/InvestmentDisplayContainer.vue';
app.component('InvestmentDisplayContainer', InvestmentDisplayContainer);

app.mount('#app')
