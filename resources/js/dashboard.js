import { createApp } from 'vue'
const app = createApp({})

// Add global translator function
app.config.globalProperties.__ = window.__;

import Dashboard from './components/Dashboard.vue'
app.component('dashboard', Dashboard)

app.mount('#app')
