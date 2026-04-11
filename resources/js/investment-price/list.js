import { createApp } from 'vue';
import { installRouteGlobal } from '@/shared/lib/vue/installRouteGlobal';
import InvestmentPriceManager from './components/InvestmentPriceManager.vue';

const app = createApp({
  components: {
    InvestmentPriceManager,
  },
});

installRouteGlobal(app);
app.mount('#investmentPriceApp');

