import { createApp } from 'vue'
import { installRouteGlobal } from '@/vue/installRouteGlobal';
const app = createApp({})
installRouteGlobal(app);
import FindTransactions from "../components/FindTransactions.vue";
app.component('find-transactions', FindTransactions)
app.mount('#app')
