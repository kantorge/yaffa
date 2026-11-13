import { test } from 'node:test';
import assert from 'node:assert/strict';
import { computeMovingAverage } from './movingAverage.js';

const months = (values, startYear = 2025) =>
  values.map((actual, i) => ({ date: new Date(startYear, i, 1), actual }));

test('monthly window is exactly 12 complete months and skips the open month', () => {
  // Jan 2025 .. Dec 2026 = 24 rows; "today" is mid Dec 2026 so Dec is open
  const rows = months(
    Array.from({ length: 24 }, (_, i) => (i < 12 ? 100 : 200)),
  );
  const out = computeMovingAverage(rows, 'month', new Date(2026, 11, 15));

  // Nov 2026 (index 22): window Dec 2025..Nov 2026 = 1x100 + 11x200
  assert.equal(out[22].movingAverage, (100 + 11 * 200) / 12);
  // Open month carries the previous value
  assert.equal(out[23].movingAverage, out[22].movingAverage);
});

test('uses what is available when history is shorter than the window', () => {
  const out = computeMovingAverage(
    months([10, 20, 30]),
    'month',
    new Date(2026, 5, 1),
  );
  assert.equal(out[2].movingAverage, 20);
});

test('future periods carry the last value', () => {
  const rows = months([10, 20, 30, 0, 0]);
  const out = computeMovingAverage(rows, 'month', new Date(2025, 3, 10)); // April open
  assert.equal(out[2].movingAverage, 20);
  assert.equal(out[3].movingAverage, 20);
  assert.equal(out[4].movingAverage, 20);
});

test('quarter window is 4 quarters and the open quarter is excluded', () => {
  const rows = [0, 1, 2, 3, 4, 5].map((q) => ({
    date: new Date(2025 + Math.floor(q / 4), (q % 4) * 3, 1),
    actual: (q + 1) * 10,
  }));
  const out = computeMovingAverage(rows, 'quarter', new Date(2026, 4, 1)); // Q2 2026 open
  assert.equal(out[4].movingAverage, (20 + 30 + 40 + 50) / 4); // Q1 2026 complete
  assert.equal(out[5].movingAverage, out[4].movingAverage);
});

test('history counts from the first actual, so leading empty rows do not dilute it', () => {
  const rows = months([0, 0, 0, 10, 20]);
  const out = computeMovingAverage(rows, 'month', new Date(2025, 8, 1));
  assert.equal(out[4].movingAverage, 15);
  assert.equal(out[1].movingAverage, undefined);
});
