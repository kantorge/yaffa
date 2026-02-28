import { createApp } from 'vue';
import { installRouteGlobal } from '@/vue/installRouteGlobal';
import InvestmentPriceManager from '../components/InvestmentPrice/InvestmentPriceManager.vue';

const app = createApp({
  components: {
    InvestmentPriceManager,
  },
});

installRouteGlobal(app);
app.mount('#investmentPriceApp');

