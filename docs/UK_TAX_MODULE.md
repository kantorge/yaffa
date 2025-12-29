# UK Tax Report Module

## Overview
The UK Tax Report module provides comprehensive tax reporting for UK tax years (6 April to 5 April), tracking dividend income and capital gains across all investment accounts.

## Features

### 1. UK Tax Year Support
- Automatically calculates UK tax years (6 April to 5 April)
- Dropdown selector to view any historical tax year
- Date range display for clarity

### 2. Dividend Income Tracking
- Sums all dividend transactions by account and investment
- Groups by investment type/group
- Automatically identifies tax-exempt accounts (ISA, SIPP)
- Shows both total dividends and taxable amounts
- Transaction count for audit trail

### 3. Capital Gains/Losses Calculation
- Tracks all sell transactions in the tax year
- Calculates average buy price using weighted average method
- Calculates average sell price
- Computes gain/loss per investment (including losses as negative values)
- **Capital losses are included in the gain/loss column** for complete tax reporting
- Separates tax-exempt gains from taxable gains
- Includes commission costs in calculations
- **All amounts converted to GBP at transaction date rates**

### 4. Tax-Exempt Account Support
- New `tax_exempt` checkbox on Account and Payee forms
- Mark ISAs, SIPPs, pensions as tax-exempt
- Dividends and gains from these accounts show as £0 taxable
- Clear badge indicators in reports

### 5. Export Functionality
- CSV export of full tax report
- Includes all dividend details
- Includes all capital gains calculations
- Summary totals section

## Usage

### Accessing the Report
Navigate to: **Reports > UK Tax Report**
URL: `http://jaffa.test/reports/tax`

### Marking Accounts as Tax-Exempt
1. Edit an Account or Payee
2. Check the "Tax Exempt" checkbox
3. Save
4. All transactions for this account will be treated as tax-exempt

### Viewing Different Tax Years
1. Use the tax year dropdown in the report header
2. Select desired tax year
3. Report automatically refreshes

### Exporting Data
1. Click "Export CSV" button
2. CSV file downloads with all tax data
3. Use for record-keeping or accountant submission

## Technical Details

### Database Changes
- Added `tax_exempt` boolean field to `account_entities` table
- Default value: `false`
- Migration: `2025_12_01_160352_add_tax_exempt_to_account_entities_table`

### New Files
- **Trait**: `app/Http/Traits/UkTaxYearTrait.php` - UK tax year calculations
- **Service**: `app/Services/TaxReportService.php` - Tax calculations
- **Controller**: `app/Http/Controllers/TaxReportController.php` - Report handling
- **View**: `resources/views/reports/tax.blade.php` - UI

### Transaction Types Used
- **Dividend (Type 8)**: For dividend income tracking
- **Sell (Type 5)**: For capital gains calculations
- **Remove shares (Type 7)**: For capital gains calculations
- **Buy (Type 4)**: For cost basis calculations
- **Add shares (Type 6)**: For cost basis calculations
- **Interest ReInvest (Type 13)**: For cost basis calculations

### Calculation Methods

#### Currency Conversion
**All amounts are converted to base currency (GBP) at transaction date rates:**
- Dividend amounts converted using rate on dividend payment date
- Buy costs converted using rate on purchase date
- Sell proceeds converted using rate on sale date
- Uses `CurrencyTrait` methods for accurate historical rate lookup

#### Average Buy Price (in GBP)
Uses weighted average method with currency conversion:
```
For each buy transaction:
  - Convert cost to GBP at transaction date rate
  - Cost in GBP = (shares * price + commission) converted to GBP
  
Total Cost in GBP = SUM(all buy costs in GBP)
Total Shares = SUM(shares)
Average Buy Price in GBP = Total Cost in GBP / Total Shares
```

#### Capital Gain/Loss (in GBP)
```
Cost Basis in GBP = Average Buy Price in GBP * Shares Sold
Net Proceeds in GBP = Gross Proceeds converted to GBP - Commission in GBP
Gain/Loss = Net Proceeds in GBP - Cost Basis in GBP

Note: Both gains AND losses appear in the gain_loss column
      Losses are negative values
      Only positive gains from taxable accounts are subject to CGT
```

## Important Notes

1. **Tax Advice Disclaimer**: This report is for informational purposes only. Always consult with a qualified tax advisor or accountant for tax filing.

2. **Tax Allowances**: The report shows gross figures. Users must apply current tax allowances:
   - Dividend Allowance (check HMRC for current year)
   - Capital Gains Tax Allowance (check HMRC for current year)

3. **Historical Data**: Buy transactions from before the tax year are included in average cost calculations to ensure accurate cost basis.

4. **Tax-Exempt Accounts**: Common UK tax-exempt accounts include:
   - ISAs (Individual Savings Accounts)
   - SIPPs (Self-Invested Personal Pensions)
   - Company pensions
   - Junior ISAs

5. **Currency Conversion**: 
   - **All amounts are automatically converted to GBP** using exchange rates on transaction dates
   - This applies to dividends, buy costs, and sell proceeds
   - Historical currency rates are fetched from your currency rate database
   - Ensures accurate tax reporting regardless of original investment currency

6. **Capital Losses**:
   - Capital losses are shown as negative values in the gain/loss column
   - Losses can be used to offset gains for tax purposes
   - Both gains and losses are reported together for complete tax picture
   - Only positive gains from taxable accounts incur CGT

## Future Enhancements

Potential improvements:
- Section 104 pooling for capital gains (more complex UK CGT rules)
- Bed and breakfasting rules (30-day rule)
- Same-day and 30-day matching rules
- Foreign dividend withholding tax tracking
- Tax relief calculations
- Integration with HMRC Making Tax Digital
