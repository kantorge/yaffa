import { test } from 'node:test';
import assert from 'node:assert/strict';
import { spreadBudget } from './budgetSpread.js';

const budgetRow = (period, amount, frequency, interval = 1) => ({
  period,
  actual: null,
  budget: amount,
  forecast: 0,
  budgetBreakdown: [{ amount, transaction_schedule: { frequency, interval } }],
});

test('yearly budget is split over 12 months in the monthly view', () => {
  const { rows, spread } = spreadBudget(
    [budgetRow('2026-03-01', -1200, 'YEARLY')],
    1,
  );
  assert.equal(spread, true);
  assert.equal(rows.length, 12);
  assert.equal(rows[0].period, '2026-03-01');
  assert.equal(rows[11].period, '2027-02-01');
  rows.forEach((row) => assert.equal(row.budget, -100));
});

test('yearly budget is left alone in the yearly view', () => {
  const { rows, spread } = spreadBudget(
    [budgetRow('2026-03-01', -1200, 'YEARLY')],
    12,
  );
  assert.equal(spread, false);
  assert.equal(rows[0].budget, -1200);
});

test('every 4 months: split in monthly view, not in quarterly', () => {
  const input = [budgetRow('2026-01-01', -400, 'MONTHLY', 4)];
  assert.equal(spreadBudget(input, 1).rows.length, 4);
  assert.equal(spreadBudget(input, 1).rows[0].budget, -100);
  assert.equal(spreadBudget(input, 3).spread, true);
  assert.equal(spreadBudget(input, 12).spread, false);
});

test('monthly budgets are never split, and the input is not mutated', () => {
  const input = [budgetRow('2026-01-01', -50, 'MONTHLY')];
  assert.equal(spreadBudget(input, 1).spread, false);
  const yearly = [budgetRow('2026-01-01', -1200, 'YEARLY')];
  spreadBudget(yearly, 1);
  assert.equal(yearly[0].budget, -1200);
});

test('overlapping periods add up', () => {
  const { rows } = spreadBudget(
    [
      {
        ...budgetRow('2026-01-01', -1200, 'YEARLY'),
        budget: -1250,
        budgetBreakdown: [
          {
            amount: -1200,
            transaction_schedule: { frequency: 'YEARLY', interval: 1 },
          },
          {
            amount: -50,
            transaction_schedule: { frequency: 'MONTHLY', interval: 1 },
          },
        ],
      },
    ],
    1,
  );
  assert.equal(rows[0].budget, -150); // 100 (yearly share) + 50 (monthly stays)
  assert.equal(rows[1].budget, -100);
});
