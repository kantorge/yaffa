import { test } from 'node:test';
import assert from 'node:assert/strict';
import {
  lastCompleteMonth,
  groupUnitsByVerdict,
  buildFormPrefill,
  usableCandidates,
  indexSeries,
  applyRecommended,
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
  assert.equal(p.end_date, undefined);
});

test('create prefill carries the end date', () => {
  const unit = {
    category_id: 57,
    category_name: 'Tax',
    suggestion: {
      proposal_type: 'create',
      effective_date: '2027-01-01',
      suggested_amount: -97000,
      end_date: '2045-12-31',
      cadence: { frequency: 'MONTHLY', interval: 3 },
    },
  };
  assert.equal(buildFormPrefill(unit).end_date, '2045-12-31');
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

test('applyRecommended: monthly rows are keyed by their period text', () => {
  const rows = [{ period: '2027-01-01', date: new Date('2027-01-01') }];
  applyRecommended(rows, 'month', [], new Map([['2027-01', -5]]));
  assert.equal(rows[0].recommended, -5);
});

test('applyRecommended: a partly covered quarter uses budget + forecast for the rest', () => {
  const rows = [
    { period: '2027 Q1', date: new Date(2027, 0, 1) },
    { period: '2027 Q2', date: new Date(2027, 3, 1) },
  ];
  const raw = ['2027-01', '2027-02', '2027-03'].map((m) => ({
    period: m + '-01',
    budget: -10,
    forecast: -5,
  }));
  // series starts in February
  applyRecommended(
    rows,
    'quarter',
    raw,
    new Map([
      ['2027-02', -20],
      ['2027-03', -20],
    ]),
  );
  assert.equal(rows[0].recommended, -15 + -20 + -20);
  assert.equal(rows[1].recommended, undefined);
});
