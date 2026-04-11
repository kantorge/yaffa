import { createApp } from 'vue'
import { installRouteGlobal } from '@/shared/lib/vue/installRouteGlobal';
const app = createApp({})
installRouteGlobal(app);
import FindTransactions from './components/find-transactions/FindTransactions.vue';
app.component('find-transactions', FindTransactions)
app.mount('#app')
