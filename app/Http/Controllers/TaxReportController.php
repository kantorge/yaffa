<?php

namespace App\Http\Controllers;

use Illuminate\Routing\Controllers\HasMiddleware;
use App\Http\Traits\UkTaxYearTrait;
use App\Services\TaxReportService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TaxReportController extends Controller implements HasMiddleware
{
    use UkTaxYearTrait;

    protected TaxReportService $taxReportService;

    public static function middleware(): array
    {
        return [
            'auth',
        ];
    }

    public function __construct(TaxReportService $taxReportService)
    {
        $this->taxReportService = $taxReportService;
    }

    /**
     * Display UK tax report summary
     */
    public function index(Request $request)
    {
        $userId = Auth::id();

        // Get requested tax year or default to current
        $taxYearParam = $request->get('tax_year');

        if ($taxYearParam) {
            $taxYear = $this->parseTaxYearString($taxYearParam);
        } else {
            $now = Carbon::now();
            $taxYear = [
                'start' => $this->getTaxYearStart($now),
                'end' => $this->getTaxYearEnd($now),
                'label' => $this->getTaxYearLabel($now),
            ];
        }

        // Get available tax years for dropdown
        $availableTaxYears = $this->getAvailableTaxYears();

        // Get dividend summary
        $dividends = $this->taxReportService->getDividendSummary(
            $userId,
            $taxYear['start'],
            $taxYear['end']
        );

        // Get capital gains summary
        $capitalGains = $this->taxReportService->getCapitalGainsSummary(
            $userId,
            $taxYear['start'],
            $taxYear['end']
        );

        // Get summary totals
        $summary = $this->taxReportService->getTaxYearSummary(
            $userId,
            $taxYear['start'],
            $taxYear['end']
        );

        // Get EIS/SEIS investments with buy events
        $eisSeisBuys = $this->taxReportService->getEisSeisBuys(
            $userId,
            $taxYear['start'],
            $taxYear['end']
        );

        return view('reports.tax', [
            'taxYear' => $taxYear,
            'availableTaxYears' => $availableTaxYears,
            'dividends' => $dividends,
            'capitalGains' => $capitalGains,
            'summary' => $summary,
            'eisSeisBuys' => $eisSeisBuys,
        ]);
    }

    /**
     * Export tax report data
     */
    public function export(Request $request)
    {
        $userId = Auth::id();
        $taxYearParam = $request->get('tax_year');

        if ($taxYearParam) {
            $taxYear = $this->parseTaxYearString($taxYearParam);
        } else {
            $now = Carbon::now();
            $taxYear = [
                'start' => $this->getTaxYearStart($now),
                'end' => $this->getTaxYearEnd($now),
                'label' => $this->getTaxYearLabel($now),
            ];
        }

        $dividends = $this->taxReportService->getDividendSummary(
            $userId,
            $taxYear['start'],
            $taxYear['end']
        );

        $capitalGains = $this->taxReportService->getCapitalGainsSummary(
            $userId,
            $taxYear['start'],
            $taxYear['end']
        );

        $summary = $this->taxReportService->getTaxYearSummary(
            $userId,
            $taxYear['start'],
            $taxYear['end']
        );

        // Return CSV download
        $filename = "tax-report-{$taxYear['label']}.csv";
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function () use ($dividends, $capitalGains, $summary, $taxYear) {
            $file = fopen('php://output', 'w');

            // Tax year header
            fputcsv($file, ['UK Tax Report', $taxYear['label']]);
            fputcsv($file, ['Report Generated', Carbon::now()->toDateTimeString()]);
            fputcsv($file, []);

            // Dividend section
            fputcsv($file, ['DIVIDEND INCOME']);
            fputcsv($file, ['Account', 'Investment', 'Investment Group', 'Tax Exempt', 'Total Dividend', 'Taxable Amount', 'Transaction Count']);
            foreach ($dividends as $div) {
                fputcsv($file, [
                    $div->account_name,
                    $div->investment_name,
                    $div->investment_group_name,
                    $div->tax_exempt ? 'Yes' : 'No',
                    number_format($div->total_dividend, 2),
                    number_format($div->taxable_amount, 2),
                    $div->transaction_count,
                ]);
            }
            fputcsv($file, []);

            // Capital gains section
            fputcsv($file, ['CAPITAL GAINS']);
            fputcsv($file, ['Account', 'Investment', 'Tax Exempt', 'Shares Sold', 'Avg Buy Price', 'Avg Sell Price', 'Cost Basis', 'Net Proceeds', 'Gain/Loss', 'Taxable Gain']);
            foreach ($capitalGains as $cg) {
                fputcsv($file, [
                    $cg['account_name'],
                    $cg['investment_name'],
                    $cg['tax_exempt'] ? 'Yes' : 'No',
                    number_format($cg['shares_sold'], 4),
                    number_format($cg['avg_buy_price'], 4),
                    number_format($cg['avg_sell_price'], 4),
                    number_format($cg['cost_basis'], 2),
                    number_format($cg['net_proceeds'], 2),
                    number_format($cg['gain_loss'], 2),
                    number_format($cg['taxable_gain'], 2),
                ]);
            }
            fputcsv($file, []);

            // Summary
            fputcsv($file, ['SUMMARY']);
            fputcsv($file, ['Total Dividends', number_format($summary['total_dividends'], 2)]);
            fputcsv($file, ['Taxable Dividends', number_format($summary['taxable_dividends'], 2)]);
            fputcsv($file, ['Tax-Exempt Dividends', number_format($summary['tax_exempt_dividends'], 2)]);
            fputcsv($file, []);
            fputcsv($file, ['Total Capital Gains', number_format($summary['total_gains'], 2)]);
            fputcsv($file, ['Taxable Capital Gains', number_format($summary['taxable_gains'], 2)]);
            fputcsv($file, ['Tax-Exempt Capital Gains', number_format($summary['tax_exempt_gains'], 2)]);

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
