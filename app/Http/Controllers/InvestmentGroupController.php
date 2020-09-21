<?php

namespace App\Http\Controllers;

use App\InvestmentGroup;
use App\Http\Requests\InvestmentGroupRequest;
use Illuminate\Http\Request;

class InvestmentGroupController extends Controller
{

    protected $investmentGroup;

    public function __construct(InvestmentGroup $investmentGroup)
    {
        $this->investmentGroup = $investmentGroup;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //Show all account groups from the database and return to view
        $investmentGroups = $this->investmentGroup->all();

        //support DataTables with action URLs
        $investmentGroups->map(function ($investmentGroup) {
            $investmentGroup['edit_url'] = route('investmentgroups.edit', $investmentGroup);
            $investmentGroup['delete_url'] = action('InvestmentGroupController@destroy', $investmentGroup);
            return $investmentGroup;
        });

        return view('investmentgroups.index',['investmentGroups'=>$investmentGroups]);
    }

    public function create()
    {
        return view('investmentgroups.form');
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $investmentGroup = InvestmentGroup::find($id);

        return view('investmentgroups.form',['investmentGroup'=> $investmentGroup]);
    }

    public function store(InvestmentGroupRequest $request)
    {

        $validated = $request->validated();

        $investmentGroup = New InvestmentGroup();
        $investmentGroup->fill($validated);
        $investmentGroup->save();

        add_notification('Investment group added', 'success');

        return redirect()->route('investmentgroups.index');
    }

    public function update(InvestmentGroupRequest $request)
    {
        // Retrieve the validated input data
        $validated = $request->validated();

        $investmentGroup = InvestmentGroup::find($request->input('id'));
        $investmentGroup->fill($validated);
        $investmentGroup->save();

        add_notification('Investment group updated', 'success');

        return redirect()->route('investmentgroups.index');
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
        $investmentGroup = InvestmentGroup::find($id);
        //delete
        try {
            $investmentGroup->delete();
            add_notification('Investment group deleted', 'success');
            return redirect()->route('investmentgroups.index');
        } catch(\Illuminate\Database\QueryException $e) {
            if ($e->errorInfo[1] == 1451) {
                add_notification('Investment group is in use, cannot be deleted', 'danger');
            } else {
                add_notification('Database error: ' . $e->errorInfo[2], 'danger');
            }
            return redirect()->back();
        }
    }
}
