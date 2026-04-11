import { createApp } from 'vue'
import { installRouteGlobal } from '@/shared/lib/vue/installRouteGlobal';

import BootstrapNotificationContainer from '@/shared/ui/notifications/BootstrapNotificationContainer.vue'
const appNotification = createApp({})
installRouteGlobal(appNotification);
appNotification.component('bootstrap-notification-container', BootstrapNotificationContainer)
appNotification.mount('#BootstrapNotificationContainer')
