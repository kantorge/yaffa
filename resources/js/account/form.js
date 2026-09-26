import { initializeBootstrapTooltips } from '@/shared/lib/helpers';
import { warnOnCurrencyChange } from '@/shared/lib/ui/currencyChangeWarning';
import { __ } from '@/shared/lib/i18n';

initializeBootstrapTooltips();

const currencySelect = document.getElementById('currency_id');
if (currencySelect) {
  warnOnCurrencyChange(
    currencySelect,
    __(
      'Changing the currency does not convert or recalculate any existing transactions on this account - they keep their current numeric value, now interpreted in the new currency.',
    ),
  );
}
