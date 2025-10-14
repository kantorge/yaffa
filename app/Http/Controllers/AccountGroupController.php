<?php

namespace App\Http\Controllers;

use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use App\Http\Requests\AccountGroupRequest;
use App\Models\AccountGroup;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Laracasts\Utilities\JavaScript\JavaScriptFacade;

class AccountGroupController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            ['auth', 'verified'],
            new Middleware('can:viewAny,App\Models\AccountGroup', only: ['index']),
            new Middleware('can:view,account_group', only: ['show']),
            new Middleware('can:create,App\Models\AccountGroup', only: ['create', 'store']),
            new Middleware('can:update,account_group', only: ['edit', 'update']),
            new Middleware('can:delete,account_group', only: ['destroy']),
        ];
    }

    /**
     * Display a listing of the resource.
     *
     * @return View
     */
    public function index(): View
    {
        /**
         * @get('/account-group')
         * @name('account-group.index')
         * @middlewares('web', 'auth', 'verified', 'can:viewAny,App\Models\AccountGroup')
         */
        // Get all account groups of the user from the database and return to view
        $accountGroups = Auth::user()
            ->accountGroups()
            ->select('id', 'name')
            ->withCount('accountEntities')
            ->get();

        // Pass data for DataTables
        JavaScriptFacade::put([
            'accountGroups' => $accountGroups,
        ]);

        return view('account-group.index');
    }

    public function create(): View
    {
        /**
         * @get('/account-group/create')
         * @name('account-group.create')
         * @middlewares('web', 'auth', 'verified', 'can:create,App\Models\AccountGroup')
         */
        return view('account-group.form');
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  AccountGroup  $accountGroup
     * @return View
     */
    public function edit(AccountGroup $accountGroup): View
    {
        /**
         * @get('/account-group/{account_group}/edit')
         * @name('account-group.edit')
         * @middlewares('web', 'auth', 'verified', 'can:update,account_group')
         */
        return view('account-group.form', ['accountGroup' => $accountGroup]);
    }

    public function store(AccountGroupRequest $request): RedirectResponse
    {
        /**
         * @post('/account-group')
         * @name('account-group.store')
         * @middlewares('web', 'auth', 'verified', 'can:create,App\Models\AccountGroup')
         */
        $request->user()->accountGroups()->create($request->validated());

        self::addSimpleSuccessMessage(__('Account group added'));

        return redirect()->route('account-group.index');
    }

    public function update(AccountGroupRequest $request, AccountGroup $accountGroup): RedirectResponse
    {
        /**
         * @methods('PUT', PATCH')
         * @uri('/account-group/{account_group}')
         * @name('account-group.update')
         * @middlewares('web', 'auth', 'verified', 'can:update,account_group')
         */
        $validated = $request->validated();

        $accountGroup->fill($validated)
            ->save();

        self::addSimpleSuccessMessage(__('Account group updated'));

        return redirect()->route('account-group.index');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  AccountGroup  $accountGroup
     * @return RedirectResponse
     */
    public function destroy(AccountGroup $accountGroup): RedirectResponse
    {
        /**
         * @delete('/account-group/{account_group}')
         * @name('account-group.destroy')
         * @middlewares('web', 'auth', 'verified', 'can:delete,account_group')
         */
        try {
            $accountGroup->delete();
            self::addSimpleSuccessMessage(__('Account group deleted'));

            return redirect()->route('account-group.index');
        } catch (QueryException $e) {
            if ($e->errorInfo[1] === 1451) {
                self::addSimpleErrorMessage(__('Account group is in use, cannot be deleted'));
            } else {
                self::addSimpleErrorMessage(__('Database error:') . ' ' . $e->errorInfo[2]);
            }
        }

        return redirect()->back();
    }
}
