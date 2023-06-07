<?php

namespace App\Http\Controllers;

use App\Http\Requests\AccountEntityRequest;
use App\Http\Requests\MergePayeesRequest;
use App\Models\Account;
use App\Models\AccountEntity;
use App\Models\Category;
use App\Models\Payee;
use App\Services\AccountEntityService;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Laracasts\Utilities\JavaScript\JavaScriptFacade;

class AccountEntityController extends Controller
{
    protected AccountEntityService $accountEntityService;

    public function __construct()
    {
        $this->middleware(['auth', 'verified']);
        $this->authorizeResource(AccountEntity::class);

        $this->accountEntityService = new AccountEntityService();
    }

    /*
     * Check if type parameter is provided and if it is valid. It is only needed if not running from CLI.
     */
    private function checkTypeParam(Request $request)
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
     * @param Request $request
     * @return View
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

    /**
     * @return View
     */
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

    /**
     * @return View
     */
    private function indexPayee(): View
    {
        // Show all payees of user from the database and return to view
        $payees = DB::select(
            "SELECT
                base_data.id,
                name,
                active,
                alias,
                transactions_from_count + transactions_to_count AS transactions_count,
                IF(
                    transactions_from_min_date IS NULL OR transactions_to_min_date IS NULL,
                    COALESCE(transactions_from_min_date, transactions_to_min_date),
                    LEAST(transactions_from_min_date, transactions_to_min_date)
                ) AS transactions_min_date,
                IF(
                    transactions_from_max_date IS NULL OR transactions_to_max_date IS NULL,
                    COALESCE(transactions_from_max_date, transactions_to_max_date),
                    GREATEST(transactions_from_max_date, transactions_to_max_date)
                ) AS transactions_max_date,
                payees.category_id

            FROM (
                select
                    `account_entities`.*,
                    (
                        select
                            count(*)
                        from
                            `transaction_details_standard`
                        where
                            `account_entities`.`id` = `transaction_details_standard`.`account_from_id`
                            and exists (
                            select
                                *
                            from
                                `transactions`
                            where
                                `transaction_details_standard`.`id` = `transactions`.`config_id`
                                and `transactions`.`config_type` = 'transaction_detail_standard'
                                and `schedule` = 0
                                and `budget` = 0)
                    ) as `transactions_from_count`,
                    (
                        select
                            count(*)
                        from
                            `transaction_details_standard`
                        where
                            `account_entities`.`id` = `transaction_details_standard`.`account_to_id`
                            and exists (
                            select
                                *
                            from
                                `transactions`
                            where
                                `transaction_details_standard`.`id` = `transactions`.`config_id`
                                and `transactions`.`config_type` = 'transaction_detail_standard'
                                and `schedule` = 0
                                and `budget` = 0)
                    ) as `transactions_to_count`,
                    (
                        select
                            min(`transactions`.`date`)
                        from
                            `transactions`
                        inner join `transaction_details_standard` on
                            `transaction_details_standard`.`id` = `transactions`.`config_id`
                        where
                            `account_entities`.`id` = `transaction_details_standard`.`account_from_id`
                            and ((`transactions`.`config_type` = 'transaction_detail_standard'
                                and exists (
                                select
                                    *
                                from
                                    `transaction_details_standard`
                                where
                                    `transactions`.`config_id` = `transaction_details_standard`.`id`
                                    and `schedule` = 0
                                    and `budget` = 0)))
                    ) as `transactions_from_min_date`,
                    (
                        select
                            min(`transactions`.`date`)
                        from
                            `transactions`
                        inner join `transaction_details_standard` on
                            `transaction_details_standard`.`id` = `transactions`.`config_id`
                        where
                            `account_entities`.`id` = `transaction_details_standard`.`account_to_id`
                            and ((`transactions`.`config_type` = 'transaction_detail_standard'
                                and exists (
                                select
                                    *
                                from
                                    `transaction_details_standard`
                                where
                                    `transactions`.`config_id` = `transaction_details_standard`.`id`
                                    and `schedule` = 0
                                    and `budget` = 0)))
                    ) as `transactions_to_min_date`,
                    (
                        select
                            max(`transactions`.`date`)
                        from
                            `transactions`
                        inner join `transaction_details_standard` on
                            `transaction_details_standard`.`id` = `transactions`.`config_id`
                        where
                            `account_entities`.`id` = `transaction_details_standard`.`account_from_id`
                            and ((`transactions`.`config_type` = 'transaction_detail_standard'
                                and exists (
                                select
                                    *
                                from
                                    `transaction_details_standard`
                                where
                                    `transactions`.`config_id` = `transaction_details_standard`.`id`
                                    and `schedule` = 0
                                    and `budget` = 0)))
                    ) as `transactions_from_max_date`,
                    (
                        select
                            max(`transactions`.`date`)
                        from
                            `transactions`
                        inner join `transaction_details_standard` on
                            `transaction_details_standard`.`id` = `transactions`.`config_id`
                        where
                            `account_entities`.`id` = `transaction_details_standard`.`account_to_id`
                            and ((`transactions`.`config_type` = 'transaction_detail_standard'
                                and exists (
                                select
                                    *
                                from
                                    `transaction_details_standard`
                                where
                                    `transactions`.`config_id` = `transaction_details_standard`.`id`
                                    and `schedule` = 0
                                    and `budget` = 0)))
                    ) as `transactions_to_max_date`
                    from
                        `account_entities`
                    where
                        `account_entities`.`user_id` = ?
                        and `config_type` = 'payee'
                ) AS base_data

                LEFT JOIN payees ON base_data.config_id = payees.id",
            [Auth::user()->id]
        );

        // Get categories to display name
        $categories = Category::with(['parent'])->get();

        // Load additional data and make further calculations
        array_map(function ($payee) use ($categories) {
            // Get full category name
            if ($payee->category_id === null) {
                $payee->category_full_name = '';
            } else {
                $payee->category_full_name = $categories->find($payee->category_id)->full_name;
            }

            return $payee;
        }, $payees);

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
        // Get all account groups
        $allAccountGroups = Auth::user()
            ->accountGroups()
            ->select('name', 'id')
            ->orderBy('name')
            ->get()
            ->pluck('name', 'id');

        // Redirect to account group form, if empty
        if (count($allAccountGroups) === 0) {
            $this->addMessage(
                'Before creating an account, please add at least one account group. E.g. cash, bank accounts, savings, etc. Account groups help to organize your accounts.',
                'info',
                'No account groups found',
                'info-circle'
            );

            return redirect()->route('account-group.create');
        }

        // Get all currencies
        $allCurrencies = Auth::user()->currencies()->pluck('name', 'id')->all();

        // Redirect to currency form, if empty
        if (count($allCurrencies) === 0) {
            $this->addMessage(
                'Before creating an account, please add at least one currency. Accounts must have a currency assigned.',
                'info',
                'No currencies found',
                'info-circle'
            );

            return redirect()->route('currencies.create');
        }

        return view('account.form', ['allAccountGroups' => $allAccountGroups, 'allCurrencies' => $allCurrencies]);
    }

