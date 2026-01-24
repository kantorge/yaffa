import { createApp } from 'vue'

import BootstrapNotificationContainer from './components/BootstrapNotificationContainer.vue'
const appNotification = createApp({})
appNotification.component('bootstrap-notification-container', BootstrapNotificationContainer)
appNotification.mount('#BootstrapNotificationContainer')
