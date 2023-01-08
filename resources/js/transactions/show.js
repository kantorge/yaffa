import { createApp } from 'vue'
const app = createApp({})

// Add global translator function
app.config.globalProperties.__ = window.__;

// Component for displaying transaction details
import ShowStandard from './../components/TransactionDisplay/ShowStandard.vue'
app.component('transaction-show-standard', ShowStandard)

app.mount('#app')
