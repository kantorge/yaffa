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
            'auth',
            'verified',
            new Middleware('can:viewAny,' . InvestmentGroup::class, only: ['index']),
            new Middleware('can:create,' . InvestmentGroup::class, only: ['create', 'store']),
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
         * @get("/investment-groups")
         * @name("investment-groups.index")
         * @middlewares("web", "auth", "verified")
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

        return view('investment-groups.index');
    }

    public function create(): View
    {
        /**
         * @get("/investment-groups/create")
         * @name("investment-groups.create")
         * @middlewares("web", "auth", "verified")
         */
        return view('investment-groups.form');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(InvestmentGroup $investmentGroup): View
    {
        /**
         * @get("/investment-groups/{investment_group}/edit")
         * @name("investment-groups.edit")
         * @middlewares("web", "auth", "verified")
         */
        return view('investment-groups.form', ['investmentGroup' => $investmentGroup]);
    }

    public function store(InvestmentGroupRequest $request): RedirectResponse
    {
        /**
         * @post("/investment-groups")
         * @name("investment-groups.store")
         * @middlewares("web", "auth", "verified")
         */
        $request->user()->investmentGroups()->create($request->validated());

        self::addSimpleSuccessMessage(__('Investment group added'));

        return to_route('investment-groups.index');
    }

    public function update(InvestmentGroupRequest $request, InvestmentGroup $investmentGroup): RedirectResponse
    {
        /**
         * @methods("PUT", "PATCH")
         * @uri("/investment-groups/{investment_group}")
         * @name("investment-groups.update")
         * @middlewares("web", "auth", "verified")
         */
        $validated = $request->validated();

        $investmentGroup
            ->fill($validated)
            ->save();

        self::addSimpleSuccessMessage(__('Investment group updated'));

        return to_route('investment-groups.index');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(InvestmentGroup $investmentGroup): RedirectResponse
    {
        /**
         * @delete("/investment-groups/{investment_group}")
         * @name("investment-groups.destroy")
         * @middlewares("web", "auth", "verified")
         */
        try {
            $investmentGroup->delete();
            self::addSimpleSuccessMessage(__('Investment group deleted'));

            return to_route('investment-groups.index');
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
