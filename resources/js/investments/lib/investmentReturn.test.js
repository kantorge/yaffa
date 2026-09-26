// Run with: node --test resources/js/investments/lib
// No test framework/dependency needed - node:test/node:assert ship with Node itself, and this
// folder's package.json ({"type":"module"}) is what lets plain `node --test` load these ESM
// import/export files directly (the project root has no "type":"module", so this is scoped
// on purpose - see the comment in that package.json's sibling investmentReturn.js).
import { test } from 'node:test';
import assert from 'node:assert/strict';
import {
    computeInvestmentReturn,
    priceAsOf,
    quantityBefore,
} from './investmentReturn.js';

// Mirrors App\Enums\TransactionType::quantityMultiplier() - the only field
// computeInvestmentReturn reads from it.
function getTypeConfig(type) {
    const quantity_multiplier = {
        buy: 1,
        sell: -1,
        add_shares: 1,
        remove_shares: -1,
        dividend: null,
        interest_yield: null,
    }[type];
    return { quantity_multiplier: quantity_multiplier ?? null };
}

let nextId = 1;
function tx(type, date, config = {}) {
    return { id: nextId++, transaction_type: type, date, config };
}

const day = (n) => new Date(`2026-01-${String(n).padStart(2, '0')}T00:00:00Z`);

function run(opts) {
    return computeInvestmentReturn({ getTypeConfig, prices: [], ...opts });
}

test('1. holding for the entire period with no transactions', () => {
    const dateFrom = day(1);
    const dateTo = day(31);
    const transactions = [
        tx('buy', new Date('2025-01-01'), { price: 10, quantity: 100 }),
    ];
    const prices = [
        { date: dateFrom, price: 10 },
        { date: dateTo, price: 12 },
    ];
    const r = run({ transactions, prices, dateFrom, dateTo });
    assert.equal(r.openingQuantity.toNumber(), 100);
    assert.equal(r.closingQuantity.toNumber(), 100);
    assert.equal(r.openingValue.toNumber(), 1000);
    assert.equal(r.closingValue.toNumber(), 1200);
    assert.equal(r.gain.toNumber(), 200);
    assert.equal(r.roi, 0.2);
});

test('2. purchase during the period (at the very start = full weight)', () => {
    const dateFrom = day(1);
    const dateTo = day(31);
    const transactions = [tx('buy', dateFrom, { price: 10, quantity: 100 })];
    const prices = [{ date: dateTo, price: 12 }];
    const r = run({ transactions, prices, dateFrom, dateTo });
    assert.equal(r.closingQuantity.toNumber(), 100);
    assert.equal(r.closingValue.toNumber(), 1200);
    assert.equal(r.avgCapital.toNumber(), 1000);
    assert.equal(r.roi, 0.2);
});

test('3. purchase near the beginning vs near the end of the period', () => {
    const dateFrom = day(1);
    const dateTo = day(11); // 10-day period, easy weight fractions
    const prices = [{ date: dateTo, price: 10 }]; // flat price, isolate the weighting effect

    const early = run({
        transactions: [tx('buy', day(1), { price: 10, quantity: 100 })],
        prices,
        dateFrom,
        dateTo,
    });
    const late = run({
        transactions: [tx('buy', day(9), { price: 10, quantity: 100 })],
        prices,
        dateFrom,
        dateTo,
    });

    assert.equal(early.avgCapital.toNumber(), 1000); // weight 1.0
    assert.ok(Math.abs(late.avgCapital.toNumber() - 200) < 1e-9); // weight 0.2 -> 1000*0.2
    assert.ok(late.avgCapital.toNumber() < early.avgCapital.toNumber());
});

