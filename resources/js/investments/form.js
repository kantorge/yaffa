import { createApp } from 'vue';
import { installRouteGlobal } from '@/shared/lib/vue/installRouteGlobal';
import InvestmentProviderForm from './components/form/InvestmentProviderForm.vue';

const app = createApp({
  components: {
    InvestmentProviderForm,
  },
});

app.config.globalProperties.__ = window.__;
installRouteGlobal(app);
app.mount('#investmentProviderFormApp');
