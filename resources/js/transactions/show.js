import { createApp } from 'vue'
import { installRouteGlobal } from '@/vue/installRouteGlobal';
const app = createApp({})

// Add global translator function
app.config.globalProperties.__ = window.__;
installRouteGlobal(app);

// Component for displaying transaction details
import Container from './../components/TransactionDisplay/Container.vue'
app.component('transaction-show-container', Container)

app.mount('#app')
