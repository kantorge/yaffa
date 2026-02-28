import { createApp } from 'vue'
import { installRouteGlobal } from '@/vue/installRouteGlobal';
const app = createApp({})

// Add global translator function
app.config.globalProperties.__ = window.__;
installRouteGlobal(app);

import Dashboard from './components/Dashboard.vue'
app.component('dashboard', Dashboard)

app.mount('#app')
