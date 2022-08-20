<?php

namespace App\Http\Controllers;

use App\Http\Requests\InvestmentGroupRequest;
use App\Models\InvestmentGroup;
use Illuminate\Support\Facades\Auth;
use JavaScript;

class InvestmentGroupController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->authorizeResource(InvestmentGroup::class);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        // Get all investment groups of the user from the database and return to view
        $investmentGroups = Auth::user()
            ->investmentGroups()
            ->select('id', 'name')
            ->get();

        // Pass data for DataTables
        JavaScript::put([
            'investmentGroups' => $investmentGroups,
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
     * @param  InvestmentGroup  $investmentGroup
     * @return \Illuminate\View\View
     */
    public function edit(InvestmentGroup $investmentGroup)
    {
        return view('investment-group.form', ['investmentGroup' => $investmentGroup]);
    }

    public function store(InvestmentGroupRequest $request)
    {
        $validated = $request->validated();

        $investmentGroup = InvestmentGroup::make($validated);
        $investmentGroup->user_id = Auth::user()->id;
        $investmentGroup->save();

        self::addSimpleSuccessMessage('Investment group added');

        return redirect()->route('investment-group.index');
    }

    public function update(InvestmentGroupRequest $request, InvestmentGroup $investmentGroup)
    {
        $validated = $request->validated();

        $investmentGroup
            ->fill($validated)
            ->save();

        self::addSimpleSuccessMessage('Investment group updated');

        return redirect()->route('investment-group.index');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  InvestmentGroup  $investmentGroup
     * @return \Illuminate\Http\Response
     */
    public function destroy(InvestmentGroup $investmentGroup)
    {
        try {
            $investmentGroup->delete();
            self::addSimpleSuccessMessage('Investment group deleted');

            return redirect()->route('investment-group.index');
        } catch (\Illuminate\Database\QueryException $e) {
            if ($e->errorInfo[1] == 1451) {
                self::addSimpleDangerMessage('Investment group is in use, cannot be deleted');
            } else {
                self::addSimpleDangerMessage('Database error: '.$e->errorInfo[2]);
            }

            return redirect()->back();
        }
    }
}
