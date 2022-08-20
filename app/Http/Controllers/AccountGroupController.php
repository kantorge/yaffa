<?php

namespace App\Http\Controllers;

use App\Http\Requests\AccountGroupRequest;
use App\Models\AccountGroup;
use Illuminate\Support\Facades\Auth;
use JavaScript;

class AccountGroupController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->authorizeResource(AccountGroup::class);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        // Get all account groups of the user from the database and return to view
        $accountGroups = Auth::user()
            ->accountGroups()
            ->select('id', 'name')
            ->get();

        // Pass data for DataTables
        JavaScript::put([
            'accountGroups' => $accountGroups,
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
     * @param  AccountGroup  $accountGroup
     * @return \Illuminate\View\View
     */
    public function edit(AccountGroup $accountGroup)
    {
        return view('account-group.form', ['accountGroup' => $accountGroup]);
    }

    public function store(AccountGroupRequest $request)
    {
        $validated = $request->validated();

        $accountGroup = AccountGroup::make($validated);
        $accountGroup->user_id = Auth::user()->id;
        $accountGroup->save();

        self::addSimpleSuccessMessage('Account group added');

        return redirect()->route('account-group.index');
    }

    public function update(AccountGroupRequest $request, AccountGroup $accountGroup)
    {
        $validated = $request->validated();

        $accountGroup->fill($validated)
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
        } catch (\Illuminate\Database\QueryException $e) {
            if ($e->errorInfo[1] == 1451) {
                self::addSimpleDangerMessage('Account group is in use, cannot be deleted');
            } else {
                self::addSimpleDangerMessage('Database error: '.$e->errorInfo[2]);
            }

            return redirect()->back();
        }
    }
}
