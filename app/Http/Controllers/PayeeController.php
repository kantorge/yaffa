<?php

namespace App\Http\Controllers;

use App\AccountEntity;
use App\Category;
use App\Http\Requests\AccountEntityRequest;
use App\Payee;
use Illuminate\Http\Request;
use JavaScript;

class PayeeController extends Controller
{

    protected $payee;

    public function __construct(AccountEntity $payee)
    {
        $this->payee = $payee->where('config_type', 'payee');
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //Show all payees from the database and return to view
        $payees = $this->payee
            ->with(['config', 'config.categories'])
            ->get();

        //pass data for DataTables
        JavaScript::put([
            'payees' => $payees,
            'editUrl' => route('payees.edit', '#ID#'),
            'deleteUrl' => action('PayeeController@destroy', '#ID#'),
        ]);

        return view('payees.index');
    }

    public function edit($id)
    {
        $payee = AccountEntity::with(['config', 'config.categories'])
            ->find($id);

        //get all categories
        //TODO: ezt érdemes AJAX-ból hívni?
        $categories = Category::all();
        $categories->sortBy('full_name');

        return view('payees.form',
                    [
                        'payee' => $payee,
                        'categories' => $categories->pluck('full_name','id')
                    ]
                );
    }

    public function update(AccountEntityRequest $request, AccountEntity $payee)
    {
        // Retrieve the validated input data
        $validated = $request->validated();
        $payee->load(['config']);

        $payee->fill($validated);
        $payee->config->fill($validated['config']);

        $payee->push();

        add_notification('Payee updated', 'success');

        return redirect()->route('payees.index');
    }

    public function create()
    {

        //get all categories
        $categories = Category::all();
        $categories->sortBy('full_name');

        return view('payees.form',
                    [
                        'categories' => $categories->pluck('full_name','id')
                    ]
                );
    }

    public function store(AccountEntityRequest $request)
    {

        $validated = $request->validated();

        $payee = new AccountEntity($validated);

        $payeeConfig = Payee::create($validated['config']);
        $payee->config()->associate($payeeConfig);

        $payee->push();

        add_notification('Payee added', 'success');

        return redirect()->route('payees.index');
    }

    public function show(AccountEntity $account) {
        $account->load('config');
        //dd($account);
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
        $payee = AccountEntity::find($id);
        //delete
        $payee->delete();

        add_notification('Payee deleted', 'success');

        return redirect()->route('payees.index');
    }
}
