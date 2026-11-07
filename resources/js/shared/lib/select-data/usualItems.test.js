import test from 'node:test';
import assert from 'node:assert/strict';
import { buildUsualItems } from './usualItems.js';

const sum = (items) => items.reduce((s, i) => s + i.amount * 1e4, 0) / 1e4;
const st = (id, n) => ({ category_id: id, usage_count: n });

test('HUF-like: whole units, exact sum', () => {
  const r = buildUsualItems({
    stats: [st(1, 6), st(2, 3), st(3, 1)],
    totalAmount: 12345,
    precision: 0,
  });
  assert.equal(sum(r), 12345);
  r.forEach((i) => assert.ok(Number.isInteger(i.amount)));
});

test('EUR-like: 2 decimals, exact sum, remainder to default category', () => {
  const r = buildUsualItems({
    stats: [st(1, 1), st(2, 1), st(3, 1)],
    totalAmount: 10,
    precision: 2,
    defaultCategoryId: 3,
  });
  assert.equal(sum(r), 10);
  assert.equal(r.find((i) => i.category_id === 3).amount, 3.34);
});

test('single category gets everything', () => {
  assert.deepEqual(
    buildUsualItems({ stats: [st(1, 5)], totalAmount: 7.5 }).map(
      (i) => i.amount,
    ),
    [7.5],
  );
});

test('drops deferred and small shares', () => {
  const r = buildUsualItems({
    stats: [st(1, 30), st(2, 20), st(3, 20), st(4, 15), st(5, 14), st(6, 1)],
    deferredIds: [2],
    totalAmount: 100,
    precision: 2,
  });
  assert.deepEqual(
    r.map((i) => i.category_id),
    [1, 3, 4, 5],
  );
  assert.equal(sum(r), 100);
});

test('negative remainder is absorbed, no non-positive items', () => {
  const r = buildUsualItems({
    stats: [st(1, 1), st(2, 1)],
    totalAmount: 1,
    precision: 0,
  });
  assert.equal(sum(r), 1);
  r.forEach((i) => assert.ok(i.amount > 0));
});

test('empty when nothing usable', () => {
  assert.deepEqual(buildUsualItems({ stats: [], totalAmount: 5 }), []);
});
