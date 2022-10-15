import { createApp } from 'vue'
const app = createApp({})

// Add global translator function
app.config.globalProperties.__ = window.__;

import TransactionContainerStandard from './../components/TransactionForm/ContainerStandard.vue'
app.component('transaction-container-standard', TransactionContainerStandard)

app.mount('#app')