test('4 & 5. sale during the period, with commission/tax (worked example)', () => {
    const dateFrom = day(1);
    const dateTo = day(31);
    const transactions = [
        tx('buy', new Date('2025-01-01'), { price: 10, quantity: 1000 }),
        tx('sell', day(15), {
            price: 14,
            quantity: 400,
            commission: 60,
            tax: 40,
        }),
    ];
    const prices = [
        { date: dateFrom, price: 10 },
        { date: dateTo, price: 14 },
    ];
    const r = run({ transactions, prices, dateFrom, dateTo });
    assert.equal(r.openingValue.toNumber(), 10000);
    assert.equal(r.closingQuantity.toNumber(), 600);
    assert.equal(r.closingValue.toNumber(), 8400);
    assert.equal(r.gain.toNumber(), 3900);
    assert.equal(r.avgCapital.toNumber(), 10000);
    assert.ok(Math.abs(r.roi - 0.39) < 1e-9);
});

test('6. purchase followed by a sale', () => {
    const dateFrom = day(1);
    const dateTo = new Date(dateFrom.getTime() + 100 * 86400000); // 100-day period
    const buyDate = new Date(dateFrom.getTime() + 10 * 86400000); // weight 0.9
    const sellDate = new Date(dateFrom.getTime() + 50 * 86400000);
    const transactions = [
        tx('buy', buyDate, { price: 10, quantity: 100 }),
        tx('sell', sellDate, { price: 12, quantity: 50 }),
    ];
    const prices = [{ date: dateTo, price: 13 }];
    const r = run({ transactions, prices, dateFrom, dateTo });
    assert.equal(r.closingQuantity.toNumber(), 50);
    assert.equal(r.closingValue.toNumber(), 650);
    // cashFlowGain = -1000 (buy) + 600 (sell) = -400; gain = (650-0) - 400 = 250
    assert.equal(r.gain.toNumber(), 250);
    assert.ok(Math.abs(r.avgCapital.toNumber() - 900) < 1e-9); // 1000 * weight 0.9
});

test('7. multiple purchases', () => {
    const dateFrom = day(1);
    const dateTo = day(11);
    const transactions = [
        tx('buy', day(1), { price: 10, quantity: 100 }), // weight 1
        tx('buy', day(6), { price: 10, quantity: 100 }), // weight 0.5
    ];
    const prices = [{ date: dateTo, price: 10 }];
    const r = run({ transactions, prices, dateFrom, dateTo });
    assert.ok(Math.abs(r.avgCapital.toNumber() - 1500) < 1e-9); // 1000*1 + 1000*0.5
});

test('8. multiple sales are not time-weighted (moving the date changes nothing)', () => {
    const dateFrom = day(1);
    const dateTo = day(31);
    const base = { price: 10, quantity: 1000 };
    const prices = [{ date: dateTo, price: 10 }];

    const early = run({
        transactions: [
            tx('buy', new Date('2025-01-01'), base),
            tx('sell', day(2), { price: 12, quantity: 100 }),
            tx('sell', day(3), { price: 12, quantity: 100 }),
        ],
        prices,
        dateFrom,
        dateTo,
    });
    const late = run({
        transactions: [
            tx('buy', new Date('2025-01-01'), base),
            tx('sell', day(29), { price: 12, quantity: 100 }),
            tx('sell', day(30), { price: 12, quantity: 100 }),
        ],
        prices,
        dateFrom,
        dateTo,
    });

    assert.equal(early.gain.toNumber(), late.gain.toNumber());
    assert.equal(early.roi, late.roi);
});

test('9. dividend during the period is not time-weighted either', () => {
    const dateFrom = day(1);
    const dateTo = day(31);
    const transactions = [
        tx('buy', new Date('2025-01-01'), { price: 10, quantity: 100 }),
    ];
    const prices = [
        { date: dateFrom, price: 10 },
        { date: dateTo, price: 10 },
    ];
    const early = run({
        transactions: [
            ...transactions,
            tx('dividend', day(2), { dividend: 50 }),
        ],
        prices,
        dateFrom,
        dateTo,
    });
    const late = run({
        transactions: [
            ...transactions,
            tx('dividend', day(30), { dividend: 50 }),
        ],
        prices,
        dateFrom,
        dateTo,
    });
    assert.equal(early.gain.toNumber(), 50);
    assert.equal(late.gain.toNumber(), 50);
    assert.ok(Math.abs(early.roi - 0.05) < 1e-9);
});

