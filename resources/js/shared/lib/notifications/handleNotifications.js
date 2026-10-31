/**
 * Stores a notification to be displayed on the next page load.
 *
 * @deprecated Currently unused. Prefer storeToast(), which shows a toast on the next page load.
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

/**
 * Stores a toast to be displayed on the next page load.
 *
 * @param {string} type - 'success', 'info', 'warning' or 'error'
 * @param {string} message - The toast body text
 * @returns {void}
 */
export function storeToast(type, message) {
  let pendingToasts = [];
  try {
    pendingToasts = JSON.parse(localStorage.getItem('pendingToasts') || '[]');
  } catch (e) {
    console.error('Failed to parse pending toasts from localStorage:', e);
  }

  pendingToasts.push({ type, message });
  localStorage.setItem('pendingToasts', JSON.stringify(pendingToasts));
}
