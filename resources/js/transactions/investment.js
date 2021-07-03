import { createApp } from 'vue'

import TransactionFormInvestment from './../components/TransactionFormInvestment.vue'
const app = createApp({})
app.component('transaction-form-investment', TransactionFormInvestment)

app.mount('#app')
