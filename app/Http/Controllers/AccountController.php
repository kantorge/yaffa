<?php

namespace App\Http\Controllers;

use App\Http\Requests\AccountEntityRequest;
use App\Models\Account;
use App\Models\AccountEntity;
use App\Models\AccountGroup;
use App\Models\Currency;
use JavaScript;

class AccountController extends Controller
{
    protected $account;

    public function __construct(AccountEntity $account)
    {
        $this->account = $account->where('config_type', 'account');
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //Show all accounts from the database and return to view
        $accounts = $this->account
            ->with(['config', 'config.account_group', 'config.currency'])
            ->get();

        //pass data for DataTables
        JavaScript::put([
            'accounts' => $accounts,
        ]);

        return view('account.index');
    }

    public function edit($id)
    {
        $accountEntity = AccountEntity::with(['config', 'config.account_group', 'config.currency'])
            ->find($id);

        // Get all account groups
        $allAccountGroups = AccountGroup::pluck('name', 'id')->all();

        // Get all currencies
        $allCurrencies = Currency::pluck('name', 'id')->all();

        return view(
            'account.form',
            [
                'account'=> $accountEntity,
                'allAccountGroups' => $allAccountGroups,
                'allCurrencies' => $allCurrencies
            ]
        );
    }

    public function update(AccountEntityRequest $request, AccountEntity $account)
    {
        // Retrieve the validated input data
        $validated = $request->validated();
        $account->load(['config']);

        $account->fill($validated);
        $account->config->fill($validated['config']);

        $account->push();

        self::addSimpleSuccessMessage('Account updated');

        return redirect()->route('account.index');
    }

    public function create()
    {
        //get all account groups
        $allAccountGroups = AccountGroup::pluck('name', 'id')->all();

        //get all currencies
        $allCurrencies = Currency::pluck('name', 'id')->all();

        return view('account.form', ['allAccountGroups' => $allAccountGroups, 'allCurrencies' => $allCurrencies]);
    }

    public function store(AccountEntityRequest $request)
    {
        $validated = $request->validated();

        $accountEntity = new AccountEntity($validated);

        $accountConfig = Account::create($validated['config']);
        $accountEntity->config()->associate($accountConfig);

        $accountEntity->push();

        self::addSimpleSuccessMessage('Account added');

        return redirect()->route('account.index');
    }

    public function show(AccountEntity $account)
    {
        $account->load('config');
        return view('account.show', compact('account'));
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //Retrieve item
        $accountEntity = AccountEntity::find($id);

        $accountEntity->delete();

        self::addSimpleSuccessMessage('Account deleted');

        return redirect()->route('account.index');
    }
}
