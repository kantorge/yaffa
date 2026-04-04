import { createApp } from 'vue'
import { installRouteGlobal } from '@/shared/lib/vue/installRouteGlobal';
const app = createApp({})
installRouteGlobal(app);

import MyProfile from './MyProfile.vue';
import AiSettings from './AiSettings.vue';
import InvestmentProviderSettings from './InvestmentProviderSettings.vue';
app.component('my-profile', MyProfile)
app.component('ai-settings', AiSettings)
app.component('investment-provider-settings', InvestmentProviderSettings)

app.mount('#app')
