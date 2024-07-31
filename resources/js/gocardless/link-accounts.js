import { createApp } from 'vue'
const app = createApp({})

import LinkAccounts from "./LinkAccounts.vue";
app.component('link-accounts', LinkAccounts)

app.mount('#app')
