import { createApp } from 'vue'
import { installRouteGlobal } from '@/shared/lib/vue/installRouteGlobal';
const app = createApp({})

// Add global translator function
app.config.globalProperties.__ = window.__;
installRouteGlobal(app);

// Component for displaying transaction details
import Container from './components/display/Container.vue'
app.component('transaction-show-container', Container)

app.mount('#app')
