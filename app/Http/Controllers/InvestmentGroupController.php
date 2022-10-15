<?php

namespace App\Http\Controllers;

use App\Http\Requests\InvestmentGroupRequest;
use App\Models\InvestmentGroup;
use Illuminate\Support\Facades\Auth;
use Laracasts\Utilities\JavaScript\JavaScriptFacade;

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
        /**
         * @get('/investment-group')
         * @name('investment-group.index')
         * @middlewares('web', 'auth', 'can:viewAny,App\Models\InvestmentGroup')
         */
        // Get all investment groups of the user from the database and return to view
        $investmentGroups = Auth::user()
            ->investmentGroups()
            ->select('id', 'name')
            ->get();

        // Pass data for DataTables
        JavaScriptFacade::put([
            'investmentGroups' => $investmentGroups,
        ]);

        return view('investment-group.index');
    }

    public function create()
    {
        /**
         * @get('/investment-group/create')
         * @name('investment-group.create')
         * @middlewares('web', 'auth', 'can:create,App\Models\InvestmentGroup')
         */
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
        /**
         * @get('/investment-group/{investment_group}/edit')
         * @name('investment-group.edit')
         * @middlewares('web', 'auth', 'can:update,investment_group')
         */
        return view('investment-group.form', ['investmentGroup' => $investmentGroup]);
    }

    public function store(InvestmentGroupRequest $request)
    {
        /**
         * @post('/investment-group')
         * @name('investment-group.store')
         * @middlewares('web', 'auth', 'can:create,App\Models\InvestmentGroup')
         */
        $validated = $request->validated();

        $investmentGroup = InvestmentGroup::make($validated);
        $investmentGroup->user_id = Auth::user()->id;
        $investmentGroup->save();

        self::addSimpleSuccessMessage(__('Investment group added'));

        return redirect()->route('investment-group.index');
    }

    public function update(InvestmentGroupRequest $request, InvestmentGroup $investmentGroup)
    {
        /**
         * @methods('PUT', PATCH')
         * @uri('/investment-group/{investment_group}')
         * @name('investment-group.update')
         * @middlewares('web', 'auth', 'can:update,investment_group')
         */
        $validated = $request->validated();

        $investmentGroup
            ->fill($validated)
            ->save();

        self::addSimpleSuccessMessage(__('Investment group updated'));

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
        /**
         * @delete('/investment-group/{investment_group}')
         * @name('investment-group.destroy')
         * @middlewares('web', 'auth', 'can:delete,investment_group')
         */
        try {
            $investmentGroup->delete();
            self::addSimpleSuccessMessage(__('Investment group deleted'));

            return redirect()->route('investment-group.index');
        } catch (\Illuminate\Database\QueryException $e) {
            if ($e->errorInfo[1] == 1451) {
                self::addSimpleDangerMessage(__('Investment group is in use, cannot be deleted'));
            } else {
                self::addSimpleDangerMessage(__('Database error:') . ' ' . $e->errorInfo[2]);
            }

            return redirect()->back();
        }
    }
}
