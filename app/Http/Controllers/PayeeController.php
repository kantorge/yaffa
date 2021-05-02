<?php

namespace App\Http\Controllers;

use App\Models\AccountEntity;
use App\Models\Category;
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
            ->with(['config'])
            ->get();

        //get categories to display name
        $categories = Category::with(['parent'])->get();

        $payees->map(function($payee) use ($categories) {
            if (is_null($payee->config->category_id)) {
                $payee['config']['category_full_name'] = '';
            } else {
                $payee['config']['category_full_name'] = $categories->find($payee->config->category_id)->full_name;
            }
            return $payee;
        });

        //pass data for DataTables
        JavaScript::put([
            'payees' => $payees,
            'editUrl' => route('payees.edit', '#ID#'),
            'deleteUrl' => route('payees.destroy', '#ID#'),
        ]);

        return view('payees.index');
    }

    public function edit($id)
    {
        $payee = AccountEntity::with(['config'])
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

        self::addSimpleSuccessMessage('Payee updated');

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

        self::addSimpleSuccessMessage('Payee added');

        return redirect()->route('payees.index');
    }

    public function show(AccountEntity $account) {
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
        $payee = AccountEntity::find($id);

        //delete
        $payee->delete();

        self::addSimpleSuccessMessage('Payee deleted');

        return redirect()->route('payees.index');
    }
}
