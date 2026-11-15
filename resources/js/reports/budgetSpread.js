// A budget row's amount is "per cadence period" (a yearly budget = X per year), while the chart
// API returns it whole in the month of each occurrence. In a view finer than the cadence it is
// spread evenly over the months the cadence period covers, starting at the occurrence month.
const MONTHS_PER_FREQUENCY = { MONTHLY: 1, YEARLY: 12 };

/** Cadence length in months; 0 for weekly/daily (always finer than any view). */
export function cadenceMonths(schedule) {
  const base = MONTHS_PER_FREQUENCY[String(schedule?.frequency).toUpperCase()];
  return base ? base * (schedule.interval || 1) : 0;
}

const monthKey = (date) =>
  `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}`;

/**
 * Spreads the budget share of every contributing Budget row whose cadence is longer than the
 * view (`monthsPerRow`: 1, 3 or 12). Works on monthly rows and returns copies (never mutates).
 * The base-currency share of a row is taken proportionally from the period's converted budget,
 * since the breakdown carries nominal amounts only.
 *
 * @param {Array} rows Monthly rows ({period: 'YYYY-MM-DD', budget, budgetBreakdown})
 * @param {number} monthsPerRow
 * @returns {{rows: Array, spread: boolean}} spread tells whether any budget was split
 */
export function spreadBudget(rows, monthsPerRow) {
  const out = rows.map((row) => ({ ...row }));
  const byKey = new Map(out.map((row) => [row.period.slice(0, 7), row]));
  let spread = false;

  const rowAt = (year, monthIndex) => {
    const date = new Date(year, monthIndex, 1);
    const key = monthKey(date);
    if (!byKey.has(key)) {
      const row = {
        period: key + '-01',
        date,
        actual: null,
        budget: 0,
        forecast: 0,
      };
      byKey.set(key, row);
      out.push(row);
    }
    return byKey.get(key);
  };

  rows.forEach((original, index) => {
    const breakdown = original.budgetBreakdown || [];
    const nominal = breakdown.reduce((sum, b) => sum + b.amount, 0);
    const factor = nominal ? original.budget / nominal : 1;
    const year = Number(original.period.slice(0, 4));
    const month = Number(original.period.slice(5, 7)) - 1;

    breakdown.forEach((b) => {
      const months = cadenceMonths(b.transaction_schedule);
      if (months <= monthsPerRow) return;

      spread = true;
      const share = factor * b.amount;
      out[index].budget -= share;
      for (let i = 0; i < months; i++) {
        rowAt(year, month + i).budget += share / months;
      }
    });
  });

  if (spread) {
    // Drop float residue, so an emptied month is really 0
    out.forEach((row) => (row.budget = Math.round(row.budget * 1e6) / 1e6));
    out.sort((a, b) => a.period.localeCompare(b.period));
  }

  return { rows: out, spread };
}
