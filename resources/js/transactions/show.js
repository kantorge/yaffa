import { createApp } from 'vue'
const app = createApp({})

// Add global translator function
app.config.globalProperties.__ = window.__;
// Add global route values
app.config.globalProperties.route = window.route;

// Component for displaying transaction details
import Container from './../components/TransactionDisplay/Container.vue'
app.component('transaction-show-container', Container)

app.mount('#app')