test('10. purchase + sale + dividend in the same period (additivity)', () => {
    const dateFrom = day(1);
    const dateTo = day(31);
    const transactions = [
        tx('buy', new Date('2025-01-01'), { price: 10, quantity: 100 }), // BMV 1000
        tx('buy', day(1), { price: 10, quantity: 100 }), // weight 1, net cost 1000
        tx('sell', day(15), { price: 12, quantity: 50 }), // net proceeds 600
        tx('dividend', day(20), { dividend: 20 }),
    ];
    const prices = [
        { date: dateFrom, price: 10 },
        { date: dateTo, price: 11 },
    ];
    const r = run({ transactions, prices, dateFrom, dateTo });
    assert.equal(r.closingQuantity.toNumber(), 150);
    assert.equal(r.closingValue.toNumber(), 1650);
    // cashFlowGain = -1000 + 600 + 20 = -380; gain = (1650-1000) - 380 = 270
    assert.equal(r.gain.toNumber(), 270);
    assert.equal(r.avgCapital.toNumber(), 2000); // 1000 + 1000*weight(1)
});

test('11. period beginning with an existing position excludes prior transactions from the window', () => {
    const dateFrom = day(10);
    const dateTo = day(20);
    const transactions = [
        tx('buy', day(1), { price: 10, quantity: 100 }),
        tx('buy', day(15), { price: 11, quantity: 10 }),
    ];
    const prices = [
        { date: dateFrom, price: 10.5 },
        { date: dateTo, price: 12 },
    ];
    const r = run({ transactions, prices, dateFrom, dateTo });
    assert.equal(r.openingQuantity.toNumber(), 100);
    assert.equal(r.openingValue.toNumber(), 1050);
    assert.equal(r.closingQuantity.toNumber(), 110);
});

test('12. period ending with no remaining position after a complete sale', () => {
    const dateFrom = day(1);
    const dateTo = day(31);
    const transactions = [
        tx('buy', new Date('2025-01-01'), { price: 10, quantity: 100 }),
        tx('sell', day(15), { price: 12, quantity: 100 }),
    ];
    const prices = [{ date: dateFrom, price: 10 }]; // no price at/after dateTo on purpose
    const r = run({ transactions, prices, dateFrom, dateTo });
    assert.equal(r.closingQuantity.toNumber(), 0);
    assert.equal(r.closingValue.toNumber(), 0); // resolved trivially, no price lookup needed
    assert.equal(r.gain.toNumber(), 200); // (0-1000) + 1200
    assert.ok(Math.abs(r.roi - 0.2) < 1e-9);
});

test('13. transactions but no price appreciation (bonus shares via add_shares)', () => {
    const dateFrom = day(1);
    const dateTo = day(31);
    const transactions = [
        tx('buy', new Date('2025-01-01'), { price: 10, quantity: 100 }),
        tx('add_shares', day(15), { quantity: 10 }),
    ];
    const prices = [
        { date: dateFrom, price: 10 },
        { date: dateTo, price: 10 },
    ];
    const r = run({ transactions, prices, dateFrom, dateTo });
    assert.equal(r.closingQuantity.toNumber(), 110);
    assert.equal(r.closingValue.toNumber(), 1100);
    assert.equal(r.gain.toNumber(), 100); // free shares still count as real economic gain
    assert.ok(Math.abs(r.roi - 0.1) < 1e-9);
});

