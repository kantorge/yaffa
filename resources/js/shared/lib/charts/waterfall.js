import * as am4core from '@amcharts/amcharts4/core';

/**
 * Transform raw {category, value, ...} rows into amCharts4 waterfall-ready data.
 * Sorts by descending absolute value (negative values sorted as their absolute
 * counterpart), computes running open/close totals for the step/column series,
 * assigns a color per direction, and optionally appends a synthetic summary bar.
 *
 * Any additional fields present on the raw rows (e.g. category_ids, transaction_types)
 * are preserved on the returned rows.
 *
 * @param {Array<{category: string, value: number}>} rawData
 * @param {Object} [options]
 * @param {string|null} [options.resultLabel] - Label for the trailing summary bar; omit/null to skip it
 * @returns {Array<Object>} Chart-ready rows, each extended with open/stepValue/barValue/color/isResult
 */
export function buildWaterfallChartData(rawData, options = {}) {
  const { resultLabel = null } = options;
  let openHistory = 0;

  const data = (rawData || [])
    .slice()
    .sort((a, b) => {
      let x = a.value;
      let y = b.value;

      // Sort negative values by their absolute magnitude, same as positive ones
      if (x < 0 && y < 0) {
        x *= -1;
        y *= -1;
      }

      return x > y ? -1 : x < y ? 1 : 0;
    })
    .map((row) => {
      const category = { ...row };
      category.open = openHistory;
      category.stepValue = openHistory + category.value;
      category.barValue = openHistory + category.value;

      openHistory = category.barValue;

      category.color =
        category.value > 0 ? am4core.color('green') : am4core.color('red');
      category.isResult = false;

      return category;
    });

  if (resultLabel && data.length > 1) {
    data.push({
      category: resultLabel,
      open: 0,
      stepValue: 0,
      barValue: openHistory,
      value: openHistory,
      color: openHistory > 0 ? am4core.color('green') : am4core.color('red'),
      isResult: true,
    });
  }

  return data;
}
