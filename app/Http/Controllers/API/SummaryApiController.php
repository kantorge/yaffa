<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Traits\CurrencyTrait;
use App\Models\Budget;
use App\Models\Category;
use App\Models\Currency;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\User;
use App\Services\BudgetService;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Middleware;
use Illuminate\Support\Carbon;

#[Middleware('auth:sanctum')]
#[Middleware('verified')]
#[Middleware('abilities:read')]
class SummaryApiController extends Controller
{
    use CurrencyTrait;

    public function __construct(private readonly BudgetService $budgetService)
    {
    }

    /**
     * Get a mobile summary
     *
     * One call for a phone's at-a-glance view, all in the user's base currency: account balances, income and
     * expense of the current month (scheduled items and transfers excluded), and the current month's budget
     * status (budgeted vs spent). Amounts are decimal strings. Returns `{"result": "busy"}` while the account
     * summaries are being recalculated, like the balance endpoint it builds on.
     */
    public function show(Request $request, AccountApiController $accountController): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $base = $this->getBaseCurrency($user->id);

        if ($base === null) {
            return response()->json([
                'error' => ['code' => 'NO_BASE_CURRENCY', 'message' => 'No base currency is configured.'],
            ], 422);
        }

        $balance = $accountController->getAccountBalance($request)->getData(true);

        if (($balance['result'] ?? null) !== 'success') {
            return response()->json(['result' => $balance['result'] ?? 'busy', 'message' => $balance['message'] ?? null]);
        }

        $rates = $this->allCurrencyRatesByMonth();
        $toBase = function (BigDecimal $amount, ?int $currencyId) use ($rates, $base): BigDecimal {
            $rate = $this->getLatestRateFromMap($currencyId, Carbon::now(), $rates, $base->id);

            return $rate === null ? $amount : $amount->multipliedBy(BigDecimal::of($rate));
        };
        $format = fn (BigDecimal $amount): string => (string) $amount->toScale($base->generic_decimal_precision ?? 2, RoundingMode::HalfUp);

        $from = Carbon::now()->startOfMonth();
        $to = Carbon::now()->endOfMonth();

        $accounts = collect($balance['accountBalanceData'])->map(fn (array $a) => [
            'id' => $a['id'],
            'name' => $a['name'],
            'active' => (bool) $a['active'],
            'currency' => $a['currency']['iso_code'] ?? null,
            'balance' => $format(BigDecimal::of($a['sum'] ?? 0)),
        ])->values();

        $flows = Transaction::query()
            ->where('user_id', $user->id)
            ->where('schedule', false)
            ->where('config_type', 'standard')
            ->whereIn('transaction_type', ['withdrawal', 'deposit'])
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->selectRaw('transaction_type, currency_id, SUM(ABS(cashflow_value)) as total')
            ->groupBy('transaction_type', 'currency_id')
            ->get();

        $sumFlows = fn (string $type): BigDecimal => $flows->where('transaction_type', $type)->reduce(
            fn (BigDecimal $carry, Transaction $row) => $carry->plus($toBase(BigDecimal::of($row->getRawOriginal('total')), $row->currency_id)),
            BigDecimal::zero()
        );

        [$budgeted, $spent] = $this->budgetStatus($user, $from, $to, $toBase);

        return response()->json([
            'result' => 'success',
            'base_currency' => $base->iso_code,
            'accounts' => $accounts,
            'total_balance' => $format($accounts->reduce(fn (BigDecimal $c, array $a) => $c->plus($a['balance']), BigDecimal::zero())),
            'month' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'income' => $format($sumFlows('deposit')),
                'expense' => $format($sumFlows('withdrawal')),
            ],
            'budget' => [
                'budgeted' => $format($budgeted),
                'spent' => $format($spent),
                'remaining' => $format($budgeted->minus($spent)),
            ],
        ]);
    }

    /**
     * Budgeted amount of this month's active spending budgets, and what was spent in their categories.
     *
     * @param  callable(BigDecimal, int|null): BigDecimal  $toBase
     * @return array{BigDecimal, BigDecimal}
     */
    private function budgetStatus(User $user, Carbon $from, Carbon $to, callable $toBase): array
    {
        $budgets = Budget::query()->where('user_id', $user->id)->where('active', true)->where('transaction_type', 'withdrawal')->get();
        $currencyIds = Currency::query()->where('user_id', $user->id)->pluck('id', 'iso_code');

        $budgeted = BigDecimal::zero();
        foreach ($budgets as $budget) {
            $occurrences = count($this->budgetService->projectOccurrences($budget, $from, $to));
            $money = $budget->amount;

            $budgeted = $budgeted->plus($toBase(
                $money->getAmount()->multipliedBy($occurrences),
                $currencyIds[$money->getCurrency()->getCurrencyCode()] ?? null
            ));
        }

        $categoryIds = $budgets->pluck('category_id')->unique();
        $categoryIds = $categoryIds->merge(Category::query()->whereIn('parent_id', $categoryIds)->pluck('id'))->unique();

        $spent = TransactionItem::query()
            ->join('transactions', 'transactions.id', '=', 'transaction_items.transaction_id')
            ->where('transactions.user_id', $user->id)
            ->where('transactions.schedule', false)
            ->where('transactions.transaction_type', 'withdrawal')
            ->whereBetween('transactions.date', [$from->toDateString(), $to->toDateString()])
            ->whereIn('transaction_items.category_id', $categoryIds)
            ->selectRaw('transactions.currency_id as currency_id, SUM(transaction_items.amount) as total')
            ->groupBy('transactions.currency_id')
            ->get()
            ->reduce(
                fn (BigDecimal $carry, TransactionItem $row) => $carry->plus($toBase(BigDecimal::of($row->getRawOriginal('total')), $row->getRawOriginal('currency_id'))),
                BigDecimal::zero()
            );

        return [$budgeted, $spent];
    }
}
