<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Traits\CurrencyTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controllers\HasMiddleware;

class DashboardApiController extends Controller implements HasMiddleware
{
    use CurrencyTrait;

    public static function middleware(): array
    {
        return [
            ['auth:sanctum', 'verified'],
        ];
    }

    public function getManualAssetOverview(Request $request): JsonResponse
    {
        /**
         * @get('/api/dashboard/manual-assets')
         * @middlewares('api', 'auth:sanctum', 'verified')
         */
        $user = $request->user();
        $baseCurrency = $this->getBaseCurrency();

        if (!$baseCurrency) {
            return response()->json(
                [
                    'result' => 'error',
                    'message' => __('Base currency is not set'),
                ],
                Response::HTTP_BAD_REQUEST
            );
        }

        $currencies = $user->currencies()->get();

        $accounts = $user
            ->accounts()
            ->with(['config', 'config.accountGroup', 'config.currency'])
            ->get()
            ->filter(function ($account) {
                return $account->config
                    && ($account->config->manual_balance !== null
                        || $account->config->manual_interest_rate !== null
                        || $account->config->manual_trend !== null);
            })
            ->map(function ($account) use ($currencies, $baseCurrency) {
                $balance = $account->config->manual_balance ?? 0;
                $balanceBase = $balance;

                if ($account->config->currency_id !== $baseCurrency->id) {
                    $rate = $currencies->find($account->config->currency_id)->rate() ?? 1;
                    $balanceBase = $balance * $rate;
                }

                return [
                    'id' => $account->id,
                    'name' => $account->name,
                    'group_name' => $account->config->accountGroup->name,
                    'type' => 'account',
                    'balance' => $balance,
                    'balance_base' => $balanceBase,
                    'currency' => $account->config->currency,
                    'trend' => $account->config->manual_trend,
                    'interest_rate' => $account->config->manual_interest_rate,
                    'active' => $account->active,
                ];
            });

        $investments = $user
            ->investments()
            ->with(['currency', 'investmentGroup'])
            ->get()
            ->filter(function ($investment) {
                return $investment->manual_balance !== null || $investment->manual_trend !== null;
            })
            ->map(function ($investment) use ($currencies, $baseCurrency) {
                $balance = $investment->manual_balance ?? 0;
                $balanceBase = $balance;

                if ($investment->currency_id !== $baseCurrency->id) {
                    $rate = $currencies->find($investment->currency_id)->rate() ?? 1;
                    $balanceBase = $balance * $rate;
                }

                return [
                    'id' => $investment->id,
                    'name' => $investment->name,
                    'group_name' => $investment->investmentGroup->name,
                    'type' => 'investment',
                    'balance' => $balance,
                    'balance_base' => $balanceBase,
                    'currency' => $investment->currency,
                    'trend' => $investment->manual_trend,
                    'interest_rate' => null,
                    'active' => $investment->active,
                ];
            });

        $assets = $accounts->concat($investments)->values();

        $totals = [
            'accounts' => $accounts->sum('balance_base'),
            'investments' => $investments->sum('balance_base'),
        ];
        $totals['overall'] = $totals['accounts'] + $totals['investments'];

        return response()->json(
            [
                'result' => 'success',
                'base_currency' => $baseCurrency,
                'assets' => $assets,
                'totals' => $totals,
            ],
            Response::HTTP_OK
        );
    }
}
