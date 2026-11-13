import { createApp, h, ref } from 'vue';
import MathInput from '@/shared/ui/form/MathInput.vue';
import { initializeBootstrapTooltips } from '@/shared/lib/helpers';
import { warnOnCurrencyChange } from '@/shared/lib/ui/currencyChangeWarning';
import { __ } from '@/shared/lib/i18n';
import { createLocalSelect } from '@/shared/lib/tom-select';

initializeBootstrapTooltips();

// Type-to-filter for the settlement account; the server already lists only the eligible (active) accounts
const settlementSelect = document.getElementById('settlement_account_id');
if (settlementSelect) {
  const accounts = [...settlementSelect.options]
    .filter((option) => option.value !== '')
    .map((option) => ({ id: option.value, text: option.text.trim() }));

  createLocalSelect(settlementSelect, {
    suggestions: () => [
      { id: 'accounts', label: __('Accounts'), items: accounts },
    ],
    search: (term) => {
      const needle = term.trim().toLowerCase();
      return accounts.filter((account) =>
        account.text.toLowerCase().includes(needle),
      );
    },
    placeholder: __('— None —'),
  });
}

const currencySelect = document.getElementById('currency_id');
if (currencySelect) {
  warnOnCurrencyChange(
    currencySelect,
    __(
      'Changing the currency does not convert or recalculate any existing transactions on this account - they keep their current numeric value, now interpreted in the new currency.',
    ),
  );
}

// MathInput accepts locale decimal separators and simple math expressions;
// it submits with the native form through the plain `name` attribute.
const openingBalanceEl = document.getElementById('opening-balance');
if (openingBalanceEl) {
  const value = ref(openingBalanceEl.dataset.value);
  createApp({
    render: () =>
      h(MathInput, {
        id: 'opening_balance',
        class: 'form-control',
        name: 'config[opening_balance]',
        modelValue: value.value,
        'onUpdate:modelValue': (v) => (value.value = v),
      }),
  }).mount(openingBalanceEl);
}
