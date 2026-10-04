import { __ } from '@/shared/lib/i18n';
import * as toastHelpers from '@/shared/lib/toast';
import { toIsoDateString } from '@/shared/lib/helpers';

// Same limit as for AI drafts: longer item lists do not fit in the modal
const MAX_ITEMS_IN_MODAL = 3;

function openStandalone(draft, templateId) {
  const csrfToken =
    window.csrfToken ||
    document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

  if (!csrfToken) {
    toastHelpers.showErrorToast(__('Unable to open the transaction form.'));
    return;
  }

  const form = document.createElement('form');
  form.method = 'POST';
  form.action = window.route('transactions.createFromDraft');
  form.style.display = 'none';

  const fields = {
    _token: csrfToken,
    transaction: JSON.stringify(draft),
    transaction_template_id: String(templateId),
  };
  Object.entries(fields).forEach(([name, value]) => {
    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = name;
    input.value = value;
    form.appendChild(input);
  });

  document.body.appendChild(form);
  form.submit();
}

/**
 * Open the transaction form prefilled from a template: in the modal of the current page, or on a
 * standalone page when the template has many items or the page has no modal (preferModal = false).
 */
export function useTemplate(templateId, { preferModal = true } = {}) {
  return window.axios
    .get(
      window.route('api.v1.transaction-templates.show', {
        template: templateId,
      }),
    )
    .then(({ data }) => {
      const draft = data.draft;
      draft.config = draft.config || {};
      draft.transaction_items = draft.transaction_items || [];
      // A template has no date: a new transaction starts today
      draft.date = toIsoDateString();

      if (data.notices.length > 0) {
        toastHelpers.showInfoToast(
          __(
            'Some saved values of the template are no longer available and were left empty.',
          ),
        );
      }

      const tooManyItems =
        draft.config_type === 'standard' &&
        draft.transaction_items.length > MAX_ITEMS_IN_MODAL;

      if (!preferModal || tooManyItems) {
        openStandalone(draft, templateId);
        return;
      }

      window.dispatchEvent(
        new CustomEvent('initiateCreateFromDraft', {
          detail: { type: draft.config_type, transaction: draft, templateId },
        }),
      );
    })
    .catch(() => {
      toastHelpers.showErrorToast(__('Unable to load the template.'));
    });
}
