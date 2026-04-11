/**
 * Stores a notification to be displayed on the next page load.
 *
 * @param {string} type - The type of notification (e.g., 'success', 'error', 'warning', 'info')
 * @param {string} message - The notification message text
 * @param {Object} [options={}] - Optional configuration object for additional notification settings
 * @returns {void}
 */
export function storeNotification(type, message, options = {}) {
    let pendingNotifications;
    try {
        pendingNotifications =
        JSON.parse(
            localStorage.getItem('pendingBootstrapNotifications') || '[]',
        ) || [];
    } catch (e) {
        console.error(
            'Failed to parse pending notifications from localStorage:',
            e,
        );
        pendingNotifications = [];
    }

    pendingNotifications.push({
        type: type,
        message: message,
        title: options.title || '',
        icon: options.icon || '',
        dismissible: options.dismissible || false,
        timeout: options.timeout || 0,
    });

    localStorage.setItem(
        'pendingBootstrapNotifications',
        JSON.stringify(pendingNotifications),
    );
}