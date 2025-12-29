# Unrealised Interest Feature

## Overview

The unrealised interest feature allows you to track and calculate interest that has accrued on your investments but has not yet been paid out. This is particularly useful for bonds, fixed-term savings accounts, and other interest-bearing investments.

## How It Works

### Daily Compound Interest Calculation

The system calculates interest using the daily compound interest formula:

```
Interest = Principal × [(1 + r/365)^days - 1]
```

Where:
- `Principal` = Amount invested (quantity × price)
- `r` = Annual interest rate (as a decimal, so 7% = 0.07)
- `days` = Number of days the money has been invested
- `365` = Number of days in a year

This formula provides accurate compound interest calculations that reflect how interest accrues daily.

## Setting Up

### 1. Configure Interest Rate on Investment

Before you can use unrealised interest calculations, you need to set the interest rate on your investment:

1. Go to **Investments** → Select your investment
2. Click **Edit**
3. Set the **Interest Rate** field (e.g., 7 for 7% annually)
4. Save

### 2. Record Transactions

Create buy/add share transactions for your investment. The system will track:
- Buy transactions (transaction type: 4)
- Add shares transactions (transaction type: 6)
- Received interest transactions (transaction type: 13)

## Viewing Unrealised Interest

### Individual Investment Interest

Visit `/investment/{id}/interest` to see detailed unrealised interest for a specific investment:

- **Interest Rate**: The annual percentage rate configured for the investment
- **Total Unrealised Interest**: Accrued but not yet received
- **Total Realised Interest**: Already received and recorded as transactions
- **Breakdown by Account**: Shows interest calculations for each account holding the investment

### Unrealised Interest Report

Visit `/reports/unrealised-interest` to see a comprehensive report of all investments with interest rates:

- Filter by tax year
- See unrealised and realised interest for all investments
- Track interest growth over time
- View totals across all holdings

## Key Concepts

### Unrealised Interest
Interest that has accrued on your investment based on the principal amount and time held, but has not yet been paid out. This is calculated daily and compounds.

### Realised Interest
Interest that has already been received and recorded as a transaction in the system (transaction type 13 - Interest ReInvest).

### Principal
The total value of your investment at a given point in time (calculated as quantity × current price).

### Interest Period
The system calculates interest from:
- The date of the buy/add transaction, OR
- The date of the last interest payment (whichever is later)

To the current date (or a date you specify).

## Examples

### Example 1: Simple Investment

- You invest £1,000 on January 1, 2025
- Interest rate: 7% annually
- Calculation as of March 31, 2025 (89 days):
  - Interest = 1000 × [(1 + 0.07/365)^89 - 1]
  - Interest = 1000 × [1.001917^89 - 1]
  - Interest ≈ £16.88

### Example 2: Multiple Investments Over Time

- January 1: Buy £1,000 at 7%
- February 1: Add another £500 (total principal now £1,500)
- March 1: Add another £500 (total principal now £2,000)
- Total interest calculated on each tranche from its respective start date

## Tax Considerations

For UK tax purposes, unrealised interest is typically not taxable until it's actually received (realised). This report helps you track:

- Current unrealised gains (which may become taxable when realised)
- Already-realised interest that may be subject to tax
- Use within the Personal Savings Allowance
- Bonds held in ISAs (which have 0% tax rate)

You can mark investments as tax-exempt (e.g., ISAs) by checking the "Tax Exempt" option when creating/editing the investment account.

## Technical Details

### Service: UnrealisedInterestService

The `App\Services\UnrealisedInterestService` class handles all interest calculations:

- `calculateInvestmentInterest()`: Calculate interest for a single investment
- `calculateUnrealisedInterest()`: Calculate accrued but unpaid interest
- `calculateRealisedInterest()`: Calculate received interest from transactions
- `getUnrealisedInterestReport()`: Generate comprehensive report for a period

### Routes

- `GET /investment/{investment}/interest` - View interest for specific investment
- `GET /reports/unrealised-interest` - View interest report for all investments

### Database

No new tables are required. The system uses existing:
- `investments` table (interest_rate field)
- `transactions` table
- `transaction_details_investment` table

## Notes

- Interest is calculated as of TODAY by default, but can be calculated to any date
- Multiple buy/add transactions are tracked separately to ensure accurate compound interest
- Interest payments (transaction type 13) are subtracted from unrealised interest
- The system uses daily compounding (365 days per year)
- Currency conversion is automatically applied when viewing in reports

## Limitations

- Currently uses simple daily compound interest (no support for monthly/quarterly compounding options yet)
- Price changes are not factored in (interest is calculated on original principal only)
- Interest is calculated from buy date; transfers between accounts reset the calculation
