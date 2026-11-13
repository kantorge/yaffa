import { test } from 'node:test';
import assert from 'node:assert/strict';
import {
  lastCompleteMonth,
  groupUnitsByVerdict,
  buildFormPrefill,
  usableCandidates,
  indexSeries,
  loadPrefs,
} from './budgetSuggestions.js';

test('last complete month crosses the year boundary', () => {
  assert.equal(lastCompleteMonth(new Date(2027, 0, 5)), '2026-12');
  assert.equal(lastCompleteMonth(new Date(2026, 11, 3)), '2026-11');
});

test('groups non-suggestion units by verdict in a stable order', () => {
  const groups = groupUnitsByVerdict([
    { verdict: 'irregular' },
    { verdict: 'suggestion' },
    { verdict: 'no_drift' },
    { verdict: 'irregular' },
  ]);
  assert.deepEqual(
    groups.map((g) => [g.verdict, g.units.length]),
    [
      ['no_drift', 1],
      ['irregular', 2],
    ],
  );
});

test('replace prefill uses the picked candidate, as a positive amount with a type', () => {
  const unit = {
    suggestion: {
      proposal_type: 'replace',
      effective_date: '2027-01-01',
      suggested_amount: -1,
    },
  };
  assert.deepEqual(
    buildFormPrefill(unit, { suggested_amount: -27500.456 }, 0),
    {
      amount: 27500,
      transaction_type: 'withdrawal',
      start_date: '2027-01-01',
    },
  );
});

test('create prefill carries category and cadence', () => {
  const unit = {
    category_id: 57,
    category_name: 'Home > Maintenance',
    suggestion: {
      proposal_type: 'create',
      effective_date: '2027-01-01',
      suggested_amount: 45000,
      cadence: { frequency: 'MONTHLY', interval: 3 },
    },
  };
  const p = buildFormPrefill(unit);
  assert.equal(p.transaction_type, 'deposit');
  assert.equal(p.interval, 3);
  assert.equal(p.category_id, 57);
});

test('candidates with a null amount are not offered', () => {
  const s = {
    candidates: [{ suggested_amount: null }, { suggested_amount: -5 }],
  };
  assert.equal(usableCandidates(s).length, 1);
});

test('series is indexed by month', () => {
  assert.equal(
    indexSeries([{ period: '2027-01-01', recommended: -5 }]).get('2027-01'),
    -5,
  );
  assert.equal(indexSeries(null).size, 0);
});

test('broken stored preferences give an empty object', () => {
  assert.deepEqual(loadPrefs({ getItem: () => '{not json' }), {});
});
