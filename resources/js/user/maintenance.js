import { __ } from '@/shared/lib/i18n';
import * as toastHelpers from '@/shared/lib/toast';
import Swal from 'sweetalert2';

document.querySelectorAll('.maintenance-task-btn').forEach((button) => {
    button.addEventListener('click', async () => {
        const confirmText = button.dataset.confirmText;

        if (confirmText) {
            const result = await Swal.fire({
                icon: 'warning',
                text: confirmText,
                confirmButtonText: __('Confirm'),
                cancelButtonText: __('Cancel'),
                showCancelButton: true,
                buttonsStyling: false,
                customClass: {
                    confirmButton: 'btn btn-danger',
                    cancelButton: 'btn btn-outline-secondary ms-3',
                },
            });

            if (!result.isConfirmed) {
                return;
            }
        }

        const url = button.dataset.route;
        const method = button.dataset.method || 'POST';

        button.disabled = true;

        try {
            const response = await fetch(url, {
                method,
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Content-Type': 'application/json',
                },
            });

            const data = await response.json();

            if (response.ok) {
                toastHelpers.showSuccessToast(data.message || __('maintenance.taskSuccessFallback'));
            } else {
                toastHelpers.showWarningToast(data.message || __('maintenance.taskFailureFallback'));
            }
        } catch (error) {
            toastHelpers.showWarningToast(__('maintenance.networkErrorPrefix') + error.message);
        } finally {
            button.disabled = false;
        }
    });
});
