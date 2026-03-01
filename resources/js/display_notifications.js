import { createApp } from 'vue'
import { installRouteGlobal } from '@/vue/installRouteGlobal';

import BootstrapNotificationContainer from './components/BootstrapNotificationContainer.vue'
const appNotification = createApp({})
installRouteGlobal(appNotification);
appNotification.component('bootstrap-notification-container', BootstrapNotificationContainer)
appNotification.mount('#BootstrapNotificationContainer')