    /**
     * @return View
     */
    private function createPayee(): View
    {
        JavaScriptFacade::put([
            'categoryPreferences' => [],
        ]);

        return view('payee.form');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param AccountEntityRequest $request
     * @return RedirectResponse
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
        $accountEntity->user_id = Auth::user()->id;

        if ($validated['config_type'] === 'account') {
            $accountConfig = Account::create($validated['config']);
            $accountEntity->config()->associate($accountConfig);

            $accountEntity->push();

            self::addSimpleSuccessMessage(__('Account added'));

            return redirect()->route('account-entity.index', ['type' => 'account']);
        }

        if ($validated['config_type'] === 'payee') {
            $payeeConfig = Payee::create($validated['config']);
            $accountEntity->config()->associate($payeeConfig);

            // Sync category preference. First, create a variable. Set preferred categories to boolean true and not preferred categories to boolean false.
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

            return redirect()->route('account-entity.index', ['type' => 'payee']);
        }

        // This redirect is theoretically not used
        return redirect()->back();
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param Request $request
     * @param AccountEntity $accountEntity
     * @return View
     * @uses editPayee
     * @uses editAccount
     */
    public function edit(Request $request, AccountEntity $accountEntity): View
    {
        /**
         * @get('/account-entity/{account_entity}/edit')
         * @name('account-entity.edit')
         * @middlewares('web', 'auth', 'verified', 'can:update,account_entity')
         */
        $this->checkTypeParam($request);

        return $this->{'edit' . Str::ucfirst($request->type)}($accountEntity);
    }

    private function editAccount(AccountEntity $accountEntity): View
    {
        $accountEntity->load(['config', 'config.accountGroup', 'config.currency']);

        // Get all account groups
        $allAccountGroups = Auth::user()->accountGroups()->pluck('name', 'id')->all();

        // Get all currencies
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
        $categoryPreference = $accountEntity->categoryPreference->map(function ($item) {
            return [
                'id' => $item->id,
                'full_name' => $item->full_name,
                'preferred' => $item->pivot->preferred,
            ];
        });
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
     *
     * @param AccountEntityRequest $request
     * @param AccountEntity $accountEntity
     * @return RedirectResponse
     */
    public function update(AccountEntityRequest $request, AccountEntity $accountEntity): RedirectResponse
    {
        /**
         * @methods('PUT', PATCH')
         * @uri('/account-entity/{account_entity}')
         * @name('account-entity.update')
         * @middlewares('web', 'auth', 'verified', 'can:update,account_entity')
         */
        $this->checkTypeParam($request);

        $validated = $request->validated();

        if ($validated['config_type'] === 'account') {
            $accountEntity->load(['config']);

            $accountEntity->fill($validated);
            $accountEntity->config->fill($validated['config']);

            $accountEntity->push();

            self::addSimpleSuccessMessage(__('Account updated'));

            return redirect()->route('account-entity.index', ['type' => 'account']);
        }

        if ($validated['config_type'] === 'payee') {
            $accountEntity->load(['config']);

            $accountEntity->fill($validated);
            $accountEntity->config->fill($validated['config']);

            // Sync category preference. First, create a variable. Set preferred categories to boolean true and not preferred categories to boolean false.
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

            return redirect()->route('account-entity.index', ['type' => 'payee']);
        }

        // This redirect is theoretically not used
        return redirect()->back();
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param Request $request
     * @param AccountEntity $accountEntity
     * @return RedirectResponse
     */
    public function destroy(Request $request, AccountEntity $accountEntity): RedirectResponse
    {
        /**
         * @delete('/account-entity/{account_entity}')
         * @name('account-entity.destroy')
         * @middlewares('web', 'auth', 'verified', 'can:delete,account_entity')
         */
        $this->checkTypeParam($request);
        $result = $this->accountEntityService->delete($accountEntity);

        if ($result['success']) {
            self::addSimpleSuccessMessage(
                __(':type deleted', ['type' => Str::ucfirst($accountEntity->config_type)])
            );

            return redirect()->route('account-entity.index', ['type' => $accountEntity->config_type]);
        }

        self::addSimpleDangerMessage($result['error']);
        return redirect()->back();
    }

    /**
     * Display a form to merge two payees.
     *
     * @param AccountEntity|null $payeeSource
     * @return View
     */
    public function mergePayeesForm(?AccountEntity $payeeSource)
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
    public function mergePayees(MergePayeesRequest $request)
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
            self::addSimpleDangerMessage(__('Database error:') . ' ' . $e->getMessage());
        }

        return redirect()->route('account-entity.index', ['type' => 'payee']);
    }
}
