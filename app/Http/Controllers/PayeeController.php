<?php

namespace App\Http\Controllers;

use App\Http\Requests\AccountEntityRequest;
use App\Models\AccountEntity;
use App\Models\Category;
use App\Models\Payee;
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
        // Get all payees
        $payees = $this->payee
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

            // TODO: First date of usage in transactions
            //$payee['config']['firstTransactionDate'] = $payee->config->firstTransactionDate;

            return $payee;
        });

        // Pass data for DataTables
        JavaScript::put([
            'payees' => $payees,
        ]);

        return view('payees.index');
    }

    public function edit(AccountEntity $payee)
    {
        $payee->load(['config']);

        // Get all categories
        //TODO: ezt érdemes AJAX-ból hívni?
        $categories = Category::all()->sortBy('full_name');

        return view(
            'payees.form',
            [
                'payee' => $payee,
                'categories' => $categories->pluck('full_name', 'id')
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
        // Get all categories
        $categories = Category::all()->sortBy('full_name');

        return view(
            'payees.form',
            [
                'categories' => $categories->pluck('full_name', 'id')
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

    public function show(AccountEntity $payee)
    {
        $payee->load('config');
        return view('account.show', compact('payee'));
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(AccountEntity $payee)
    {
        try {
            $payee->delete();
            self::addSimpleSuccessMessage('Payee deleted');
            return redirect()->route('payees.index');
        } catch (\Illuminate\Database\QueryException $e) {
            if ($e->errorInfo[1] == 1451) {
                self::addSimpleDangerMessage('Payee is in use, cannot be deleted');
            } else {
                self::addSimpleDangerMessage('Database error: ' . $e->errorInfo[2]);
            }
            return redirect()->back();
        }
    }
}
