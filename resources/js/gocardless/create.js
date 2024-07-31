import { createApp } from 'vue'
const app = createApp({})

import CreateRequisition from "./CreateRequisition.vue";
app.component('create-requisition', CreateRequisition)

app.mount('#app')
