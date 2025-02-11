import { createApp } from 'vue'
const app = createApp({})
import FindTransactions from "../components/FindTransactions.vue";
app.component('find-transactions', FindTransactions)
app.mount('#app')
