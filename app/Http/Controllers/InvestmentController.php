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
use Illuminate\View\View;
use Laracasts\Utilities\JavaScript\JavaScriptFacade;

class InvestmentController extends Controller implements HasMiddleware
{
    use ScheduleTrait;

    public function __construct(
        protected InvestmentService $investmentService
    ) {
    }

    public static function middleware(): array
    {
        return [
            'auth',
            'verified',
            new Middleware('can:viewAny,' . Investment::class, only: ['index']),
            new Middleware('can:view,investment', only: ['show']),
            new Middleware('can:create,' . Investment::class, only: ['create', 'store']),
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
         * @get("/investments")
         * @name("investments.index")
         * @middlewares("web", "auth", "verified")
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
            if (! $investment instanceof Investment) {
                return $investment;
            }

            $investment['price'] = $this->investmentService->getLatestPrice($investment);
            $investment['quantity'] = $this->investmentService->getCurrentQuantity($investment);

            return $investment;
        });

        // Pass data for DataTables
        JavaScriptFacade::put([
            'investments' => $investments,
            'investmentGroups' => $request->user()->investmentGroups,
        ]);

        return view('investments.index');
    }

    /**
     * Display form to edit the resource.
     */
    public function edit(Investment $investment): View
    {
        /**
         * @get("/investments/{investment}/edit")
         * @name("investments.edit")
         * @middlewares("web", "auth", "verified")
         */
        return view(
            'investments.form',
            [
                'investment' => $investment,
            ]
        );
    }

    public function update(InvestmentRequest $request, Investment $investment): RedirectResponse
    {
        /**
         * @uri("/investments/{investment}")
         * @name("investments.update")
         * @middlewares("web", "auth", "verified")
         */
        // Retrieve the validated input data
        $validated = $request->validated();
        $investment->fill($validated);
        $investment->save();

        self::addSimpleSuccessMessage(__('Investment updated'));

        return to_route('investments.index');
    }

    /**
     * Display form to create new resource.
     *
     * @return View|RedirectResponse
     */
    public function create(Request $request): View|RedirectResponse
    {
        /**
         * @get("/investments/create")
         * @name("investments.create")
         * @middlewares("web", "auth", "verified")
         */
        // Redirect the user to the investment group form, if no investment groups are available
        if ($request->user()->investmentGroups()->count() === 0) {
            $this->addMessage(
                __('investment.requirement.investmentGroup'),
                'info',
                __('No investment groups found'),
                'info-circle'
            );

            return to_route('investment-groups.create');
        }

        // Redirect to currency form, if empty
        if ($request->user()->currencies()->count() === 0) {
            $this->addMessage(
                __('investment.requirement.currency'),
                'info',
                __('No currencies found'),
                'info-circle'
            );

            return to_route('currencies.create');
        }

        return view('investments.form');
    }

    public function store(InvestmentRequest $request): RedirectResponse
    {
        /**
         * @post("/investments")
         * @name("investments.store")
         * @middlewares("web", "auth", "verified")
         */
        $investment = new Investment($request->validated());
        $investment->user()->associate($request->user());
        $investment->save();

        self::addSimpleSuccessMessage(__('Investment added'));

        return to_route('investments.index');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Investment $investment): RedirectResponse
    {
        /**
         * @delete("/investments/{investment}")
         * @name("investments.destroy")
         * @middlewares("web", "auth", "verified")
         */

        $result = $this->investmentService->delete($investment);

        if ($result['success']) {
            self::addSimpleSuccessMessage(__('Investment deleted'));
            return to_route('investments.index');
        }

        self::addSimpleErrorMessage($result['error']);
        return redirect()->back();
    }

    public function show(Investment $investment): View
    {
        /**
         * @get("/investments/{investment}")
         * @name("investments.show")
         * @middlewares("web", "auth", "verified")
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
        $investment->current_quantity = $this->investmentService->getCurrentQuantity($investment);
        $investment->latest_price = $this->investmentService->getLatestPrice($investment);

        $investment = $this->investmentService->enrichInvestmentWithQuantityHistory($investment);

        // Get basic (non-scheduled) transactions
        $transactions = $investment->transactionsBasic()
            ->with([
                'config',
            ])
            ->get();

        // Get scheduled transactions and generate instances
        $scheduledTransactions = $investment->transactionsScheduled()
            ->with([
                'config',
                'transactionSchedule',
            ])
            ->get()
            ->filter(fn ($transaction): bool => $transaction instanceof \App\Models\Transaction
                && ($transaction->transactionSchedule?->active) === true);

        // Add all scheduled instances to list of transactions
        $scheduleInstances = $this->getScheduleInstances($scheduledTransactions, 'start');
        $transactions = $transactions->concat($scheduleInstances);

        return view('investments.show', [
            'investment' => $investment,
            'transactions' => $transactions,
            'prices' => $prices,
        ]);
    }
}
