import { createApp } from 'vue'
const app = createApp({})

import MyProfile from "./MyProfile.vue";
app.component('my-profile', MyProfile)

app.mount('#app')
