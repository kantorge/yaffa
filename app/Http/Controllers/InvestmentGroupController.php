<?php

namespace App\Http\Controllers;

use App\Models\InvestmentGroup;
use App\Http\Requests\InvestmentGroupRequest;
use JavaScript;

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
     * @return \Illuminate\View\View
     */
    public function index()
    {
        //Show all investment groups from the database and return to view
        $investmentGroups = $this->investmentGroup->all();

        //pass data for DataTables
        JavaScript::put([
            'investmentGroups' => $investmentGroups,
            'editUrl' => route('investment-group.edit', '#ID#'),
            'deleteUrl' => route('investment-group.destroy', '#ID#'),
        ]);

        return view('investment-group.index');
    }

    public function create()
    {
        return view('investment-group.form');
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param InvestmentGroup $investmentGroup
     * @return \Illuminate\View\View
     */
    public function edit(InvestmentGroup $investmentGroup)
    {
        return view('investment-group.form', ['investmentGroup'=> $investmentGroup]);
    }

    public function store(InvestmentGroupRequest $request)
    {
        $validated = $request->validated();

        InvestmentGroup::create($validated);

        self::addSimpleSuccessMessage('Investment group added');

        return redirect()->route('investment-group.index');
    }

    public function update(InvestmentGroupRequest $request)
    {
        // Retrieve the validated input data
        $validated = $request->validated();

        InvestmentGroup::find($request->input('id'))
            ->fill($validated)
            ->save();

        self::addSimpleSuccessMessage('Investment group updated');

        return redirect()->route('investment-group.index');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param InvestmentGroup $investmentGroup
     * @return \Illuminate\Http\Response
     */
    public function destroy( InvestmentGroup $investmentGroup)
    {
        try {
            $investmentGroup->delete();
            self::addSimpleSuccessMessage('Investment group deleted');
            return redirect()->route('investment-group.index');
        } catch(\Illuminate\Database\QueryException $e) {
            if ($e->errorInfo[1] == 1451) {
                self::addSimpleDangerMessage('Investment group is in use, cannot be deleted');
            } else {
                self::addSimpleDangerMessage('Database error: ' . $e->errorInfo[2]);
            }
            return redirect()->back();
        }
    }
}
