<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use App\Http\Requests\InvestmentGroupRequest;
use App\Models\InvestmentGroup;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Laracasts\Utilities\JavaScript\JavaScriptFacade;

class InvestmentGroupController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            ['auth', 'verified'],
            new Middleware('can:viewAny,App\Models\InvestmentGroup', only: ['index']),
            new Middleware('can:view,investment_group', only: ['show']),
            new Middleware('can:create,App\Models\InvestmentGroup', only: ['create', 'store']),
            new Middleware('can:update,investment_group', only: ['edit', 'update']),
            new Middleware('can:delete,investment_group', only: ['destroy']),
        ];
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): View
    {
        /**
         * @get('/investment-group')
         * @name('investment-group.index')
         * @middlewares('web', 'auth', 'verified', 'can:viewAny,App\Models\InvestmentGroup')
         */
        // Get all investment groups of the user from the database and return to view
        $investmentGroups = $request->user()
            ->investmentGroups()
            ->select('id', 'name')
            ->withCount('investments')
            ->get();

        // Pass data for DataTables
        JavaScriptFacade::put([
            'investmentGroups' => $investmentGroups,
        ]);

        return view('investment-group.index');
    }

    public function create(): View
    {
        /**
         * @get('/investment-group/create')
         * @name('investment-group.create')
         * @middlewares('web', 'auth', 'verified', 'can:create,App\Models\InvestmentGroup')
         */
        return view('investment-group.form');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(InvestmentGroup $investmentGroup): View
    {
        /**
         * @get('/investment-group/{investment_group}/edit')
         * @name('investment-group.edit')
         * @middlewares('web', 'auth', 'verified', 'can:update,investment_group')
         */
        return view('investment-group.form', ['investmentGroup' => $investmentGroup]);
    }

    public function store(InvestmentGroupRequest $request): RedirectResponse
    {
        /**
         * @post('/investment-group')
         * @name('investment-group.store')
         * @middlewares('web', 'auth', 'verified', 'can:create,App\Models\InvestmentGroup')
         */
        $request->user()->investmentGroups()->create($request->validated());

        self::addSimpleSuccessMessage(__('Investment group added'));

        return to_route('investment-group.index');
    }

    public function update(InvestmentGroupRequest $request, InvestmentGroup $investmentGroup): RedirectResponse
    {
        /**
         * @methods('PUT', PATCH')
         * @uri('/investment-group/{investment_group}')
         * @name('investment-group.update')
         * @middlewares('web', 'auth', 'verified', 'can:update,investment_group')
         */
        $validated = $request->validated();

        $investmentGroup
            ->fill($validated)
            ->save();

        self::addSimpleSuccessMessage(__('Investment group updated'));

        return to_route('investment-group.index');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(InvestmentGroup $investmentGroup): RedirectResponse
    {
        /**
         * @delete('/investment-group/{investment_group}')
         * @name('investment-group.destroy')
         * @middlewares('web', 'auth', 'verified', 'can:delete,investment_group')
         */
        try {
            $investmentGroup->delete();
            self::addSimpleSuccessMessage(__('Investment group deleted'));

            return to_route('investment-group.index');
        } catch (QueryException $e) {
            if ($e->errorInfo[1] === 1451) {
                self::addSimpleErrorMessage(__('Investment group is in use, cannot be deleted'));
            } else {
                self::addSimpleErrorMessage(__('Database error:') . ' ' . $e->errorInfo[2]);
            }

            return redirect()->back();
        }
    }
}
