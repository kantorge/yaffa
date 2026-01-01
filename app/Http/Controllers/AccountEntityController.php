<?php

namespace App\Http\Controllers;

use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use App\Http\Requests\AccountEntityRequest;
use App\Http\Requests\MergePayeesRequest;
use App\Models\Account;
use App\Models\AccountEntity;
use App\Models\Payee;
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
    public static function middleware(): array
    {
        return [
            ['auth', 'verified'],
            new Middleware('can:viewAny,App\Models\AccountEntity', only: ['index']),
            new Middleware('can:view,account_entity', only: ['show']),
            new Middleware('can:create,App\Models\AccountEntity', only: ['create', 'store']),
            new Middleware('can:update,account_entity', only: ['edit', 'update']),
            new Middleware('can:delete,account_entity', only: ['destroy']),
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
                'account.show',
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
         * @get('/account-entity')
         * @name('account-entity.index')
         * @middlewares('web', 'auth', 'verified', 'can:viewAny,App\Models\AccountEntity')
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

        return view('account.index');
    }

    private function indexPayee(): View
    {
        // Show all payees of the user from the database and return to view
        $payees = Auth::user()
            ->payees()
            ->withCount('transactionsStandardFrom as from_count')
            ->withCount('transactionsStandardTo as to_count')
            ->withMin('transactionsStandardFrom as from_min_date', 'date')
            ->withMax('transactionsStandardFrom as from_max_date', 'date')
            ->withMin('transactionsStandardTo as to_min_date', 'date')
            ->withMax('transactionsStandardTo as to_max_date', 'date')
            ->with(['config', 'config.category'])
            ->get();

        // Pass data for DataTables
        JavaScriptFacade::put([
            'payees' => $payees,
        ]);

        return view('payee.index');
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
         * @get('/account-entity/create')
         * @name('account-entity.create')
         * @middlewares('web', 'auth', 'verified', 'can:create,App\Models\AccountEntity')
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

            return to_route('account-group.create');
        }

        // Redirect to currency form, if empty
        if (Auth::user()->currencies()->count() === 0) {
            $this->addMessage(
                __('account.requirement.currency'),
                'info',
                __('No currencies found'),
                'info-circle'
            );

            return to_route('currency.create');
        }

        return view('account.form');
    }

    private function createPayee(): View
    {
        JavaScriptFacade::put([
            'categoryPreferences' => [],
        ]);

        return view('payee.form');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(AccountEntityRequest $request): RedirectResponse
    {
        /**
         * @post('/account-entity')
         * @name('account-entity.store')
         * @middlewares('web', 'auth', 'verified', 'can:create,App\Models\AccountEntity')
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
            $payeeConfig = Payee::create($validated['config']);
            $accountEntity->config()->associate($payeeConfig);

            // Sync category preference. First, create a variable.
            // Set preferred categories to boolean true and not preferred categories to boolean false.
            $preferences = [];
            if (array_key_exists('preferred', $validated['config'])) {
                foreach ($validated['config']['preferred'] as $categoryId) {
                    $preferences[$categoryId] = ['preferred' => true];
                }
            }
            if (array_key_exists('not_preferred', $validated['config'])) {
                foreach ($validated['config']['not_preferred'] as $categoryId) {
                    $preferences[$categoryId] = ['preferred' => false];
                }
            }

            $accountEntity->push();

            $accountEntity->categoryPreference()->sync($preferences);

            $accountEntity->push();

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
         * @get('/account-entity/{account_entity}/edit')
         * @name('account-entity.edit')
         * @middlewares('web', 'auth', 'verified', 'can:update,account_entity')
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
            'account.form',
            [
                'account' => $accountEntity,
                'allAccountGroups' => $allAccountGroups,
                'allCurrencies' => $allCurrencies,
            ]
        );
    }

    private function editPayee(AccountEntity $accountEntity): View
    {
        $accountEntity->load(['config', 'categoryPreference']);

        // Simplify the category preference structure and pass it as JavaScript variable
        $categoryPreference = $accountEntity->categoryPreference->map(fn ($item) => [
            'id' => $item->id,
            'full_name' => $item->full_name,
            'preferred' => $item->pivot->preferred,
        ]);
        JavaScriptFacade::put([
            'categoryPreferences' => $categoryPreference->toArray(),
        ]);

        return view(
            'payee.form',
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
         * @method('PUT', PATCH')
         * @uri('/account-entity/{account_entity}')
         * @name('account-entity.update')
         * @middlewares('web', 'auth', 'verified', 'can:update,account_entity')
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
            $accountEntity->load(['config']);

            $accountEntity->fill($validated);
            $accountEntity->config->fill($validated['config']);

            // Sync category preference. First, create a variable.
            // Set preferred categories to boolean true and not preferred categories to boolean false.
            $preferences = [];
            if (array_key_exists('preferred', $validated['config'])) {
                foreach ($validated['config']['preferred'] as $categoryId) {
                    $preferences[$categoryId] = ['preferred' => true];
                }
            }
            if (array_key_exists('not_preferred', $validated['config'])) {
                foreach ($validated['config']['not_preferred'] as $categoryId) {
                    $preferences[$categoryId] = ['preferred' => false];
                }
            }

            $accountEntity->categoryPreference()->sync($preferences);

            $accountEntity->push();

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
         * @get('/payees/merge/{payeeSource?}')
         * @name('payees.merge.form')
         * @middlewares('web', 'auth', 'verified')
         */
        if ($payeeSource) {
            JavaScriptFacade::put([
                'payeeSource' => $payeeSource->toArray(),
            ]);
        }

        return view('payee.merge');
    }

    /**
     * Merge two payees.
     */
    public function mergePayees(MergePayeesRequest $request): RedirectResponse
    {
        /**
         * @post('/payees/merge')
         * @name('payees.merge.submit')
         * @middlewares('web', 'auth', 'verified')
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
