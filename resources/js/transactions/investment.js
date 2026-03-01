import { createApp } from 'vue'
import { installRouteGlobal } from '@/vue/installRouteGlobal';
const app = createApp({})

// Add global translator function
app.config.globalProperties.__ = window.__;
installRouteGlobal(app);

import TransactionContainerInvestment from './../components/TransactionForm/ContainerInvestment.vue';
app.component('transaction-container-investment', TransactionContainerInvestment)

app.mount('#app')
