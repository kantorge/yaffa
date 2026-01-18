<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Auth;
use App\Http\Requests\InvestmentRequest;
use App\Http\Traits\ScheduleTrait;
use App\Models\Investment;
use App\Models\InvestmentPrice;
use App\Services\InvestmentService;
use App\Services\UnrealisedInterestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Laracasts\Utilities\JavaScript\JavaScriptFacade;

class InvestmentController extends Controller implements HasMiddleware
{
    use ScheduleTrait;

    protected InvestmentService $investmentService;

    public function __construct()
    {

        $this->investmentService = new InvestmentService();
    }

    public static function middleware(): array
    {
        return [
            ['auth', 'verified'],
            new Middleware('can:viewAny,App\Models\Investment', only: ['index']),
            new Middleware('can:view,investment', only: ['show']),
            new Middleware('can:create,App\Models\Investment', only: ['create', 'store']),
            new Middleware('can:update,investment', only: ['edit', 'update']),
            new Middleware('can:delete,investment', only: ['destroy']),
        ];
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): View
    {
        /**
         * @get('/investment')
         * @name('investment.index')
         * @middlewares('web', 'auth', 'verified', 'can:viewAny,App\Models\Investment')
         */
        // Don't load investments on initial page load to avoid timeout
        // JavaScript will fetch via AJAX based on filter state
        JavaScriptFacade::put([
            'investmentGroups' => Auth::user()->investmentGroups,
        ]);

        return view('investment.index');
    }

    /**
     * Display form to edit the resource.
     */
    public function edit(Investment $investment): View
    {
        /**
         * @get('/investment/{investment}/edit')
         * @name('investment.edit')
         * @middlewares('web', 'auth', 'verified', 'can:update,investment')
         */
        return view(
            'investment.form',
            [
                'investment' => $investment,
            ]
        );
    }

    public function update(InvestmentRequest $request, Investment $investment): RedirectResponse
    {
        /**
         * @uri('/investment/{investment}')
         * @name('investment.update')
         * @middlewares('web', 'auth', 'verified', 'can:update,investment')
         */
        // Retrieve the validated input data
        $validated = $request->validated();
        $investment->fill($validated);
        $investment->save();

        self::addSimpleSuccessMessage(__('Investment updated'));

        return redirect()->route('investment.index');
    }

    /**
     * Display form to create new resource.
     *
     * @return View|RedirectResponse
     */
    public function create(Request $request): View|RedirectResponse
    {
        /**
         * @get('/investment/create')
         * @name('investment.create')
         * @middlewares('web', 'auth', 'verified', 'can:create,App\Models\Investment')
         */
        // Redirect the user to the investment group form, if no investment groups are available
        if ($request->user()->investmentGroups()->count() === 0) {
            $this->addMessage(
                __('investment.requirement.investmentGroup'),
                'info',
                __('No investment groups found'),
                'info-circle'
            );

            return redirect()->route('investment-group.create');
        }

        // Redirect to currency form, if empty
        if ($request->user()->currencies()->count() === 0) {
            $this->addMessage(
                __('investment.requirement.currency'),
                'info',
                __('No currencies found'),
                'info-circle'
            );

            return redirect()->route('currency.create');
        }

        return view('investment.form');
    }

    public function store(InvestmentRequest $request): RedirectResponse
    {
        /**
         * @post('/investment')
         * @name('investment.store')
         * @middlewares('web', 'auth', 'verified', 'can:create,App\Models\Investment')
         */
        $investment = Investment::make($request->validated());
        $investment->user()->associate($request->user());
        $investment->save();

        self::addSimpleSuccessMessage(__('Investment added'));

        return redirect()->route('investment.index');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Investment $investment): RedirectResponse
    {
        /**
         * @delete('/investment/{investment}')
         * @name('investment.destroy')
         * @middlewares('web', 'auth', 'verified', 'can:delete,investment')
         */

        $result = $this->investmentService->delete($investment);

        if ($result['success']) {
            self::addSimpleSuccessMessage(__('Investment deleted'));
            return redirect()->route('investment.index');
        }

        self::addSimpleErrorMessage($result['error']);
        return redirect()->back();
    }

    public function show(Investment $investment): View
    {
        /**
         * @get('/investment/{investment}')
         * @name('investment.show')
         * @middlewares('web', 'auth', 'verified', 'can:view,investment')
         */

        // Get all stored price points
        $prices = InvestmentPrice::where('investment_id', $investment->id)
            ->orderBy('date')
            ->get();

        // Eager load investment details to be displayed
        $investment->load([
            'investmentGroup',
            'currency',
        ]);

        // Add current quantity and price as dynamic properties for use in the view or JS
        $investment->current_quantity = $investment->getCurrentQuantity();
        $investment->latest_price = $investment->getLatestPrice();

        $investmentService = new InvestmentService();
        $investment = $investmentService->enrichInvestmentWithQuantityHistory($investment);

        $transactions = $investment->transactionsBasic()
            ->with([
                'config',
                'transactionType',
            ])
            ->get();

        // Generate waterfall data grouped by month
        $waterfallData = $this->generateWaterfallData($investment, $transactions, $prices);

        return view('investment.show', [
            'investment' => $investment,
            'transactions' => $transactions,
            'prices' => $prices,
            'waterfallData' => $waterfallData,
        ]);
    }

