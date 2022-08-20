import { createApp } from 'vue'

import TransactionContainerStandard from './../components/TransactionForm/ContainerStandard.vue'
const app = createApp({})
app.component('transaction-container-standard', TransactionContainerStandard)

app.mount('#app')
