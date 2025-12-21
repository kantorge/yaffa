import { createApp } from 'vue';
import InvestmentPriceManager from '../components/InvestmentPrice/InvestmentPriceManager.vue';

const app = createApp({
  components: {
    InvestmentPriceManager,
  },
});

app.mount('#investmentPriceApp');

