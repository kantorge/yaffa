import { createApp } from 'vue'
import { installRouteGlobal } from '@/vue/installRouteGlobal';
const app = createApp({})
installRouteGlobal(app);

import MyProfile from "./MyProfile.vue";
app.component('my-profile', MyProfile)

app.mount('#app')
