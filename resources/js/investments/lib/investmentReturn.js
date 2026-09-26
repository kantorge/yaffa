import Decimal from 'decimal.js';

/**
 * Latest known price at or before `date` - "last known value carried forward", the convention
 * used for every as-of valuation here (never peeks at a later price). Falls back to a buy/sell
 * transaction's own recorded price when no investment-price-history entry qualifies. Returns
 * null when neither source has anything at or before `date` - the caller must treat that as
 * "unknown", never substitute a guessed value.
 */
export function priceAsOf(date, prices, transactions) {
  const time = date.getTime();

  const fromHistory = prices
    .filter((p) => new Date(p.date).getTime() <= time)
    .sort((a, b) => new Date(b.date) - new Date(a.date));
  if (fromHistory.length > 0) {
    return new Decimal(fromHistory[0].price || 0);
  }

  const fromTransactions = transactions
    .filter(
      (t) => t.config?.price != null && new Date(t.date).getTime() <= time,
    )
    .sort((a, b) => new Date(b.date) - new Date(a.date));
  if (fromTransactions.length > 0) {
    return new Decimal(fromTransactions[0].config.price);
  }

  return null;
}

/**
 * Net quantity held from every transaction strictly before `date` - the "opening" position for
 * a reporting period starting on `date`.
 */
export function quantityBefore(date, transactions, getTypeConfig) {
  return transactions
    .filter((t) => new Date(t.date) < date)
    .reduce((sum, t) => {
      const multiplier = getTypeConfig(t.transaction_type).quantity_multiplier;
      if (multiplier == null || t.config?.quantity == null) return sum;
      return sum.plus(new Decimal(multiplier).times(t.config.quantity));
    }, new Decimal(0));
}

/**
 * Total economic return of a single security position over [dateFrom, dateTo], Modified-Dietz
 * style, adapted so realized sales/dividends land in the numerator as return rather than as a
 * withdrawal of capital:
 *
 *   gain       = (closingValue - openingValue) + sum of each transaction's net cash effect,
 *                using the same sign convention as the backend's
 *                TransactionService::getInvestmentConfigCashFlow (buy: -netCost,
 *                sell/dividend/interest_yield: +netProceeds/+netIncome, each already net of
 *                that transaction's own commission/tax).
 *   avgCapital = openingValue + sum of (purchase net cost * time-remaining-in-period weight)
 *   roi        = gain / avgCapital
 *
 * Only purchases are time-weighted in the denominator - capital added late in the period gets
 * less credit for exposure to that period's return. Sales/dividends are not withdrawals to be
 * weighted away; they're realized return, so they enter the numerator in full, unweighted.
 *
 * add_shares/remove_shares have no price/quantity-driven cash amount (TransactionType::
 * amountMultiplier() is null for both, so that term is never added - see
 * TransactionService::getInvestmentConfigCashFlow), but a commission/tax recorded against one
 * still reduces gain, exactly as it does on the backend: a fee is a real cash cost even when
 * the quantity change itself has no price.
 *
 * openingValue/closingValue/gain/avgCapital/roi come back `null` when the price at a period
 * boundary can't be resolved from price history or transaction data - an unresolvable
 * valuation must surface as "unknown" to the caller, never silently default to 0 or 1.
 */
export function computeInvestmentReturn({
  transactions,
  prices,
  dateFrom,
  dateTo,
  getTypeConfig,
}) {
  const periodMs = dateTo - dateFrom;
  const inWindow = transactions.filter((t) => {
    const d = new Date(t.date);
    return d >= dateFrom && d <= dateTo;
  });

  const openingQuantity = quantityBefore(dateFrom, transactions, getTypeConfig);
  const windowQuantityChange = inWindow.reduce((sum, t) => {
    const multiplier = getTypeConfig(t.transaction_type).quantity_multiplier;
    if (multiplier == null || t.config?.quantity == null) return sum;
    return sum.plus(new Decimal(multiplier).times(t.config.quantity));
  }, new Decimal(0));
  const closingQuantity = openingQuantity.plus(windowQuantityChange);

  const openingPrice = openingQuantity.isZero()
    ? new Decimal(0)
    : priceAsOf(dateFrom, prices, transactions);
  const closingPrice = closingQuantity.isZero()
    ? new Decimal(0)
    : priceAsOf(dateTo, prices, transactions);

  const openingValue =
    openingPrice == null ? null : openingQuantity.times(openingPrice);
  const closingValue =
    closingPrice == null ? null : closingQuantity.times(closingPrice);

  let buying = new Decimal(0); // gross (price x quantity), informational only
  let selling = new Decimal(0); // gross (price x quantity), informational only
  let added = new Decimal(0);
  let removed = new Decimal(0);
  let dividend = new Decimal(0); // raw dividend/interest_yield field, informational only
  let commission = new Decimal(0);
  let taxes = new Decimal(0);
  let cashFlowGain = new Decimal(0);
  let weightedPurchaseCapital = new Decimal(0);

  for (const t of inWindow) {
    const cfg = t.config || {};
    const comm =
      cfg.commission != null ? new Decimal(cfg.commission) : new Decimal(0);
    const tax = cfg.tax != null ? new Decimal(cfg.tax) : new Decimal(0);
    commission = commission.plus(comm);
    taxes = taxes.plus(tax);

    if (
      t.transaction_type === 'buy' &&
      cfg.price != null &&
      cfg.quantity != null
    ) {
      const gross = new Decimal(cfg.price).times(cfg.quantity);
      buying = buying.plus(gross);
      const netCost = gross.plus(comm).plus(tax);
      cashFlowGain = cashFlowGain.minus(netCost);
      const weight =
        periodMs > 0
          ? Math.min(1, Math.max(0, (dateTo - new Date(t.date)) / periodMs))
          : 1;
      weightedPurchaseCapital = weightedPurchaseCapital.plus(
        netCost.times(weight),
      );
    } else if (
      t.transaction_type === 'sell' &&
      cfg.price != null &&
      cfg.quantity != null
    ) {
      const gross = new Decimal(cfg.price).times(cfg.quantity);
      selling = selling.plus(gross);
      cashFlowGain = cashFlowGain.plus(gross.minus(comm).minus(tax));
    } else if (
      t.transaction_type === 'dividend' ||
      t.transaction_type === 'interest_yield'
    ) {
      const div =
        cfg.dividend != null ? new Decimal(cfg.dividend) : new Decimal(0);
      dividend = dividend.plus(div);
      cashFlowGain = cashFlowGain.plus(div.minus(comm).minus(tax));
    } else if (t.transaction_type === 'add_shares') {
      added = added.plus(cfg.quantity != null ? new Decimal(cfg.quantity) : 0);
      cashFlowGain = cashFlowGain.minus(comm).minus(tax);
    } else if (t.transaction_type === 'remove_shares') {
      removed = removed.plus(
        cfg.quantity != null ? new Decimal(cfg.quantity) : 0,
      );
      cashFlowGain = cashFlowGain.minus(comm).minus(tax);
    }
  }

  const gain =
    openingValue == null || closingValue == null
      ? null
      : closingValue.minus(openingValue).plus(cashFlowGain);

  const avgCapital =
    openingValue == null ? null : openingValue.plus(weightedPurchaseCapital);

  let roi = null;
  if (gain != null && avgCapital != null) {
    roi = avgCapital.isZero() ? 0 : gain.dividedBy(avgCapital).toNumber();
  }

  return {
    openingQuantity,
    closingQuantity,
    openingValue,
    closingValue,
    buying,
    selling,
    added,
    removed,
    dividend,
    commission,
    taxes,
    gain,
    avgCapital,
    roi,
  };
}
