<?php

namespace App\Http\Controllers;

use App\Http\Requests\InvestmentRequest;
use App\Http\Traits\ScheduleTrait;
use App\Models\Investment;
use App\Models\InvestmentPrice;
use App\Services\InvestmentService;
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
        $this->middleware('can:viewAny,App\Models\Investment')->only('index');
        $this->middleware('can:view,investment')->only('show');
        $this->middleware('can:create,App\Models\Investment')->only('create', 'store');
        $this->middleware('can:update,investment')->only('edit', 'update');
        $this->middleware('can:delete,investment')->only('destroy');

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
        $investment = Investment::make($request->validated());
        $investment->user()->associate(Auth::user());
        $investment->save();

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

        return view('investment.show', [
            'investment' => $investment,
            'transactions' => $transactions,
            'prices' => $prices,
        ]);
    }
}
