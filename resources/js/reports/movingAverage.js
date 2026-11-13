const MONTHS_PER_INTERVAL = { month: 1, quarter: 3, year: 12 };
// Window size in periods: 12 months, 4 quarters, 5 years. Same window as the budget
// suggestions detector's level L (config budget_suggestions.level_window).
const WINDOW_PERIODS = { month: 12, quarter: 4, year: 5 };

/**
 * Moving average of the `actual` values, written to `movingAverage` on each row.
 * Rows must be sorted by date and contiguous at the given interval.
 * Only complete periods are averaged: the open (current) period and future periods
 * carry the last value, so the line equals the detector's level L.
 *
 * @param {Array<{date: Date, actual: number}>} baseData
 * @param {'month'|'quarter'|'year'} interval
 * @param {Date} now
 */
export function computeMovingAverage(baseData, interval, now = new Date()) {
  const months = MONTHS_PER_INTERVAL[interval] || 1;
  const size = WINDOW_PERIODS[interval] || WINDOW_PERIODS.month;
  const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());

  return baseData.map((item, index) => {
    const periodEnd = new Date(
      item.date.getFullYear(),
      item.date.getMonth() + months,
      1,
    );

    if (periodEnd > today) {
      if (index > 0) {
        item.movingAverage = baseData[index - 1].movingAverage;
      }
      return item;
    }

    const window = baseData.slice(Math.max(0, index - size + 1), index + 1);
    item.movingAverage =
      window.reduce((sum, row) => sum + (row.actual || 0), 0) / window.length;

    return item;
  });
}
