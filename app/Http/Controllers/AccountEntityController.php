<?php

namespace App\Http\Controllers;

use App\Http\Requests\AccountEntityRequest;
use App\Models\Account;
use App\Models\AccountEntity;
use App\Models\Category;
use App\Models\Currency;
use App\Models\Payee;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use JavaScript;

class AccountEntityController extends Controller
{
    public function __construct(Request $request)
    {
        $this->middleware('auth');
        $this->authorizeResource(AccountEntity::class);

        // Check if known type is requested
        if (! app()->runningInConsole() && (! $request->has('type') || ! in_array($request->type, ['account', 'payee']))) {
            abort(Response::HTTP_NOT_FOUND);
        }
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        return $this->{'index'.Str::ucfirst($request->type)}();
    }

    public function indexAccount()
    {
        // Show all accounts of user from the database and return to view
        $accounts = Auth::user()
            ->accounts()
            ->with(['config', 'config.account_group', 'config.currency'])
            ->get();

        // Pass data for DataTables
        JavaScript::put([
            'accounts' => $accounts,
        ]);

        return view('account.index');
    }

    public function indexPayee()
    {
        // Show all payees of user from the database and return to view
        $payees = Auth::user()
            ->payees()
            ->with(['config'])
            ->get();

        // Get categories to display name
        $categories = Category::with(['parent'])->get();

        // Load additional data
        $payees->map(function ($payee) use ($categories) {
            // Full category name
            if (is_null($payee->config->category_id)) {
                $payee['config']['category_full_name'] = '';
            } else {
                $payee['config']['category_full_name'] = $categories->find($payee->config->category_id)->full_name;
            }

            return $payee;
        });

        // Pass data for DataTables
        JavaScript::put([
            'payees' => $payees,
        ]);

        return view('payee.index');
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Request $request)
    {
        return $this->{'create'.Str::ucfirst($request->type)}();
    }

    public function createAccount()
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
        $allCurrencies = Currency::pluck('name', 'id')->all();

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

    public function createPayee()
    {
        return view('payee.form');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(AccountEntityRequest $request)
    {
        $validated = $request->validated();

        $accountEntity = new AccountEntity($validated);
        $accountEntity->user_id = Auth::user()->id;

        if ($validated['config_type'] === 'account') {
            $accountConfig = Account::create($validated['config']);
            $accountEntity->config()->associate($accountConfig);

            $accountEntity->push();

            self::addSimpleSuccessMessage('Account added');

            return redirect()->route('account-entity.index', ['type' => 'account']);
        }

        if ($validated['config_type'] === 'payee') {
            $payeeConfig = Payee::create($validated['config']);
            $accountEntity->config()->associate($payeeConfig);

            $accountEntity->push();

            self::addSimpleSuccessMessage('Payee added');

            return redirect()->route('account-entity.index', ['type' => 'payee']);
        }

        // TODO: should the above two conditional parts be unified with dynamic model handling
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\AccountEntity  $accountEntity
     * @return \Illuminate\Http\Response
     */
    public function edit(Request $request, AccountEntity $accountEntity)
    {
        return $this->{'edit'.Str::ucfirst($request->type)}($accountEntity);
    }

    public function editAccount(AccountEntity $accountEntity)
    {
        $accountEntity->load(['config', 'config.account_group', 'config.currency']);

        // Get all account groups
        $allAccountGroups = Auth::user()->accountGroups()->pluck('name', 'id')->all();

        // Get all currencies
        $allCurrencies = Auth::user()->currencies()->pluck('name', 'id')->all();

        return view(
            'account.form',
            [
                'account'=> $accountEntity,
                'allAccountGroups' => $allAccountGroups,
                'allCurrencies' => $allCurrencies,
            ]
        );
    }

    public function editPayee(AccountEntity $accountEntity)
    {
        $accountEntity->load(['config']);

        return view(
            'payee.form',
            [
                'payee'=> $accountEntity,
            ]
        );
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\AccountEntity  $accountEntity
     * @return \Illuminate\Http\Response
     */
    public function update(AccountEntityRequest $request, AccountEntity $accountEntity)
    {
        $validated = $request->validated();

        if ($validated['config_type'] === 'account') {
            $accountEntity->load(['config']);

            $accountEntity->fill($validated);
            $accountEntity->config->fill($validated['config']);

            $accountEntity->push();

            self::addSimpleSuccessMessage('Account updated');

            return redirect()->route('account-entity.index', ['type' => 'account']);
        }

        if ($validated['config_type'] === 'payee') {
            $accountEntity->load(['config']);

            $accountEntity->fill($validated);
            $accountEntity->config->fill($validated['config']);

            $accountEntity->push();

            self::addSimpleSuccessMessage('Payee updated');

            return redirect()->route('account-entity.index', ['type' => 'payee']);
        }

        // TODO: should the above two conditional parts be unified with dynamic model handling
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\AccountEntity  $accountEntity
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $request, AccountEntity $accountEntity)
    {
        try {
            $accountEntity->delete();

            self::addSimpleSuccessMessage(
                __(':type deleted', ['type' => Str::ucfirst($request->type)])
            );

            return redirect()->route('account-entity.index', ['type' => $request->type]);
        } catch (\Illuminate\Database\QueryException $e) {
            if ($e->errorInfo[1] == 1451) {
                self::addSimpleDangerMessage(
                    __(':type is in use, cannot be deleted', ['type' => Str::ucfirst($request->type)])
                );
            } else {
                self::addSimpleDangerMessage('Database error: '.$e->errorInfo[2]);
            }

            return redirect()->back();
        }
    }
}
