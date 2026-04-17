# Transaction Types

## Feature Summary

Transaction types define what kind of financial event the user is recording. They are the first and most important classification layer because they determine which properties matter, how the transaction affects balances or holdings, and which form fields YAFFA exposes.

In practical use, these types are split into two types: standard transactions and investment transactions.

## User Problem

- Users need a quick way to tell YAFFA what kind of money event they are entering.
- Different financial actions require different data and should not be treated as if they were the same.

## User Value / Benefit

- Reduces confusion during data entry.
- Makes transaction forms adapt to the user’s real intent.
- Ensures reports and balance calculations behave consistently.

## Technical Description

Each transaction subtype belongs to one of two categories:

- Standard
  - withdrawal
  - deposit
  - transfer

- Investment
  - buy
  - sell
  - add shares
  - remove shares
  - dividend
  - interest yield

The type also determines whether the transaction affects money amount, investment quantity, or both.

## Type Matrix

| Family     | Type           | Meaning for the user                                               | Cashflow effect                  | Holding quantity effect   | Typical required fields              | Everyday examples                                |
| ---------- | -------------- | ------------------------------------------------------------------ | -------------------------------- | ------------------------- | ------------------------------------ | ------------------------------------------------ |
| Standard   | Withdrawal     | Money leaves an account and goes to a payee or expense destination | Decreases cash                   | None                      | account from, payee, amount          | Paying for groceries, utility bills, eating out  |
| Standard   | Deposit        | Money enters an account from a payee or income source              | Increases cash                   | None                      | payee, account to, amount            | Salary, freelance income, gifts, simple interest |
| Standard   | Transfer       | Money moves between two user accounts                              | Neutral at whole-portfolio level | None                      | account from, account to, amounts    | Moving money between checking and savings        |
| Investment | Buy            | Purchase of an investment using cash                               | Decreases cash                   | Increases shares or units | account, investment, price, quantity | Buying stocks, mutual funds                      |
| Investment | Sell           | Sale of an investment                                              | Increases cash                   | Decreases shares or units | account, investment, price, quantity | Selling stocks, mutual funds                     |
| Investment | Add shares     | Manual increase of holdings without a normal buy flow              | Usually no direct cashflow       | Increases shares or units | account, investment, quantity        | Receiving bonus shares, stock splits             |
| Investment | Remove shares  | Manual decrease of holdings without a normal sell flow             | Usually no direct cashflow       | Decreases shares or units | account, investment, quantity        | Selling bonus shares, correcting holdings        |
| Investment | Dividend       | Cash income received from an investment                            | Increases cash                   | No quantity change        | account, investment, income amount   | Receiving dividends from stocks                  |
| Investment | Interest yield | Yield or interest income related to an investment account          | Increases cash                   | No quantity change        | account, investment, income amount   | Interest from bonds or savings accounts          |

## Core Logic / Rules

- Standard and investment types should be documented separately after this classification step.
- Transfer is still a standard transaction even though it is not income or expense.
- Buy and sell affect both cash and holding quantity.
- Add shares and remove shares affect quantity without representing a normal trade.
- Dividend and interest yield represent investment income rather than quantity movement.

## Field Requirement Rules

- Price is required for buy and sell.
- Quantity is required for buy, sell, add shares, and remove shares.
- Income amount is required for dividend and interest yield.
- Standard types use account and payee direction rather than price and quantity.

## Outputs

- Correct form shape for the chosen transaction
- Correct reporting semantics
- Correct balance and holding calculations

## Edge Cases / Constraints

- Transfer should not be interpreted as spending or income at the portfolio level.
- Some investment types describe non-trade adjustments rather than market operations.
- The same top-level transaction concept behaves differently depending on subtype, so documentation should not flatten these distinctions.

## Confidence Level

High

## Assumptions

- The type labels are user-facing representations of the enum values defined in the application.
- Detailed form behavior is described in the standard and investment transaction documents rather than repeated here.
