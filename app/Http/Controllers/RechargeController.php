<?php

namespace App\Http\Controllers;

use App\Http\Requests\DeactivateSettledTagsRequest;
use App\Models\Tag;
use App\Services\RechargeTagService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\View\View;

/**
 * Follow-up pages for recharges: items in a tag-requiring category without a tag, and the
 * balance of every tag used in an "expect nil" category.
 */
class RechargeController extends Controller implements HasMiddleware
{
    public function __construct(private readonly RechargeTagService $rechargeTagService)
    {
    }

    public static function middleware(): array
    {
        return [
            'auth',
            'verified',
            new Middleware('can:viewAny,' . Tag::class, only: ['untagged', 'openTags']),
            new Middleware('can:create,' . Tag::class, only: ['deactivateSettled']),
        ];
    }

    public function untagged(Request $request): View
    {
        /**
         * @get("/reports/recharges/untagged")
         * @name("reports.recharges.untagged")
         * @middlewares("web", "auth", "verified")
         */
        $this->abortUnlessEnabled($request);

        return view('reports.recharges.untagged', [
            'items' => $this->rechargeTagService->untaggedItems($request->user()),
            'hasTagRequiredCategories' => $this->rechargeTagService->hasTagRequiredCategories($request->user()),
            'backstopDate' => $request->user()->recharge_backstop_date,
        ]);
    }

    public function openTags(Request $request): View
    {
        /**
         * @get("/reports/recharges/open")
         * @name("reports.recharges.open")
         * @middlewares("web", "auth", "verified")
         */
        $this->abortUnlessEnabled($request);

        $balances = $this->rechargeTagService->tagBalances($request->user());

        return view('reports.recharges.open', [
            'openTags' => $balances->where('settled', false)->values(),
            'settledTags' => $balances->filter(fn (array $row) => $row['settled'] && $row['active'])->values(),
            'baseCurrency' => $request->user()->baseCurrency(),
            'tolerance' => RechargeTagService::SETTLED_TOLERANCE,
            'backstopDate' => $request->user()->recharge_backstop_date,
        ]);
    }

    public function deactivateSettled(DeactivateSettledTagsRequest $request): RedirectResponse
    {
        /**
         * @post("/reports/recharges/deactivate-settled")
         * @name("reports.recharges.deactivate-settled")
         * @middlewares("web", "auth", "verified")
         */
        $this->abortUnlessEnabled($request);

        $count = $this->rechargeTagService->deactivateSettled(
            $request->user(),
            $request->validated('tag_ids'),
        );

        self::addSimpleSuccessMessage(trans_choice(':count tag deactivated|:count tags deactivated', $count, ['count' => $count]));

        return redirect()->route('reports.recharges.open');
    }

    /**
     * Recharge tags are opt-in in the user settings; the pages do not exist for other users.
     */
    private function abortUnlessEnabled(Request $request): void
    {
        abort_unless($this->rechargeTagService->isEnabled($request->user()), 404);
    }
}
