import { createApp } from 'vue'
import { installRouteGlobal } from '@/vue/installRouteGlobal';
const app = createApp({})

// Add global translator function
app.config.globalProperties.__ = window.__;
installRouteGlobal(app);

import TransactionContainerStandard from './../components/TransactionForm/ContainerStandard.vue'
app.component('transaction-container-standard', TransactionContainerStandard)

app.mount('#app')
