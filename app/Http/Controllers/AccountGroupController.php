<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use App\Http\Requests\AccountGroupRequest;
use App\Models\AccountGroup;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Laracasts\Utilities\JavaScript\JavaScriptFacade;

class AccountGroupController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            'auth',
            'verified',
            new Middleware('can:create,' . AccountGroup::class, only: ['create', 'store']),
            new Middleware('can:update,account_group', only: ['edit', 'update']),
            new Middleware('can:delete,account_group', only: ['destroy']),
        ];
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): View
    {
        /**
         * @get("/account-group")
         * @name("account-group.index")
         * @middlewares("web", "auth", "verified")
         */
        // Get all account groups of the user from the database and return to view
        $accountGroups = $request->user()
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
         * @get("/account-group/create")
         * @name("account-group.create")
         * @middlewares("web", "auth", "verified")
         */
        return view('account-group.form');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(AccountGroup $accountGroup): View
    {
        /**
         * @get("/account-group/{account_group}/edit")
         * @name("account-group.edit")
         * @middlewares("web", "auth", "verified")
         */
        return view('account-group.form', ['accountGroup' => $accountGroup]);
    }

    public function store(AccountGroupRequest $request): RedirectResponse
    {
        /**
         * @post("/account-group")
         * @name("account-group.store")
         * @middlewares("web", "auth", "verified")
         */
        $request->user()->accountGroups()->create($request->validated());

        self::addSimpleSuccessMessage(__('Account group added'));

        return to_route('account-group.index');
    }

    public function update(AccountGroupRequest $request, AccountGroup $accountGroup): RedirectResponse
    {
        /**
         * @methods("PUT", "PATCH")
         * @uri("/account-group/{account_group}")
         * @name("account-group.update")
         * @middlewares("web", "auth", "verified")
         */
        $validated = $request->validated();

        $accountGroup->fill($validated)
            ->save();

        self::addSimpleSuccessMessage(__('Account group updated'));

        return to_route('account-group.index');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(AccountGroup $accountGroup): RedirectResponse
    {
        /**
         * @delete("/account-group/{account_group}")
         * @name("account-group.destroy")
         * @middlewares("web", "auth", "verified")
         */
        try {
            $accountGroup->delete();
            self::addSimpleSuccessMessage(__('Account group deleted'));

            return to_route('account-group.index');
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
