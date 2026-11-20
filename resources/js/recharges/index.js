// Recharge report pages are server-rendered; this only applies the app's locale formatting
// to the amounts and dates, which stay readable (raw) if the script does not run.
import { toFormattedCurrency, toFormattedDate } from '@/shared/lib/i18n';

const locale = window.YAFFA?.userSettings?.locale || 'en';

document.querySelectorAll('[data-money-amount]').forEach((element) => {
  let currency;
  try {
    currency = JSON.parse(element.dataset.moneyCurrency || 'null');
  } catch {
    currency = null;
  }

  element.textContent = toFormattedCurrency(
    element.dataset.moneyAmount,
    locale,
    currency,
  );
});

document.querySelectorAll('[data-date]').forEach((element) => {
  const date = new Date(`${element.dataset.date}T00:00:00`);
  if (!isNaN(date)) {
    element.textContent = toFormattedDate(date, locale, null);
  }
});
