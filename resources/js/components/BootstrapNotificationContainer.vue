<template>
    <div
        v-for="(item, index) in notifications"
        :key="index"
    >
        <bootstrap-notification
            :dismissable="item.dismissable"
            :type="item.type"
            :title="item.title"
            :message="item.message"
            :icon="item.icon"
        ></bootstrap-notification>
    </div>
</template>

<script>
    import BootstrapNotification from './BootstrapNotification.vue'
    export default {
        name: 'BootstrapNotificationContainer',
        components: {
            'bootstrap-notification': BootstrapNotification,
        },
        props: {
            initialNotifications: {
                type: Array,
                required: true,
            },
        },
        data() {
            return {
                notifications: this.initialNotifications,
            };
        },
        mounted() {
            // Set event handlers for alert closed event
            $('#BootstrapNotificationContainer div.alert').on('closed.bs.alert', function () {
                // Remove the closed alert from the list
                let index = this.notifications.findIndex(item => item.id === $(this).attr('id'));
                this.notifications.splice(index, 1);
            }.bind(this));

            // Set up a global event listener for the 'notification' event
            window.addEventListener('notification', function (event) {
                // Add the notification to the list
                // Notification is expected to contain the required properties: type, message, title, icon, dismissable
                this.notifications.push(event.detail.notification);
            }.bind(this));
        },
    }
</script>
