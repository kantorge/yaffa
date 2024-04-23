<?php

namespace App\Http\Controllers;

use App\Http\Requests\InvestmentRequest;
use App\Http\Traits\ScheduleTrait;
use App\Models\Investment;
use App\Models\InvestmentPrice;
use App\Models\Transaction;
use App\Models\TransactionDetailInvestment;
use App\Services\InvestmentService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Laracasts\Utilities\JavaScript\JavaScriptFacade;

class InvestmentController extends Controller
{
    use ScheduleTrait;

    protected InvestmentService $investmentService;

    public function __construct()
    {
        $this->middleware(['auth', 'verified']);
        $this->authorizeResource(Investment::class);

        $this->investmentService = new InvestmentService();
    }

    /**
     * Display a listing of the resource.
     *
     * @return View
     */
    public function index(): View
    {
        /**
         * @get('/investment')
         * @name('investment.index')
         * @middlewares('web', 'auth', 'verified', 'can:viewAny,App\Models\Investment')
         */
        // Show all investments from the database and return to view
        $investments = Auth::user()
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
            'investmentGroups' => Auth::user()->investmentGroups,
        ]);

        return view('investment.index');
    }

    /**
     * Display form to edit the resource.
     *
     * @param Investment $investment
     * @return View
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
         * @methods('PUT', PATCH')
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
    public function create(): View|RedirectResponse
    {
        /**
         * @get('/investment/create')
         * @name('investment.create')
         * @middlewares('web', 'auth', 'verified', 'can:create,App\Models\Investment')
         */
        // Redirect the user to the investment group form, if no investment groups are available
        if (Auth::user()->investmentGroups()->count() === 0) {
            $this->addMessage(
                __('investment.requirement.investmentGroup'),
                'info',
                __('No investment groups found'),
                'info-circle'
            );

            return redirect()->route('investment-group.create');
        }

        // Redirect to currency form, if empty
        if (Auth::user()->currencies()->count() === 0) {
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
        Investment::create($request->validated());

        self::addSimpleSuccessMessage(__('Investment added'));

        return redirect()->route('investment.index');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param Investment $investment
     * @return RedirectResponse
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

        self::addSimpleDangerMessage($result['error']);
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

        // Get all transactions related to selected investment
        $rawTransactions = Transaction::with([
            'config',
            'transactionType',
            'transactionSchedule'
        ])
            ->whereHasMorph(
                'config',
                [TransactionDetailInvestment::class],
                function (Builder $query) use ($investment) {
                    $query->where('investment_id', $investment->id);
                }
            )
            ->orderBy('date')
            ->get();

        // Split the transactions into historical and scheduled, based on the schedule flag
        [$scheduledTransactions, $transactions] = $rawTransactions->partition('schedule');

        // Add all scheduled items to list of transactions
        $scheduleInstances = $this->getScheduleInstances($scheduledTransactions, 'start');
        $transactions = $transactions->concat($scheduleInstances);

        // Calculate historical and scheduled quantity changes for chart
        $runningTotal = 0;
        $runningSchedule = 0;
        $quantities = $transactions
            ->sortBy('date')
            ->map(function (Transaction $transaction) use (&$runningTotal, &$runningSchedule) {
                // Quantity operator can be 1, -1 or null.
                // It's the expected behavior to set the quantity to 0 if the operator is null.
                $quantity = $transaction->transactionType->quantity_multiplier * $transaction->config->quantity;

                $runningSchedule += $quantity;
                if (!$transaction->schedule) {
                    $runningTotal += $quantity;
                }

                return [
                    'date' => $transaction->date->format('Y-m-d'),
                    'quantity' => $runningTotal,
                    'schedule' => $runningSchedule,
                ];
            });

        JavaScriptFacade::put([
            'investment' => $investment,
            'transactions' => array_values($transactions->toArray()),
            'prices' => $prices,
            'quantities' => array_values($quantities->toArray()),
        ]);

        return view('investment.show', [
            'investment' => $investment,
        ]);
    }

    /**
     * Display view with timeline chart.
     *
     * @return View
     */
    public function timeline(): View
    {
        /**
         * @get('/investment/timeline')
         * @name('investment.timeline')
         * @middlewares('web', 'auth', 'verified')
         */
        return view('investment.timeline');
    }
}
