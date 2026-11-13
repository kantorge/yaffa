<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReferenceDataRequest;
use App\Models\Account;
use App\Models\AccountEntity;
use App\Models\Category;
use App\Models\Currency;
use App\Models\Investment;
use App\Models\Payee;
use App\Models\Tag;
use App\Models\User;
use App\Services\ReferenceUsageService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Middleware;

#[Middleware('auth:sanctum')]
#[Middleware('verified')]
#[Middleware('abilities:read')]
class ReferenceDataApiController extends Controller
{
    /**
     * Get reference data for offline entry
     *
     * Accounts, payees, categories, tags, investments and currencies in one call. With `updated_since` (ISO 8601) only rows
     * changed since then are returned, plus `ids` (every current id per type) so the client can drop deleted rows,
     * as deletions leave no trace otherwise. Accounts are always returned in full: their settings live in a table
     * without timestamps, so a change there cannot be detected. Send the previous `ETag` as `If-None-Match` to get
     * 304 when nothing changed. Use `server_time` as the next `updated_since`.
     * A payee's `category_id` (its default category) is stored outside the timestamped row, so with `updated_since`
     * it can lag until the payee row itself changes: refetch in full when it matters.
     */
    public function index(ReferenceDataRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $serverTime = now();
        $since = $request->filled('updated_since') ? Carbon::parse((string) $request->input('updated_since'))->setTimezone(config('app.timezone')) : null;

        $data = [
            'accounts' => $user->accounts()->with('config')->orderBy('id')->get()
                ->map(fn (AccountEntity $e) => [
                    'id' => $e->id,
                    'name' => $e->name,
                    'alias' => $e->alias,
                    'active' => (bool) $e->active,
                    'currency_id' => $e->config instanceof Account ? $e->config->currency_id : null,
                    'account_group_id' => $e->config instanceof Account ? $e->config->account_group_id : null,
                    'settlement_account_id' => $e->config instanceof Account ? $e->config->settlement_account_id : null,
                ])->values(),
            'payees' => $this->changedSince($user->payees()->getQuery(), $since)->with('config')->orderBy('id')
                ->get(['id', 'name', 'alias', 'active', 'config_type', 'config_id'])
                ->map(fn (AccountEntity $e) => [
                    'id' => $e->id,
                    'name' => $e->name,
                    'alias' => $e->alias,
                    'active' => (bool) $e->active,
                    'category_id' => $e->config instanceof Payee ? $e->config->category_id : null,
                ])->values(),
            'categories' => $this->changedSince(Category::query()->where('user_id', $user->id), $since)->orderBy('id')->get()
                ->map(fn (Category $c) => ['id' => $c->id, 'name' => $c->name, 'active' => (bool) $c->active, 'parent_id' => $c->parent_id])->values(),
            'tags' => $this->changedSince(Tag::query()->where('user_id', $user->id), $since)->orderBy('id')->get()
                ->map(fn (Tag $t) => ['id' => $t->id, 'name' => $t->name, 'active' => (bool) $t->active])->values(),
            'investments' => $this->changedSince(Investment::query()->where('user_id', $user->id), $since)->orderBy('id')->get(['id', 'name', 'symbol', 'currency_id', 'active'])
                ->map(fn (Investment $i) => [
                    'id' => $i->id,
                    'name' => $i->name,
                    'symbol' => $i->symbol,
                    'currency_id' => $i->currency_id,
                    'active' => (bool) $i->active,
                ])->values(),
            'currencies' => $this->changedSince(Currency::query()->where('user_id', $user->id), $since)->orderBy('id')->get()
                ->map(fn (Currency $c) => [
                    'id' => $c->id,
                    'iso_code' => $c->iso_code,
                    'name' => $c->name,
                    'base' => (bool) $c->base,
                    'generic_decimal_precision' => $c->generic_decimal_precision,
                    'detailed_decimal_precision' => $c->detailed_decimal_precision,
                ])->values(),
        ];

        if ($since) {
            $data['ids'] = [
                'payees' => $user->payees()->pluck('id'),
                'categories' => Category::query()->where('user_id', $user->id)->pluck('id'),
                'tags' => Tag::query()->where('user_id', $user->id)->pluck('id'),
                'investments' => $user->investments()->pluck('id'),
                'currencies' => Currency::query()->where('user_id', $user->id)->pluck('id'),
            ];
        }

        // server_time is left out of the hash: it changes on every call and would defeat the ETag
        $etag = md5(json_encode($data));

        $response = response()->json($data + ['server_time' => $serverTime->toIso8601String()]);
        $response->setEtag($etag);
        $response->isNotModified($request);

        return $response;
    }

    /**
     * Get recent usage for ranking suggestions
     *
     * Rolling-window aggregates (`window_months`) for suggesting the most and recently used accounts, payees,
     * investments and per-payee categories: `standard` and `investment` rows are grouped by transaction type and
     * the accounts/investment involved, with a use count and the last date. Sum rows to rank a single entity.
     * `payee_categories` holds each payee's top categories, `deferred_categories` the ones it opted out of.
     */
    public function usage(Request $request, ReferenceUsageService $service): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json($service->forUser($user));
    }

    /**
     * @template T of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<T>  $query
     * @return Builder<T>
     */
    private function changedSince(Builder $query, ?Carbon $since): Builder
    {
        return $since ? $query->where('updated_at', '>', $since) : $query;
    }
}
