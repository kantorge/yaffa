import { initializeBootstrapTooltips } from '@/shared/lib/helpers';

initializeBootstrapTooltips();

// A parent with budget suggestions off excludes this category: lock the checkbox while it is selected
const parentSelect = document.getElementById('parent_id');
const suggestionsCheckbox = document.getElementById(
  'calculate_budget_suggestions',
);
const suggestionsHidden = document.getElementById(
  'calculate_budget_suggestions_hidden',
);
const suggestionsHelp = document.getElementById(
  'calculate_budget_suggestions_help',
);

parentSelect.addEventListener('change', () => {
  const excluded =
    parentSelect.selectedOptions[0]?.dataset.suggestionsOff === '1';
  suggestionsCheckbox.disabled = excluded;
  // A disabled checkbox is not submitted: keep the category's own value in the hidden input
  suggestionsHidden.value = excluded ? suggestionsHidden.dataset.own : 0;
  suggestionsHelp.textContent = excluded
    ? suggestionsHelp.dataset.excluded
    : suggestionsHelp.dataset.default;
});
