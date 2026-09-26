import { createApp } from 'vue';
import { installRouteGlobal } from '@/shared/lib/vue/installRouteGlobal';
const app = createApp({});

// Add global translator function
app.config.globalProperties.__ = window.__;
installRouteGlobal(app);

import Dashboard from './components/Dashboard.vue';
import CreateStandardTransactionModal from '@/transactions/components/form/ModalStandard.vue';
import CreateInvestmentTransactionModal from '@/transactions/components/form/ModalInvestment.vue';

app.component('Dashboard', Dashboard);
app.component('TransactionCreateStandardModal', CreateStandardTransactionModal);
app.component(
    'TransactionCreateInvestmentModal',
    CreateInvestmentTransactionModal,
);

app.mount('#app');
