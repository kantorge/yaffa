<?php

namespace App\Http\Controllers;

use App\Models\AccountGroup;
use App\Http\Requests\AccountGroupRequest;
use JavaScript;

class AccountGroupController extends Controller
{

    protected $accountGroup;

    public function __construct(AccountGroup $accountGroup)
    {
        $this->accountGroup = $accountGroup;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        //Show all account groups from the database and return to view
        $accountGroups = $this->accountGroup->all();

        //pass data for DataTables
        JavaScript::put([
            'accountGroups' => $accountGroups,
            'editUrl' => route('account-group.edit', '#ID#'),
            'deleteUrl' => route('account-group.destroy', '#ID#'),
        ]);

        return view('account-group.index');
    }

    public function create()
    {
        return view('account-group.form');
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param AccountGroup $accountGroup
     * @return \Illuminate\View\View
     */
    public function edit(AccountGroup $accountGroup)
    {
        return view('account-group.form', ['accountGroup'=> $accountGroup]);
    }

    public function store(AccountGroupRequest $request)
    {
        $validated = $request->validated();

        AccountGroup::create($validated);

        self::addSimpleSuccessMessage('Account group added');

        return redirect()->route('account-group.index');
    }

    public function update(AccountGroupRequest $request)
    {
        // Retrieve the validated input data
        $validated = $request->validated();

        AccountGroup::find($request->input('id'))
            ->fill($validated)
            ->save();

        self::addSimpleSuccessMessage('Account group updated');

        return redirect()->route('account-group.index');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  AccountGroup  $accountGroup
     * @return \Illuminate\Http\Response
     */
    public function destroy(AccountGroup $accountGroup)
    {
        try {
            $accountGroup->delete();
            self::addSimpleSuccessMessage('Account group deleted');
            return redirect()->route('account-group.index');
        } catch(\Illuminate\Database\QueryException $e) {
            if ($e->errorInfo[1] == 1451) {
                self::addSimpleDangerMessage('Account group is in use, cannot be deleted');
            } else {
                self::addSimpleDangerMessage('Database error: ' . $e->errorInfo[2]);
            }
            return redirect()->back();
        }
    }
}