test('add_shares/remove_shares have no price but a recorded fee still reduces gain', () => {
    const dateFrom = day(1);
    const dateTo = day(31);
    const transactions = [
        tx('buy', new Date('2025-01-01'), { price: 10, quantity: 100 }),
        tx('add_shares', day(15), { quantity: 10, commission: 2, tax: 1 }),
    ];
    const prices = [
        { date: dateFrom, price: 10 },
        { date: dateTo, price: 10 },
    ];
    const r = run({ transactions, prices, dateFrom, dateTo });
    assert.equal(r.closingQuantity.toNumber(), 110);
    assert.equal(r.closingValue.toNumber(), 1100);
    // valueDelta = 100 (free shares), minus the 3 fee = 97
    assert.equal(r.gain.toNumber(), 97);
});

test('14. transaction costs but no market-price change', () => {
    const dateFrom = day(1);
    const dateTo = day(11);
    const transactions = [
        tx('buy', new Date('2025-01-01'), { price: 10, quantity: 100 }),
        tx('buy', day(6), { price: 10, quantity: 10, commission: 5 }), // weight 0.5
    ];
    const prices = [
        { date: dateFrom, price: 10 },
        { date: dateTo, price: 10 },
    ];
    const r = run({ transactions, prices, dateFrom, dateTo });
    assert.equal(r.closingValue.toNumber(), 1100);
    // cashFlowGain = -(100 + 5) = -105; gain = (1100-1000) - 105 = -5
    assert.equal(r.gain.toNumber(), -5);
    assert.ok(r.roi < 0); // a loss purely from commission drag, despite a flat price
});

test('15. arbitrary sub-period (Q1) vs full year weight capital independently', () => {
    const yearStart = new Date('2026-01-01T00:00:00Z');
    const yearEnd = new Date('2026-12-31T00:00:00Z');
    const q1End = new Date('2026-03-31T00:00:00Z');
    const q1Buy = new Date('2026-02-01T00:00:00Z');
    const q3Buy = new Date('2026-08-01T00:00:00Z');
    const transactions = [
        tx('buy', q1Buy, { price: 10, quantity: 1000 }),
        tx('buy', q3Buy, { price: 12, quantity: 500 }),
    ];
    const pricesQ1 = [{ date: q1End, price: 11 }];
    const pricesYear = [{ date: yearEnd, price: 13 }];

    const q1 = run({
        transactions,
        prices: pricesQ1,
        dateFrom: yearStart,
        dateTo: q1End,
    });
    assert.equal(q1.closingQuantity.toNumber(), 1000); // Q3 buy is outside this window
    assert.ok(q1.avgCapital.toNumber() > 0 && q1.roi !== null);

    const year = run({
        transactions,
        prices: pricesYear,
        dateFrom: yearStart,
        dateTo: yearEnd,
    });
    assert.equal(year.closingQuantity.toNumber(), 1500);
    const w1 = (yearEnd - q1Buy) / (yearEnd - yearStart);
    const w2 = (yearEnd - q3Buy) / (yearEnd - yearStart);
    const expectedAvgCapital = 10000 * w1 + 6000 * w2;
    assert.ok(Math.abs(year.avgCapital.toNumber() - expectedAvgCapital) < 1e-6);
});

test('priceAsOf: unresolvable when nothing is known at or before the date', () => {
    assert.equal(priceAsOf(day(1), [], []), null);
});

test('priceAsOf: falls back to a transaction price when no price-history entry qualifies', () => {
    const p = priceAsOf(
        day(10),
        [],
        [tx('buy', day(5), { price: 9.5, quantity: 1 })],
    );
    assert.equal(p.toNumber(), 9.5);
});

test('quantityBefore: excludes transactions on or after the boundary date', () => {
    const q = quantityBefore(
        day(10),
        [
            tx('buy', day(5), { price: 1, quantity: 3 }),
            tx('buy', day(10), { price: 1, quantity: 100 }),
        ],
        getTypeConfig,
    );
    assert.equal(q.toNumber(), 3);
});
