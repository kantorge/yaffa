import { createApp } from 'vue';
import { installRouteGlobal } from '@/shared/lib/vue/installRouteGlobal';
import InvestmentProviderForm from './components/form/InvestmentProviderForm.vue';
import { warnOnCurrencyChange } from '@/shared/lib/ui/currencyChangeWarning';
import { __ } from '@/shared/lib/i18n';

const app = createApp({
  components: {
    InvestmentProviderForm,
  },
});

app.config.globalProperties.__ = window.__;
installRouteGlobal(app);
app.mount('#investmentProviderFormApp');

const currencySelect = document.getElementById('currency_id');
if (currencySelect) {
  warnOnCurrencyChange(
    currencySelect,
    __(
      'Changing the currency does not convert or recalculate any existing transactions or investment price values for this investment - they keep their current numeric value, now interpreted in the new currency.',
    ),
  );
}
