<?php

namespace App\Http\Controllers;

use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use App\Http\Requests\AccountEntityRequest;
use App\Http\Requests\MergePayeesRequest;
use App\Models\Account;
use App\Models\AccountEntity;
use App\Models\Category;
use App\Services\PayeeCategoryStatsService;
use App\Services\PayeePersistenceService;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Laracasts\Utilities\JavaScript\JavaScriptFacade;

class AccountEntityController extends Controller implements HasMiddleware
{
    public function __construct(
        private readonly PayeeCategoryStatsService $payeeCategoryStatsService,
        private readonly PayeePersistenceService $payeePersistenceService,
    ) {
    }

    public static function middleware(): array
    {
        return [
            'auth',
            'verified',
            new Middleware('can:view,account_entity', only: ['show']),
            new Middleware('can:create,' . AccountEntity::class, only: ['create', 'store']),
            new Middleware('can:update,account_entity', only: ['edit', 'update']),
        ];
    }

    /*
     * Check if type parameter is provided and if it is valid. It is only needed if not running from CLI.
     */
    private function checkTypeParam(Request $request): void
    {
        if (!app()->runningInConsole()
            && (!$request->has('type') || !in_array($request->type, ['account', 'payee']))) {
            abort(Response::HTTP_NOT_FOUND);
        }
    }

    public function show(AccountEntity $accountEntity, Request $request): View|RedirectResponse
    {
        // Load view for Accounts
        if ($accountEntity->config_type === 'account') {
            $accountEntity->load([
                'config',
                'config.currency',
            ]);

            // Get preset filters from query string
            $filters = [];
            if ($request->has('date_from')) {
                $filters['date_from'] = $request->get('date_from');
            }
            if ($request->has('date_to')) {
                $filters['date_to'] = $request->get('date_to');
            }

            // If neither date_from nor date_to is set, check for date_preset or use default
            if (!$request->has('date_from') && !$request->has('date_to')) {
                if ($request->has('date_preset')) {
                    $filters['date_preset'] = $request->get('date_preset');
                } else {
                    $filters['date_preset'] = $accountEntity->config->default_date_range
                    ?? $request->user()->account_details_date_range
                    ?? 'none';
                }
            }

            JavaScriptFacade::put([
                'account' => $accountEntity,
                'filters' => $filters,
            ]);

            return view(
                'accounts.show',
                [
                    'account' => $accountEntity,
                ]
            );
        }

        // Currently no function for Payees, redirect back
        return redirect()->back();
    }

    /**
     * Display a listing of the resource, for the type specified in request.
     *
     * @uses indexAccount()
     * @uses indexPayee()
     */
    public function index(Request $request): View
    {
        /**
         * @get("/account-entity")
         * @name("account-entity.index")
         * @middlewares("web", "auth", "verified")
         */
        $this->checkTypeParam($request);

        return $this->{'index' . Str::ucfirst($request->get('type'))}();
    }

    private function indexAccount(): View
    {
        // Show all accounts of user from the database and return to view
        $accounts = Auth::user()
            ->accounts()
            ->withCount('transactionsInvestment')
            ->withCount('transactionsStandardFrom')
            ->withCount('transactionsStandardTo')
            ->with(['config', 'config.accountGroup', 'config.currency'])
            ->get()
            ->map(function (AccountEntity $account) {
                $account->transactions_count = $account->transactions_investment_count
                    + $account->transactions_standard_from_count
                    + $account->transactions_standard_to_count;

                return $account;
            });

        // Pass data for DataTables
        JavaScriptFacade::put([
            'accounts' => $accounts,
        ]);

        return view('accounts.index');
    }

