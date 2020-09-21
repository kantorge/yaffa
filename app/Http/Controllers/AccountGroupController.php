<?php

namespace App\Http\Controllers;

use App\AccountGroup;
use App\Http\Requests\AccountGroupRequest;
use Illuminate\Http\Request;

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
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //Show all account groups from the database and return to view
        $accountGroups = $this->accountGroup->all();

        //support DataTables with action URLs
        $accountGroups->map(function ($accountGroup) {
            $accountGroup['edit_url'] = route('accountgroups.edit', $accountGroup);
            $accountGroup['delete_url'] = action('AccountGroupController@destroy', $accountGroup);
            return $accountGroup;
        });

        return view('accountgroups.index',['accountGroups'=>$accountGroups]);
    }

    public function create()
    {
        return view('accountgroups.form');
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $accountGroup = AccountGroup::find($id);

        return view('accountgroups.form',['accountGroup'=> $accountGroup]);
    }

    public function store(AccountGroupRequest $request)
    {

        $validated = $request->validated();

        $accountGroup = New AccountGroup();
        $accountGroup->fill($validated);
        $accountGroup->save();

        add_notification('Account group added', 'success');

        return redirect()->route('accountgroups.index');
    }

    public function update(AccountGroupRequest $request)
    {
        // Retrieve the validated input data
        $validated = $request->validated();

        $accountGroup = AccountGroup::find($request->input('id'));
        $accountGroup->fill($validated);
        $accountGroup->save();

        add_notification('Account group updated', 'success');

        return redirect()->route('accountgroups.index');
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
        $accountGroup = AccountGroup::find($id);
        //delete
        try {
            $accountGroup->delete();
            add_notification('Account group deleted', 'success');
            return redirect()->route('accountgroups.index');
        } catch(\Illuminate\Database\QueryException $e) {
            if ($e->errorInfo[1] == 1451) {
                add_notification('Account group is in use, cannot be deleted', 'danger');
            } else {
                add_notification('Database error: ' . $e->errorInfo[2], 'danger');
            }
            return redirect()->back();
        }
    }
}
