import { __ } from '@/shared/lib/i18n';
import * as toastHelpers from '@/shared/lib/toast';

document.querySelectorAll('.maintenance-task-btn').forEach((button) => {
    button.addEventListener('click', async () => {
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
