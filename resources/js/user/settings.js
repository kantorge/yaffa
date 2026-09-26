import { createApp } from 'vue';
import { installRouteGlobal } from '@/shared/lib/vue/installRouteGlobal';
const app = createApp({});
installRouteGlobal(app);

import MyProfile from './MyProfile.vue';
import AiSettings from './AiSettings.vue';
import InvestmentProviderSettings from './InvestmentProviderSettings.vue';
app.component('MyProfile', MyProfile);
app.component('AiSettings', AiSettings);
app.component('InvestmentProviderSettings', InvestmentProviderSettings);

app.mount('#app');