    /**
     * Generate waterfall data for Price/Volume analysis
     * Groups transactions by month and calculates buy/sell volumes and price changes
     */
    private function generateWaterfallData($investment, $transactions, $prices)
    {
        $waterfall = [];
        $monthlyData = [];

        // Get prices by month for price change calculation
        $pricesByMonth = [];
        foreach ($prices as $price) {
            $period = $price->date->format('Y-m');
            $pricesByMonth[$period] = $price->price;
        }

        // Initialize all periods with prices (not just transaction months)
        foreach (array_keys($pricesByMonth) as $period) {
            $monthlyData[$period] = [
                'period' => $period,
                'buys' => 0,
                'sells' => 0,
                'quantity_start' => 0,
                'quantity_end' => 0,
            ];
        }

        // Group transactions by month
        foreach ($transactions as $transaction) {
            $period = $transaction->date->format('Y-m');

            if (!isset($monthlyData[$period])) {
                $monthlyData[$period] = [
                    'period' => $period,
                    'buys' => 0,
                    'sells' => 0,
                    'quantity_start' => 0,
                    'quantity_end' => 0,
                ];
            }

            $config = $transaction->config;
            $typeId = $transaction->transaction_type_id;

            // Buy transaction (type 4)
            if ($typeId === 4 && $config) {
                $value = ($config->price * $config->quantity) + ($config->commission ?? 0);
                $monthlyData[$period]['buys'] += $value;
            }
            // Sell transaction (type 5)
            elseif ($typeId === 5 && $config) {
                $value = ($config->price * $config->quantity) - ($config->commission ?? 0);
                $monthlyData[$period]['sells'] -= $value; // Negative value for waterfall
            }
        }

        // Sort by period
        ksort($monthlyData);

        // Calculate price changes and running totals
        $previousPrice = null;
        $cumulativeQuantity = 0;
        $runningTotal = 0;

        foreach ($monthlyData as $period => $data) {
            // Calculate quantity change
            $quantityChange = 0;
            foreach ($transactions as $transaction) {
                if ($transaction->date->format('Y-m') === $period) {
                    $config = $transaction->config;
                    $typeId = $transaction->transaction_type_id;

                    if (in_array($typeId, [4, 6]) && $config) { // Buy or Add shares
                        $quantityChange += $config->quantity ?? 0;
                    } elseif (in_array($typeId, [5, 7]) && $config) { // Sell or Remove shares
                        $quantityChange -= $config->quantity ?? 0;
                    }
                }
            }

            $data['quantity_start'] = $cumulativeQuantity;
            $cumulativeQuantity += $quantityChange;
            $data['quantity_end'] = $cumulativeQuantity;

            // Get current month price or use previous month's price
            $currentPrice = $pricesByMonth[$period] ?? $previousPrice;

            // Calculate price change impact on value
            $priceChange = 0;
            if ($previousPrice !== null && $currentPrice !== null && $data['quantity_start'] > 0) {
                $priceChange = ($currentPrice - $previousPrice) * $data['quantity_start'];
            }

            $data['priceChange'] = round($priceChange, 2);

            // Calculate running total (waterfall cumulative)
            // Start with buys (increases total)
            $runningTotal += $data['buys'];

            // Then apply price changes (can increase or decrease)
            $runningTotal += $data['priceChange'];

            // Then apply sells (decreases total, already negative)
            $runningTotal += $data['sells'];

            // Set the final running total after all adjustments
            $data['runningTotal'] = round($runningTotal, 2);

            $previousPrice = $currentPrice;

            $waterfall[] = $data;
        }

        return $waterfall;
    }

    /**
     * Display unrealised interest details for the investment
     */
    public function interest(Investment $investment): View
    {
        /**
         * @get('/investment/{investment}/interest')
         * @name('investment.interest')
         * @middlewares('web', 'auth', 'verified', 'can:view,investment')
         */
        $service = new UnrealisedInterestService();
        $interestData = $service->calculateInvestmentInterest($investment);

        return view('investment.interest', [
            'investment' => $investment,
            'interestData' => $interestData,
        ]);
    }

    /**
     * Display the transaction upload form.
     *
     * @return View
     */
    public function uploadForm(): View
    {
        /**
         * @get('/investment/transaction/upload')
         * @name('investment.upload')
         * @middlewares('web', 'auth', 'verified')
         */
        return view('investment.upload');
    }
}
