import { createApp } from 'vue'
const app = createApp({})

// Add global translator function
app.config.globalProperties.__ = window.__;

import TransactionContainerInvestment from './../components/TransactionForm/ContainerInvestment.vue';
app.component('transaction-container-investment', TransactionContainerInvestment)

app.mount('#app')
