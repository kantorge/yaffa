import Swal from 'sweetalert2';
import { __ } from '@/shared/lib/i18n';

/**
 * Warns the user, via a SweetAlert2 alert, the moment they change a currency <select> away
 * from the value it was loaded with (edit mode only - a create form has no
 * data-original-currency-id, so this is a no-op there). Purely informational: the backend
 * form request may separately reject the change outright once existing transactions exist,
 * but even when it doesn't, no conversion/recalculation of existing data happens - this
 * makes that explicit before the user submits.
 *
 * @param {HTMLSelectElement} selectEl
 * @param {string} message
 */
export function warnOnCurrencyChange(selectEl, message) {
  const originalCurrencyId = selectEl.dataset.originalCurrencyId;

  if (!originalCurrencyId) {
    return;
  }

  selectEl.addEventListener('change', () => {
    if (selectEl.value === originalCurrencyId) {
      return;
    }

    Swal.fire({
      animation: false,
      icon: 'warning',
      text: message,
      confirmButtonText: __('OK'),
      buttonsStyling: false,
      customClass: {
        confirmButton: 'btn btn-primary',
      },
    });
  });
}
