<?php

namespace App\Http\Controllers;

use App\Account;
use App\AccountEntity;
use App\AccountGroup;
use App\Currency;
use App\Http\Requests\AccountEntityRequest;
use Illuminate\Http\Request;
use JavaScript;
//use Barryvdh\Debugbar\Facade as Debugbar;

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
            'editUrl' => route('accounts.edit', '#ID#'),
            'deleteUrl' => action('AccountController@destroy', '#ID#'),
        ]);

        return view('accounts.index');
    }

    public function edit($id)
    {
        $account = AccountEntity::with(['config', 'config.account_group', 'config.currency'])
            ->find($id);

        //get all account groups
        $allAccountGroups = AccountGroup::pluck('name', 'id')->all();

        //get all currencies
        $allCurrencies = Currency::pluck('name', 'id')->all();

        return view('accounts.form',
            [
                'account'=> $account,
                'allAccountGroups' => $allAccountGroups,
                'allCurrencies' => $allCurrencies
        ]);
    }

    public function update(AccountEntityRequest $request, AccountEntity $account)
    {
        // Retrieve the validated input data
        $validated = $request->validated();
        $account->load(['config']);

        $account->fill($validated);
        $account->config->fill($validated['config']);

        $account->push();

        add_notification('Account updated', 'success');

        return redirect()->route('accounts.index');
    }

    public function create()
    {

        //get all account groups
        $allAccountGroups = AccountGroup::pluck('name', 'id')->all();

        //get all currencies
        $allCurrencies = Currency::pluck('name', 'id')->all();

        return view('accounts.form', ['allAccountGroups' => $allAccountGroups, 'allCurrencies' => $allCurrencies]);
    }

    public function store(AccountEntityRequest $request)
    {

        $validated = $request->validated();

        $account = new AccountEntity($validated);

        $accountConfig = Account::create($validated['config']);
        $account->config()->associate($accountConfig);

        $account->push();

        add_notification('Account added', 'success');

        return redirect()->route('accounts.index');
    }

    public function show(AccountEntity $account) {
        $account->load('config');
        return view('accounts.show', compact('account'));
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
        $account = AccountEntity::find($id);
        //delete
        $account->delete();

        add_notification('Account deleted', 'success');

        return redirect()->route('accounts.index');
    }
}
