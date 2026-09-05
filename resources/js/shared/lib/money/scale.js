/**
 * Storage scale (max decimal places) for money/quantity fields, mirroring each
 * model's MoneyCast/DecimalCast declaration in app/Models/*.php. Nothing exposes
 * a cast's scale to the frontend today, so this is kept in sync by hand - update
 * both sides together if a column's scale ever changes.
 *
 * - AMOUNT (4): TransactionItem::amount, TransactionDetailStandard::amount_from/
 *   amount_to, TransactionDetailInvestment::commission/tax/dividend.
 * - PRICE (10): TransactionDetailInvestment::price, InvestmentPrice::price.
 */
export const STORAGE_SCALE = {
  AMOUNT: 4,
  PRICE: 10,
};
