import { createApp } from 'vue'

import TransactionFormStandard from './../components/TransactionFormStandard.vue'
const app = createApp({})
app.component('transaction-form-standard', TransactionFormStandard)

app.mount('#app')
