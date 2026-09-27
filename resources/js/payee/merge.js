import { __ } from '@/shared/lib/i18n';
import { createRemoteSelect, setSelected } from '@/shared/lib/tom-select';
import { confirmAction } from '@/shared/lib/confirm';

const sourceElement = document.getElementById('payee_source');
const targetElement = document.getElementById('payee_target');

const createPayeeSelect = (element, otherElement, placeholder) =>
  createRemoteSelect(element, {
    url: '/api/v1/payees',
    params: (term) => ({
      q: term || undefined,
      account_type: 'payee',
      withInactive: true,
    }),
    mapResult: (item) => ({ id: item.id, text: item.name }),
    // Exclude the payee selected on the other side
    filterResults: (results) =>
      results.filter((item) => String(item.id) !== otherElement.value),
    placeholder,
  });

const sourceSelect = createPayeeSelect(
  sourceElement,
  targetElement,
  __('Select payee to be merged'),
);
createPayeeSelect(
  targetElement,
  sourceElement,
  __('Select payee to be merged into'),
);

// Preset the source payee if provided in the URL
if (window.payeeSource) {
  setSelected(
    sourceSelect,
    { id: window.payeeSource.id, text: window.payeeSource.name },
    { silent: true },
  );
}

// Add confirm dialog to submit button
$('#merge-payees-form').on('submit', function (e) {
  // Validate if both selects have a value
  const source = sourceElement.value;
  const target = targetElement.value;

  if (!source || !target) {
    e.preventDefault();
    alert(__('Please select payees to be merged'));
    return;
  }

  // Validate if both selects are not the same
  if (source === target) {
    e.preventDefault();
    alert(__('Please select different payees to be merged'));
    return;
  }

  // Validate if action radio button is selected
  let action = $('input[name=action]:checked').val();
  if (typeof action === 'undefined') {
    e.preventDefault();
    alert(__('Please select an action'));
    return;
  }

  e.preventDefault();
  const form = e.target;

  confirmAction(__('Are you sure you want to merge these payees?'), {
    icon: 'warning',
  }).then((result) => {
    if (result.isConfirmed) {
      form.submit();
    }
  });
});

// Cancel button behaviour
$('#cancel').on('click', function () {
  confirmAction(__('Are you sure you want to discard any changes?'), {
    icon: 'warning',
  }).then((result) => {
    if (result.isConfirmed) {
      window.history.back();
    }
  });
});
