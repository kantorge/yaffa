/**
 * Filter a list of date-bearing records to those falling within [dateFrom, dateTo] (inclusive).
 * Either bound may be null/omitted to leave that side open-ended.
 *
 * @param {Array<Object>} items
 * @param {string} dateField Property on each item holding a date (Date object or date string).
 * @param {string|Date|null} dateFrom
 * @param {string|Date|null} dateTo
 * @returns {Array<Object>}
 */
export function filterByDateRange(items, dateField, dateFrom, dateTo) {
  const from = dateFrom ? new Date(dateFrom).getTime() : null;
  const to = dateTo ? new Date(dateTo).getTime() : null;

  return items.filter((item) => {
    const ts = new Date(item[dateField]).getTime();

    if (from !== null && ts < from) return false;
    if (to !== null && ts > to) return false;

    return true;
  });
}
