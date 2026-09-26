import Swal from 'sweetalert2';

/**
 * Show a destructive-action confirm dialog with the app's standard styling.
 *
 * @param {string} text The confirm dialog body text.
 * @param {Object} options Optional overrides.
 * @param {string} [options.title] Dialog title.
 * @param {string} [options.confirmButtonText] Defaults to "Confirm".
 * @param {string|HTMLElement} [options.target] Element (or selector) to append the popup to.
 *        Defaults to SweetAlert2's own default of 'body'. Pass the modal element (or a descendant
 *        of it) when confirming inside a Bootstrap/CoreUI modal - otherwise the modal's focus trap
 *        treats the popup as "outside" and steals focus back into the modal on every focusin.
 *
 * @returns {Promise} The SweetAlert2 result promise.
 */
export function confirmDelete(text, options = {}) {
    return Swal.fire({
        animation: false,
        icon: 'warning',
        text: text,
        title: options.title,
        showCancelButton: true,
        buttonsStyling: false,
        customClass: {
            confirmButton: 'btn btn-danger',
            cancelButton: 'btn btn-secondary ms-3',
        },
        cancelButtonText: __('Cancel'),
        confirmButtonText: options.confirmButtonText || __('Confirm'),
        ...(options.target ? { target: options.target } : {}),
    });
}

/**
 * Show a non-destructive confirm dialog with the app's standard styling.
 *
 * @param {string} text The confirm dialog body text.
 * @param {Object} options Optional overrides.
 * @param {string} [options.title] Dialog title.
 * @param {string} [options.icon] Defaults to "question".
 * @param {string} [options.confirmButtonText] Defaults to "Confirm".
 * @param {string|HTMLElement} [options.target] Element (or selector) to append the popup to.
 *        Defaults to SweetAlert2's own default of 'body'. Pass the modal element (or a descendant
 *        of it) when confirming inside a Bootstrap/CoreUI modal - otherwise the modal's focus trap
 *        treats the popup as "outside" and steals focus back into the modal on every focusin.
 *
 * @returns {Promise} The SweetAlert2 result promise.
 */
export function confirmAction(text, options = {}) {
    return Swal.fire({
        animation: false,
        icon: options.icon || 'question',
        text: text,
        title: options.title,
        showCancelButton: true,
        buttonsStyling: false,
        customClass: {
            confirmButton: 'btn btn-primary',
            cancelButton: 'btn btn-secondary ms-3',
        },
        cancelButtonText: __('Cancel'),
        confirmButtonText: options.confirmButtonText || __('Confirm'),
        ...(options.target ? { target: options.target } : {}),
    });
}
