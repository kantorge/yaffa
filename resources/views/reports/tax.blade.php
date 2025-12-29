@extends('template.layouts.page')

@section('title_postfix', __('UK Tax Report'))

@section('content_container_classes', 'container-fluid')

@section('content_header', __('UK Tax Report'))

@section('content')
<div class="row">
    <div class="col-lg-12">
        <!-- Tax Year Selector -->
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Tax Year: {{ $taxYear['label'] }} <small class="text-muted">(All amounts in GBP)</small></h5>
                <div class="d-flex gap-2">
                    <select class="form-select" id="taxYearSelect" onchange="window.location.href='?tax_year='+this.value">
                        @foreach($availableTaxYears as $year)
                            <option value="{{ $year['label'] }}" {{ $taxYear['label'] === $year['label'] ? 'selected' : '' }}>
                                {{ $year['label'] }} ({{ \Carbon\Carbon::parse($year['start'])->format('d M Y') }} - {{ \Carbon\Carbon::parse($year['end'])->format('d M Y') }})
                            </option>
                        @endforeach
                    </select>
                    <a href="{{ route('reports.tax.export', ['tax_year' => $taxYear['label']]) }}" class="btn btn-success">
                        <i class="fa fa-download"></i> Export CSV
                    </a>
                </div>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6>Dividend Income</h6>
                        <table class="table table-sm">
                            <tr>
                                <td>Total Dividends:</td>
                                <td class="text-end"><strong>£{{ number_format($summary['total_dividends'], 2) }}</strong></td>
                            </tr>
                            <tr>
                                <td>Tax Paid:</td>
                                <td class="text-end text-info"><strong>£{{ number_format($summary['total_tax_paid'], 2) }}</strong></td>
                            </tr>
                            <tr>
                                <td>Tax-Exempt (ISA/SIPP):</td>
                                <td class="text-end text-success">£{{ number_format($summary['tax_exempt_dividends'], 2) }}</td>
                            </tr>
                            <tr>
                                <td>Taxable:</td>
                                <td class="text-end text-warning">£{{ number_format($summary['taxable_dividends'], 2) }}</td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h6>Capital Gains/Losses</h6>
                        <table class="table table-sm">
                            <tr>
                                <td>Total Gains/Losses:</td>
                                <td class="text-end"><strong class="{{ $summary['total_gains'] >= 0 ? 'text-success' : 'text-danger' }}">
                                    £{{ number_format($summary['total_gains'], 2) }}
                                </strong></td>
                            </tr>
                            <tr>
                                <td>Tax-Exempt (ISA/SIPP):</td>
                                <td class="text-end text-muted">£{{ number_format($summary['tax_exempt_gains'], 2) }}</td>
                            </tr>
                            <tr>
                                <td>Taxable Gains Only:</td>
                                <td class="text-end text-warning">£{{ number_format($summary['taxable_gains'], 2) }}</td>
                            </tr>
                        </table>
                        <small class="text-muted">Note: Losses shown as negative values</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Dividend Income Detail -->
        <div class="card mb-3">
            <div class="card-header">
                <h5 class="mb-0">Dividend Income Detail</h5>
            </div>
            <div class="card-body">
                @if($dividends->isEmpty())
                    <p class="text-muted">No dividend transactions found for this tax year.</p>
                @else
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Account</th>
                                    <th>Investment</th>
                                    <th>Investment Group</th>
                                    <th class="text-center">Tax Status</th>
                                    <th class="text-end">Total Dividend</th>
                                    <th class="text-end">Tax Paid</th>
                                    <th class="text-end">Taxable Amount</th>
                                    <th class="text-center">Transactions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($dividends as $dividend)
                                <tr>
                                    <td>{{ $dividend->account_name }}</td>
                                    <td>{{ $dividend->investment_name }}</td>
                                    <td>{{ $dividend->investment_group_name }}</td>
                                    <td class="text-center">
                                        @if($dividend->tax_exempt)
                                            <span class="badge bg-success">Tax-Exempt</span>
                                        @else
                                            <span class="badge bg-warning">Taxable</span>
                                        @endif
                                    </td>
                                    <td class="text-end">£{{ number_format($dividend->total_dividend, 2) }}</td>
                                    <td class="text-end text-info">£{{ number_format($dividend->total_tax, 2) }}</td>
                                    <td class="text-end">£{{ number_format($dividend->taxable_amount, 2) }}</td>
                                    <td class="text-center">{{ $dividend->transaction_count }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr class="table-active fw-bold">
                                    <td colspan="4" class="text-end">Total:</td>
                                    <td class="text-end">£{{ number_format($dividends->sum('total_dividend'), 2) }}</td>
                                    <td class="text-end text-info">£{{ number_format($dividends->sum('total_tax'), 2) }}</td>
                                    <td class="text-end">£{{ number_format($dividends->sum('taxable_amount'), 2) }}</td>
                                    <td class="text-center">{{ $dividends->sum('transaction_count') }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                @endif
            </div>
        </div>

        <!-- Capital Gains Detail -->
        <div class="card mb-3">
            <div class="card-header">
                <h5 class="mb-0">Capital Gains/Losses Detail</h5>
            </div>
            <div class="card-body">
                @if($capitalGains->isEmpty())
                    <p class="text-muted">No sell transactions found for this tax year.</p>
                @else
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Account</th>
                                    <th>Investment</th>
                                    <th class="text-center">Tax Status</th>
                                    <th class="text-end">Shares Sold</th>
                                    <th class="text-end">Avg Buy Price</th>
                                    <th class="text-end">Avg Sell Price</th>
                                    <th class="text-end">Cost Basis</th>
                                    <th class="text-end">Net Proceeds</th>
                                    <th class="text-end">Gain/Loss</th>
                                    <th class="text-end">Taxable Gain</th>
                                    <th class="text-center">Transactions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($capitalGains as $gain)
                                <tr>
                                    <td>{{ $gain['account_name'] }}</td>
                                    <td>{{ $gain['investment_name'] }}</td>
                                    <td class="text-center">
                                        @if($gain['tax_exempt'])
                                            <span class="badge bg-success">Tax-Exempt</span>
                                        @else
                                            <span class="badge bg-warning">Taxable</span>
                                        @endif
                                    </td>
                                    <td class="text-end">{{ number_format($gain['shares_sold'], 4) }}</td>
                                    <td class="text-end">£{{ number_format($gain['avg_buy_price'], 4) }}</td>
                                    <td class="text-end">£{{ number_format($gain['avg_sell_price'], 4) }}</td>
                                    <td class="text-end">£{{ number_format($gain['cost_basis'], 2) }}</td>
                                    <td class="text-end">£{{ number_format($gain['net_proceeds'], 2) }}</td>
                                    <td class="text-end {{ $gain['gain_loss'] >= 0 ? 'text-success' : 'text-danger' }}">
                                        £{{ number_format($gain['gain_loss'], 2) }}
                                    </td>
                                    <td class="text-end">£{{ number_format($gain['taxable_gain'], 2) }}</td>
                                    <td class="text-center">{{ $gain['transaction_count'] }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr class="table-active fw-bold">
                                    <td colspan="6" class="text-end">Total:</td>
                                    <td class="text-end">£{{ number_format($capitalGains->sum('cost_basis'), 2) }}</td>
                                    <td class="text-end">£{{ number_format($capitalGains->sum('net_proceeds'), 2) }}</td>
                                    <td class="text-end {{ $capitalGains->sum('gain_loss') >= 0 ? 'text-success' : 'text-danger' }}">
                                        £{{ number_format($capitalGains->sum('gain_loss'), 2) }}
                                    </td>
                                    <td class="text-end">£{{ number_format($capitalGains->sum('taxable_gain'), 2) }}</td>
                                    <td class="text-center">{{ $capitalGains->sum('transaction_count') }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                @endif
            </div>
        </div>

        <!-- EIS/SEIS Investments Summary -->
        @if($eisSeisBuys->isNotEmpty())
        <div class="card mb-3">
            <div class="card-header">
                <h5 class="mb-0">EIS/SEIS Investments - Buy Events</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>Scheme</th>
                                <th>Investment Name</th>
                                <th>Account</th>
                                <th class="text-end">Total Quantity</th>
                                <th class="text-end">Total Cost (GBP)</th>
                                <th>Buy Dates</th>
                                <th class="text-center">Transactions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($eisSeisBuys as $buy)
                            <tr>
                                <td>
                                    <span class="badge bg-primary">{{ $buy->investment_group_name }}</span>
                                </td>
                                <td>{{ $buy->investment_name }}</td>
                                <td>{{ $buy->account_name }}</td>
                                <td class="text-end">{{ number_format($buy->total_quantity, 2) }}</td>
                                <td class="text-end"><strong>£{{ number_format($buy->total_cost, 2) }}</strong></td>
                                <td>{{ $buy->buy_dates->implode(', ') }}</td>
                                <td class="text-center">{{ $buy->transaction_count }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="table-active fw-bold">
                                <td colspan="3" class="text-end">Total:</td>
                                <td class="text-end">{{ number_format($eisSeisBuys->sum('total_quantity'), 2) }}</td>
                                <td class="text-end">£{{ number_format($eisSeisBuys->sum('total_cost'), 2) }}</td>
                                <td colspan="2"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
        @endif

        <!-- Help Information -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Tax Information</h5>
            </div>
            <div class="card-body">
                <h6>UK Tax Year</h6>
                <p>The UK tax year runs from 6 April to 5 April of the following year.</p>
                
                <h6>Tax-Exempt Accounts</h6>
                <p>Accounts marked as "Tax-Exempt" (such as ISAs and SIPPs) do not attract income tax on dividends or capital gains tax on profits. 
                To mark an account as tax-exempt, edit the account and check the "Tax Exempt" checkbox.</p>
                
                <h6>Dividend Income</h6>
                <p>Dividend income from taxable accounts is subject to income tax. The current dividend allowance for {{ $taxYear['label'] }} should be checked with HMRC.</p>
                
                <h6>Capital Gains/Losses</h6>
                <p>Capital gains and losses are calculated using the weighted average cost basis method. The cost basis includes the purchase price plus any commission paid, 
                converted to GBP at the transaction date's exchange rate. Both gains and losses are shown in the "Gain/Loss" column (losses as negative values). 
                Only gains from taxable accounts are subject to capital gains tax. Losses can be used to offset gains. The annual CGT allowance should be checked with HMRC.</p>
                
                <h6>Currency Conversion</h6>
                <p>All amounts are converted to GBP (base currency) using the exchange rate on the transaction date. This ensures accurate tax reporting regardless of the original currency of the investment.</p>
                
                <h6>EIS/SEIS Schemes</h6>
                <p>Enterprise Investment Scheme (EIS) and Seed Enterprise Investment Scheme (SEIS) are tax-advantaged investment schemes. Buy events for investments in these schemes are tracked and reported here for informational purposes. 
                Please refer to HMRC guidance for specific tax reliefs available under these schemes.</p>
                
                <p class="text-muted"><small><strong>Note:</strong> This report is for informational purposes only. Please consult with a qualified tax advisor or accountant for tax filing purposes.</small></p>
            </div>
        </div>
    </div>
</div>
@stop
