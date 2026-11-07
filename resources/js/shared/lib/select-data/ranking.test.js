// Run with: node --test resources/js/shared/lib/select-data/ranking.test.js
import { test } from 'node:test';
import assert from 'node:assert/strict';
import {
  aggregateUsage,
  applySavedTransaction,
  categoryFullName,
  investmentUsage,
  rankByUsage,
  searchEntities,
  standardUsage,
  suggestCategories,
} from './ranking.js';

const entity = (id, name, extra = {}) => ({ id, name, active: true, ...extra });
const usage = () => ({
  standard: [
    {
      type: 'withdrawal',
      account_from_id: 1,
      account_to_id: 10,
      uses: 5,
      last_used: '2026-01-01',
    },
    {
      type: 'withdrawal',
      account_from_id: 1,
      account_to_id: 11,
      uses: 3,
      last_used: '2026-03-01',
    },
    {
      type: 'withdrawal',
      account_from_id: 2,
      account_to_id: 12,
      uses: 9,
      last_used: '2026-02-01',
    },
    {
      type: 'deposit',
      account_from_id: 13,
      account_to_id: 1,
      uses: 7,
      last_used: '2026-02-01',
    },
  ],
  investment: [],
  payee_categories: [],
  deferred_categories: [],
});

test('payee usage is limited to the type, and to the chosen account when it has history', () => {
  const anchored = standardUsage(usage(), {
    transactionType: 'withdrawal',
    side: 'to',
    anchorId: 1,
  });
  assert.deepEqual([...anchored.keys()].sort(), [10, 11]);

  const global = standardUsage(usage(), {
    transactionType: 'withdrawal',
    side: 'to',
  });
  assert.deepEqual([...global.keys()].sort(), [10, 11, 12]);
});

test('an account without shared history falls back to global usage', () => {
  const map = standardUsage(usage(), {
    transactionType: 'withdrawal',
    side: 'to',
    anchorId: 99,
  });
  assert.equal(map.size, 3);
});

test('most used and recent never repeat an item, and fill tops up alphabetically', () => {
  const candidates = [1, 2, 3, 4, 5, 6, 7, 8].map((i) => entity(i, `P${i}`));
  const rows = candidates.slice(0, 7).map((c, i) => ({
    id: c.id,
    uses: 100 - i * 10,
    last_used: `2026-01-0${i + 1}`,
  }));
  const map = aggregateUsage(rows, (r) => r.id);
  // Re-key: aggregateUsage sums by id from a rows' `uses`/`last_used`
  const { mostUsed, recent, fill } = rankByUsage(candidates, map);

  assert.deepEqual(
    mostUsed.map((c) => c.id),
    [1, 2, 3, 4, 5],
  );
  assert.deepEqual(
    recent.map((c) => c.id),
    [7, 6],
  );
  assert.deepEqual(
    fill.map((c) => c.id),
    [8],
  );

  const all = [...mostUsed, ...recent, ...fill].map((c) => c.id);
  assert.equal(new Set(all).size, all.length);
});

test('with no history everything comes from the alphabetical fill, capped at ten', () => {
  const candidates = Array.from({ length: 14 }, (_, i) =>
    entity(i + 1, `N${String(i).padStart(2, '0')}`),
  );
  const { mostUsed, recent, fill } = rankByUsage(candidates, new Map());

  assert.equal(mostUsed.length + recent.length, 0);
  assert.equal(fill.length, 10);
});

test('search matches names and aliases ignoring case and accents, names first, inactive last', () => {
  const entities = [
    entity(1, 'Tesco', { alias: 'groceries' }),
    entity(2, 'Árpád Shop', { active: false }),
    entity(3, 'Shop Arpad', {}),
    entity(4, 'My Tesco Express', {}),
  ];

  assert.deepEqual(
    searchEntities(entities, 'ARPAD').map((e) => e.id),
    [3, 2],
  );
  assert.deepEqual(
    searchEntities(entities, 'tesco').map((e) => e.id),
    [1, 4],
  );

  const [alias] = searchEntities(entities, 'grocer');
  assert.equal(alias.id, 1);
  assert.equal(alias.matchedVia, 'alias');
  assert.deepEqual(searchEntities(entities, '  '), []);
});

