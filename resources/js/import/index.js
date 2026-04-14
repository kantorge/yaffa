import { createApp } from 'vue';
import { installRouteGlobal } from '@/shared/lib/vue/installRouteGlobal';
import ImportPage from './components/ImportPage.vue';
import CreateStandardTransactionModal from '@/transactions/components/form/ModalStandard.vue';
import QuickViewTransactionModal from '@/transactions/components/display/Modal.vue';

const app = createApp({});

app.config.globalProperties.__ = window.__;
installRouteGlobal(app);

app.component('import-page', ImportPage);
app.component('transaction-create-standard-modal', CreateStandardTransactionModal);
app.component('transaction-quickview-modal', QuickViewTransactionModal);

app.mount('#app');
