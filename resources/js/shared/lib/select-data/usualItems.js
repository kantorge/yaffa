import Decimal from 'decimal.js';

export const MIN_SHARE = 0.1;

/**
 * Split totalAmount over the payee's usual categories, by usage share.
 * Returns [{category_id, category_full_name, amount}] whose amounts sum exactly to totalAmount.
 */
export function buildUsualItems({
  stats,
  deferredIds = [],
  totalAmount,
  precision = 2,
  defaultCategoryId = null,
}) {
  const total = new Decimal(totalAmount);
  const usable = (stats || [])
    .map((s) => ({
      category_id: Number(s.category_id),
      category_full_name: s.category_full_name || null,
      usage_count: Number(s.usage_count),
    }))
    .filter(
      (s) =>
        s.category_id > 0 &&
        s.usage_count > 0 &&
        !deferredIds.map(Number).includes(s.category_id),
    )
    .sort((a, b) => b.usage_count - a.usage_count);

  const all = usable.reduce((sum, s) => sum + s.usage_count, 0);
  // The share threshold alone bounds the list (at most 10 items).
  const kept = usable.filter((s) => s.usage_count / all >= MIN_SHARE);
  if (kept.length === 0 || !total.gt(0)) {
    return [];
  }

  const keptSum = kept.reduce((sum, s) => sum + s.usage_count, 0);
  // Whole-unit currencies get a "nice" step: 10 for big totals, else 1.
  const places = precision ?? 2;
  const step = places === 0 && total.gte(1000) ? 10 : null;
  const round = (d) =>
    step
      ? d.dividedBy(step).toDecimalPlaces(0).times(step)
      : d.toDecimalPlaces(places);

  const items = kept.map((s) => ({
    category_id: s.category_id,
    category_full_name: s.category_full_name,
    amount: round(total.times(s.usage_count).dividedBy(keptSum)),
  }));

  const remainder = total.minus(
    items.reduce((sum, i) => sum.plus(i.amount), new Decimal(0)),
  );
  const target =
    items.find((i) => i.category_id === Number(defaultCategoryId)) ||
    items.reduce((a, b) => (b.amount.gt(a.amount) ? b : a));
  target.amount = target.amount.plus(remainder);

  return items
    .filter((i) => i.amount.gt(0))
    .map((i) => ({ ...i, amount: i.amount.toNumber() }));
}
