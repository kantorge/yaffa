/**
 * Function to display a Toast notification.
 *
 * @param {string} header The header of the toast.
 * @param {string} body The body of the toast.
 * @param {string} toastClass The class of the toast.
 * @param {Object} otherProperties Other properties to pass to the toast.
 *
 * @returns {void}
 */
export function showToast(header, body, toastClass, otherProperties ) {
    otherProperties = otherProperties || {};

    // Emit a custom event to global scope to display the Toast
    let notificationEvent = new CustomEvent('toast', {
        detail: {
            ...otherProperties,
            ...{
                header: header,
                body: body,
                toastClass: toastClass,
            }
        }
    });
    window.dispatchEvent(notificationEvent);
}

/**
 * Function to display a success Toast notification, where only the body is needed.
 *
 * @param {string} body The body of the toast.
 *
 * @returns {void}
 */
export function showSuccessToast(body) {
    showToast(
        __('Success'),
        body,
        'bg-success',
    );
}

/**
 * Function to display an error Toast notification, where only the body is needed.
 *
 * @param {string} body The body of the toast.
 *
 * @returns {void}
 */
export function showErrorToast(body) {
    showToast(
        __('Error'),
        body,
        'bg-danger',
    );
}

/*
 * Function to display a loader Toast notification indefinitely
 *
 * @param {string} body The body of the toast.
 * @param {string} toastClass The class of the toast. (Color is appended with 'bg-info')
 *
 * @returns {void}
 */
export function showLoaderToast(body, toastClass) {
    showToast(
        __('Loading'),
        body,
        toastClass + ' bg-info',
        {
            delay: Infinity
        }
    );
}

/**
 * Function to display a warning Toast notification, where only the body is needed.
 *
 * @param {string} body The body of the toast.
 *
 * @returns {void}
 */
export function showWarningToast(body) {
    showToast(
        __('Warning'),
        body,
        'bg-warning',
    );
}

/**
 * Function to hide and dispose Toast notifications.
 *
 * @param {string} selector The CSS selector for the toast element(s) to hide.
 * @param {number} delay The delay in milliseconds before hiding the toast. Defaults to 250ms.
 *
 * @returns {void}
 */
export function hideToast(selector, delay = 250) {
    const toastElements = document.querySelectorAll(selector);

    if (toastElements.length === 0) {
        return;
    }

    toastElements.forEach((toastElement) => {
        const toastInstance = new window.bootstrap.Toast(toastElement);
        setTimeout(() => {
            try {
                toastInstance.dispose();
            } catch (e) {
                // Ignore errors, the toast might have been already disposed
                if (import.meta.env.DEV) {
                    console.error('Error disposing toast:', e);
                }
            }
        }, delay);
    });
}