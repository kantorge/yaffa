import { createApp } from 'vue'
const app = createApp({})

// Add global translator function
app.config.globalProperties.__ = window.__;

import TransactionFormInvestment from './../components/TransactionFormInvestment.vue'
app.component('transaction-form-investment', TransactionFormInvestment)

app.mount('#app')