    private function indexPayee(): View
    {
        $user = Auth::user();

        // Show all payees of the user from the database and return to view
        $payees = $user
            ->payees()
            ->withCount('transactionsStandardFrom as from_count')
            ->withCount('transactionsStandardTo as to_count')
            ->withMin('transactionsStandardFrom as from_min_date', 'date')
            ->withMax('transactionsStandardFrom as from_max_date', 'date')
            ->withMin('transactionsStandardTo as to_min_date', 'date')
            ->withMax('transactionsStandardTo as to_max_date', 'date')
            ->with(['config', 'config.category', 'config.category.parent'])
            ->get();

        $categorySuggestionsByPayeeId = $this->payeeCategoryStatsService
            ->getDefaultSuggestionsForAllPayees($user)
            ->keyBy('payee_id');

        $payees->each(function (AccountEntity $payee) use ($categorySuggestionsByPayeeId): void {
            $suggestion = $categorySuggestionsByPayeeId->get($payee->id);

            $payee->setAttribute('category_suggestion', $suggestion === null ? null : [
                'max_category_id' => (int) $suggestion['max_category_id'],
                'category' => (string) $suggestion['category'],
                'max' => (int) $suggestion['max'],
                'sum' => (int) $suggestion['sum'],
            ]);
        });

        // Pass data for DataTables
        JavaScriptFacade::put([
            'payees' => $payees,
        ]);

        return view('payees.index');
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return View|RedirectResponse
     * @uses createPayee
     * @uses createAccount
     */
    public function create(Request $request): View|RedirectResponse
    {
        /**
         * @get("/account-entity/create")
         * @name("account-entity.create")
         * @middlewares("web", "auth", "verified")
         */
        $this->checkTypeParam($request);

        return $this->{'create' . Str::ucfirst($request->type)}();
    }

    private function createAccount(): View|RedirectResponse
    {
        // Redirect to account group form, if empty
        if (Auth::user()->accountGroups()->count() === 0) {
            $this->addMessage(
                __('account.requirement.accountGroup'),
                'info',
                __('No account groups found'),
                'info-circle'
            );

            return to_route('account-groups.create');
        }

        // Redirect to currency form, if empty
        if (Auth::user()->currencies()->count() === 0) {
            $this->addMessage(
                __('account.requirement.currency'),
                'info',
                __('No currencies found'),
                'info-circle'
            );

            return to_route('currencies.create');
        }

        return view('accounts.form');
    }

    private function createPayee(): View
    {
        JavaScriptFacade::put([
            'categoryPreferences' => [],
        ]);

        return view('payees.form');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(AccountEntityRequest $request): RedirectResponse
    {
        /**
         * @post("/account-entity")
         * @name("account-entity.store")
         * @middlewares("web", "auth", "verified")
         */
        $this->checkTypeParam($request);

        $validated = $request->validated();

        $accountEntity = new AccountEntity($validated);
        $accountEntity->user_id = $request->user()->id;

        if ($validated['config_type'] === 'account') {
            $accountConfig = Account::create($validated['config']);
            $accountEntity->config()->associate($accountConfig);

            $accountEntity->push();

            self::addMessage(
                __('Account added'),
                'success',
                null,
                null,
                true
            );

            return to_route('account-entity.index', ['type' => 'account']);
        }

        if ($validated['config_type'] === 'payee') {
            $this->payeePersistenceService->store($request);

            self::addSimpleSuccessMessage(__('Payee added'));

            return to_route('account-entity.index', ['type' => 'payee']);
        }

        // This redirect is theoretically not used
        return redirect()->back();
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @uses editPayee
     * @uses editAccount
     */
    public function edit(AccountEntity $accountEntity): View
    {
        /**
         * @get("/account-entity/{account_entity}/edit")
         * @name("account-entity.edit")
         * @middlewares("web", "auth", "verified")
         */
        return $this->{'edit' . Str::ucfirst($accountEntity->config_type)}($accountEntity);
    }

    private function editAccount(AccountEntity $accountEntity): View
    {
        $accountEntity->load(['config', 'config.accountGroup', 'config.currency']);

        // Get all account groups of the user
        $allAccountGroups = Auth::user()->accountGroups()->pluck('name', 'id')->all();

        // Get all currencies of the user
        $allCurrencies = Auth::user()->currencies()->pluck('name', 'id')->all();

        return view(
            'accounts.form',
            [
                'account' => $accountEntity,
                'allAccountGroups' => $allAccountGroups,
                'allCurrencies' => $allCurrencies,
            ]
        );
    }

    private function editPayee(AccountEntity $accountEntity): View
    {
        $accountEntity->load(['config', 'categoryPreference.parent']);

        // Simplify the category preference structure and pass it as JavaScript variable
        $categoryPreference = $accountEntity->categoryPreference->map(fn (Category $item): array => [
            'id' => $item->id,
            'full_name' => $item->full_name,
            'preferred' => (bool) data_get($item, 'pivot.preferred', false),
        ]);
        JavaScriptFacade::put([
            'categoryPreferences' => $categoryPreference->toArray(),
        ]);

        return view(
            'payees.form',
            [
                'payee' => $accountEntity,
            ]
        );
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(AccountEntityRequest $request, AccountEntity $accountEntity): RedirectResponse
    {
        /**
         * @methods("PUT", "PATCH")
         * @uri("/account-entity/{account_entity}")
         * @name("account-entity.update")
         * @middlewares("web", "auth", "verified")
         */
        $validated = $request->validated();

        if ($accountEntity->config_type === 'account') {
            $accountEntity->load(['config']);

            $accountEntity->fill($validated);
            $accountEntity->config->fill($validated['config']);

            $accountEntity->push();

            self::addSimpleSuccessMessage(__('Account updated'));

            return to_route('account-entity.index', ['type' => 'account']);
        }

        if ($accountEntity->config_type === 'payee') {
            $this->payeePersistenceService->update($accountEntity, $request);

            self::addSimpleSuccessMessage(__('Payee updated'));

            return to_route('account-entity.index', ['type' => 'payee']);
        }

        // This redirect is theoretically not used
        return redirect()->back();
    }

    /**
     * Display a form to merge two payees.
     */
    public function mergePayeesForm(?AccountEntity $payeeSource): View
    {
        /**
         * @get("/payees/merge/{payeeSource?}")
         * @name("payees.merge.form")
         * @middlewares("web", "auth", "verified")
         */
        if ($payeeSource) {
            JavaScriptFacade::put([
                'payeeSource' => $payeeSource->toArray(),
            ]);
        }

        return view('payees.merge');
    }

    /**
     * Merge two payees.
     */
    public function mergePayees(MergePayeesRequest $request): RedirectResponse
    {
        /**
         * @post("/payees/merge")
         * @name("payees.merge.submit")
         * @middlewares("web", "auth", "verified")
         */
        $validated = $request->validated();

        // Wrap database transaction
        DB::beginTransaction();
        try {
            // Update all transaction detail items with source payee to target payee
            DB::table('transaction_details_standard')
                ->where('account_from_id', $validated['payee_source'])
                ->update(['account_from_id' => $validated['payee_target']]);

            DB::table('transaction_details_standard')
                ->where('account_to_id', $validated['payee_source'])
                ->update(['account_to_id' => $validated['payee_target']]);

            // Hydrate the source payee
            $payeeSource = AccountEntity::find($validated['payee_source']);

            // Delete or set active to false the source payee model, based on value of action field
            if ($request->action === 'delete') {
                $payeeSource->delete();
            } else {
                $payeeSource->active = false;
                $payeeSource->push();
            }

            DB::commit();
            self::addSimpleSuccessMessage(__('Payees merged'));
        } catch (Exception $e) {
            DB::rollback();
            self::addSimpleErrorMessage(__('Database error:') . ' ' . $e->getMessage());
        }

        return to_route('account-entity.index', ['type' => 'payee']);
    }
}
