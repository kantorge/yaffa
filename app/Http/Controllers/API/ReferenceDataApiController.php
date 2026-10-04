<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\AccountEntity;
use App\Models\Category;
use App\Models\Currency;
use App\Models\Tag;
use App\Models\User;
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
     * Accounts, payees, categories, tags and currencies in one call. With `updated_since` (ISO 8601) only rows
     * changed since then are returned, plus `ids` (every current id per type) so the client can drop deleted rows,
     * as deletions leave no trace otherwise. Accounts are always returned in full: their settings live in a table
     * without timestamps, so a change there cannot be detected. Send the previous `ETag` as `If-None-Match` to get
     * 304 when nothing changed. Use `server_time` as the next `updated_since`.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate(['updated_since' => ['nullable', 'date']]);

        /** @var User $user */
        $user = $request->user();

        $serverTime = now();
        $since = $request->filled('updated_since') ? Carbon::parse((string) $request->input('updated_since')) : null;

        $data = [
            'accounts' => $user->accounts()->with('config')->orderBy('id')->get()
                ->map(fn (AccountEntity $e) => [
                    'id' => $e->id,
                    'name' => $e->name,
                    'active' => (bool) $e->active,
                    'currency_id' => $e->config instanceof Account ? $e->config->currency_id : null,
                    'account_group_id' => $e->config instanceof Account ? $e->config->account_group_id : null,
                ])->values(),
            'payees' => $this->changedSince($user->payees()->getQuery(), $since)->orderBy('id')->get(['id', 'name', 'active'])
                ->map(fn (AccountEntity $e) => ['id' => $e->id, 'name' => $e->name, 'active' => (bool) $e->active])->values(),
            'categories' => $this->changedSince(Category::query()->where('user_id', $user->id), $since)->orderBy('id')->get()
                ->map(fn (Category $c) => ['id' => $c->id, 'name' => $c->name, 'active' => (bool) $c->active, 'parent_id' => $c->parent_id])->values(),
            'tags' => $this->changedSince(Tag::query()->where('user_id', $user->id), $since)->orderBy('id')->get()
                ->map(fn (Tag $t) => ['id' => $t->id, 'name' => $t->name, 'active' => (bool) $t->active])->values(),
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