test('investment usage can be limited to one account', () => {
  const u = usage();
  u.investment = [
    {
      type: 'buy',
      account_id: 1,
      investment_id: 7,
      uses: 2,
      last_used: '2026-01-01',
    },
    {
      type: 'buy',
      account_id: 2,
      investment_id: 8,
      uses: 4,
      last_used: '2026-01-02',
    },
  ];
  assert.deepEqual([...investmentUsage(u, { accountId: 1 }).keys()], [7]);
  assert.equal(investmentUsage(u).size, 2);
});

const categories = [
  { id: 1, name: 'Food', parent_id: null, active: true },
  { id: 2, name: 'Groceries', parent_id: 1, active: true },
  { id: 3, name: 'Fuel', parent_id: null, active: true },
  { id: 4, name: 'Old', parent_id: null, active: false },
  { id: 5, name: 'Tools', parent_id: null, active: true },
];

test('category full name is resolved from the parent', () => {
  const byId = new Map(categories.map((c) => [c.id, c]));
  assert.equal(categoryFullName(categories[1], byId), 'Food > Groceries');
  assert.equal(categoryFullName(categories[0], byId), 'Food');
});

test('payee categories: default first, then most used, minus deferred and inactive', () => {
  const u = usage();
  u.payee_categories = [
    { payee_id: 10, category_id: 2, uses: 9 },
    { payee_id: 10, category_id: 3, uses: 5 },
    { payee_id: 10, category_id: 4, uses: 4 },
    { payee_id: 10, category_id: 5, uses: 1 },
    { payee_id: 11, category_id: 1, uses: 50 },
  ];
  u.deferred_categories = [{ payee_id: 10, category_id: 3 }];

  const ids = suggestCategories(categories, u, {
    payee: { id: 10, category_id: 5 },
  }).map((c) => c.id);
  assert.deepEqual(ids, [5, 2]);
});

test('a payee without history falls back to the most used categories overall', () => {
  const u = usage();
  u.payee_categories = [
    { payee_id: 11, category_id: 1, uses: 50 },
    { payee_id: 12, category_id: 3, uses: 5 },
  ];
  const ids = suggestCategories(categories, u, {
    payee: { id: 99, category_id: null },
  }).map((c) => c.id);
  assert.deepEqual(ids, [1, 3]);
});

test('saving a transaction updates counts and last use, so the next entry ranks correctly', () => {
  const u = usage();
  applySavedTransaction(u, {
    config_type: 'standard',
    transaction_type: 'withdrawal',
    date: '2026-04-01T00:00:00.000000Z',
    config: { account_from_id: 1, account_to_id: 10 },
    transaction_items: [
      { category_id: 2 },
      { category_id: '2' },
      { category_id: null },
    ],
  });
  applySavedTransaction(u, {
    config_type: 'standard',
    transaction_type: 'withdrawal',
    date: '2026-04-02',
    config: { account_from_id: 1, account_to_id: 20 },
    transaction_items: [],
  });

  const known = u.standard.find((r) => r.account_to_id === 10);
  assert.equal(known.uses, 6);
  assert.equal(known.last_used, '2026-04-01');
  assert.equal(u.standard.find((r) => r.account_to_id === 20).uses, 1);
  assert.deepEqual(u.payee_categories, [
    { payee_id: 10, category_id: 2, uses: 2 },
  ]);
});

test('saving an investment transaction counts it, and a transfer touches no payee categories', () => {
  const u = usage();
  applySavedTransaction(u, {
    config_type: 'investment',
    transaction_type: 'buy',
    date: '2026-04-01',
    config: { account_id: 1, investment_id: 7 },
  });
  applySavedTransaction(u, {
    config_type: 'standard',
    transaction_type: 'transfer',
    date: '2026-04-01',
    config: { account_from_id: 1, account_to_id: 2 },
    transaction_items: [{ category_id: 2 }],
  });

  assert.equal(u.investment.length, 1);
  assert.deepEqual(u.payee_categories, []);
});
