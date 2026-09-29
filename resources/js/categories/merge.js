import { __ } from '@/shared/lib/i18n';
import { createRemoteSelect, setSelected } from '@/shared/lib/tom-select';
import { confirmAction } from '@/shared/lib/confirm';

const sourceElement = document.getElementById('category_source');
const targetElement = document.getElementById('category_target');

// Whether the selected category is a parent (null: nothing selected), read by the submit handler
const isParent = new Map([
  [sourceElement, null],
  [targetElement, null],
]);

const createCategorySelect = (element, otherElement, placeholder) =>
  createRemoteSelect(element, {
    url: '/api/v1/categories',
    params: (term) => ({ q: term || undefined, withInactive: true }),
    mapResult: (item) => ({ id: item.id, text: item.full_name }),
    // Exclude the category selected on the other side
    filterResults: (results) =>
      results.filter((item) => String(item.id) !== otherElement.value),
    placeholder,
    onSelect: (item) => {
      window.axios
        .get('/api/v1/categories/' + item.id)
        .then(({ data }) => isParent.set(element, !data.parent));
    },
    onClear: () => isParent.set(element, null),
  });

const sourceSelect = createCategorySelect(
  sourceElement,
  targetElement,
  __('Select category to be merged'),
);
createCategorySelect(
  targetElement,
  sourceElement,
  __('Select category to be merged into'),
);

// Preset the source category if provided in the URL
if (window.categorySource?.id) {
  setSelected(sourceSelect, {
    id: window.categorySource.id,
    text: window.categorySource.full_name,
  });
}

// Add confirm dialog to submit button
$('#merge-categories-form').on('submit', function (e) {
  // Validate if both selects have a value
  const source = sourceElement.value;
  const target = targetElement.value;

  if (!source || !target) {
    e.preventDefault();
    alert(__('Please select categories to be merged'));
    return;
  }

  // Validate if both selects are not the same
  if (source === target) {
    e.preventDefault();
    alert(__('Please select different categories to be merged'));
    return;
  }

  // Validate if action radio button is selected
  let action = $('input[name=action]:checked').val();
  if (typeof action === 'undefined') {
    e.preventDefault();
    alert(__('Please select an action'));
    return;
  }

  // Validate invalid combination where source category is a parent, and target category is a child
  if (
    isParent.get(sourceElement) === true &&
    isParent.get(targetElement) === false
  ) {
    e.preventDefault();
    alert(__('You cannot merge a parent category into a child category.'));
    return;
  }

  e.preventDefault();
  const form = e.target;

  confirmAction(__('Are you sure you want to merge these categories?'), {
    icon: 'warning',
  }).then((result) => {
    if (result.isConfirmed) {
      form.submit();
    }
  });
});

// Cancel button behaviour
document.getElementById('cancel').addEventListener('click', function () {
  confirmAction(__('Are you sure you want to discard any changes?'), {
    icon: 'warning',
  }).then((result) => {
    if (result.isConfirmed) {
      window.history.back();
    }
  });
});
