import { createApp } from 'vue';
import { installRouteGlobal } from '@/shared/lib/vue/installRouteGlobal';
const app = createApp({});

// Add global translator function
app.config.globalProperties.__ = window.__;
installRouteGlobal(app);

import Dashboard from './components/Dashboard.vue';
import CreateStandardTransactionModal from '@/transactions/components/form/ModalStandard.vue';
import CreateInvestmentTransactionModal from '@/transactions/components/form/ModalInvestment.vue';

app.component('dashboard', Dashboard);
app.component('transaction-create-standard-modal', CreateStandardTransactionModal);
app.component(
  'transaction-create-investment-modal',
  CreateInvestmentTransactionModal,
);

app.mount('#app');
