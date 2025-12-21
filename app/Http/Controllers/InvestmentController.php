<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use App\Http\Requests\InvestmentRequest;
use App\Http\Traits\ScheduleTrait;
use App\Models\Investment;
use App\Models\InvestmentPrice;
use App\Services\InvestmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
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
        // Show all investments from the database and return to view
        $investments = $request->user()
            ->investments()
            ->withCount('transactions')
            ->withCount('transactionsBasic')
            ->withCount('transactionsScheduled')
            ->with([
                'currency',
                'investmentGroup',
            ])
            ->get();

        $investments->map(function ($investment) {
            $investment['price'] = $investment->getLatestPrice();
            $investment['quantity'] = $investment->getCurrentQuantity();

            return $investment;
        });

        // Pass data for DataTables
        JavaScriptFacade::put([
            'investments' => $investments,
            'investmentGroups' => $request->user()->investmentGroups,
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

        // Get basic (non-scheduled) transactions
        $transactions = $investment->transactionsBasic()
            ->with([
                'config',
                'transactionType',
            ])
            ->get();

        // Get scheduled transactions and generate instances
        $scheduledTransactions = $investment->transactionsScheduled()
            ->with([
                'config',
                'transactionType',
                'transactionSchedule',
            ])
            ->get()
            ->filter(fn ($transaction) => $transaction->transactionSchedule && $transaction->transactionSchedule->active);

        // Add all scheduled instances to list of transactions
        $scheduleInstances = $this->getScheduleInstances($scheduledTransactions, 'start');
        $transactions = $transactions->concat($scheduleInstances);

        return view('investment.show', [
            'investment' => $investment,
            'transactions' => $transactions,
            'prices' => $prices,
        ]);
    }
}
