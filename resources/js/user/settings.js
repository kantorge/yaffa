import { createApp } from 'vue'
import { installRouteGlobal } from '@/vue/installRouteGlobal';
const app = createApp({})
installRouteGlobal(app);

import MyProfile from "./MyProfile.vue";
import AiSettings from "./AiSettings.vue";
app.component('my-profile', MyProfile)
app.component('ai-settings', AiSettings)

app.mount('#app')
