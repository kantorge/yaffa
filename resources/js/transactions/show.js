import { createApp } from 'vue'

import ShowStandard from './../components/TransactionDisplay/ShowStandard.vue'
const app = createApp({})
app.component('transaction-show-standard', ShowStandard)

app.mount('#app')
