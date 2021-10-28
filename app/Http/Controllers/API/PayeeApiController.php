<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\AccountEntityRequest;
use App\Models\AccountEntity;
use App\Models\Category;
use App\Models\Payee;
use App\Models\TransactionType;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class PayeeApiController extends Controller
{
    protected $payee;

    public function __construct(AccountEntity $payee)
    {
        $this->payee = $payee->where('config_type', 'payee');
    }

    public function getList(Request $request)
    {
        if ($request->get('q')) {
            $payees = $this->payee
                ->select(['id', 'name AS text'])
                ->when($request->get('q'), function ($query) use ($request) {
                    $query->where('name', 'LIKE', '%'.$request->get('q').'%');
                })
                ->where('active', 1)
                ->orderBy('name')
                ->take(10)
                ->get();
        } else {
            // Account and transaction type is expected to be present
            $accountId = $request->get('account_id');

            $accountDirection = ($request->get('account_type') === 'from' ? 'to' : 'from');
            $payeeDirection = ($request->get('account_type') === 'from' ? 'from' : 'to');

            $payees = DB::table('transactions')
                ->join(
                    'transaction_details_standard',
                    'transaction_details_standard.id',
                    '=',
                    'transactions.config_id'
                )
                ->join(
                    'account_entities',
                    'account_entities.id',
                    '=',
                    "transaction_details_standard.account_{$payeeDirection}_id"
                )
                ->select('account_entities.id', 'account_entities.name AS text')
                ->where('account_entities.active', true)
                ->where(
                    // TODO: fallback to query without this, if no results are found
                    'transaction_type_id',
                    '=',
                    TransactionType::where('name', '=', $request->get('transaction_type'))->first()->id
                )
                ->when($accountId, function ($query) use ($accountDirection, $accountId) {
                    return $query->where(
                        "transaction_details_standard.account_{$accountDirection}_id",
                        '=',
                        $accountId
                    );
                })
                ->groupBy("transaction_details_standard.account_{$payeeDirection}_id")
                ->orderByRaw('count(*) DESC')
                ->limit(10)
                ->get();
        }

        //return data
        return response()->json($payees, Response::HTTP_OK);
    }

    public function getDefaultCategoryForPayee(Request $request)
    {
        if ($request->missing('payee_id')) {
            return response('', Response::HTTP_OK);
        }

        $payee = AccountEntity::
            with(['config', 'config.category'])
            ->find($request->get('payee_id'));

        if (! $payee->config->category_id) {
            return response('', Response::HTTP_OK);
        }

        return response($payee->config->category->only(['id', 'full_name']), Response::HTTP_OK);
    }

    public function getPayeeDefaultSuggestion()
    {
        $baseQueryFrom = DB::table('transaction_items')
            ->join(
                'transactions',
                'transactions.id',
                '=',
                'transaction_items.transaction_id'
            )
            ->join(
                'transaction_details_standard',
                'transaction_details_standard.id',
                '=',
                'transactions.config_id'
            )
            ->join(
                'categories',
                'categories.id',
                '=',
                'transaction_items.category_id'
            )
            ->join(
                'account_entities',
                'account_entities.id',
                '=',
                'transaction_details_standard.account_from_id'
            )
            ->join(
                'payees',
                'payees.id',
                '=',
                'account_entities.config_id'
            )
            ->where('categories.active', true) // Only active category can be recommended
            ->whereNull('payees.category_id') // No category set
            ->whereNull('payees.category_suggestion_dismissed') // Suggestion was not dismissed yet
            ->where('account_entities.config_type', 'payee')
            ->where('account_entities.active', true) // Only active payee can get recommendation
            ->select([
                'transaction_details_standard.account_from_id as payee_id',
                'categories.id as category_id',
            ]);

        $baseQuery = DB::table('transaction_items')
            ->join(
                'transactions',
                'transactions.id',
                '=',
                'transaction_items.transaction_id'
            )
            ->join(
                'transaction_details_standard',
                'transaction_details_standard.id',
                '=',
                'transactions.config_id'
            )
            ->join(
                'categories',
                'categories.id',
                '=',
                'transaction_items.category_id'
            )
            ->join(
                'account_entities',
                'account_entities.id',
                '=',
                'transaction_details_standard.account_to_id'
            )
            ->join(
                'payees',
                'payees.id',
                '=',
                'account_entities.config_id'
            )
            ->where('categories.active', true) // Only active category can be recommended
            ->whereNull('payees.category_id') // No category set
            ->whereNull('payees.category_suggestion_dismissed') // Suggestion was not dismissed yet
            ->where('account_entities.config_type', 'payee')
            ->where('account_entities.active', true) // Only active payee can get recommendation
            ->select([
                'transaction_details_standard.account_to_id as payee_id',
                'categories.id as category_id',
            ])
            ->unionAll($baseQueryFrom);

        $data = DB::query()
            ->fromSub($baseQuery, 'base')
            ->select([
                'payee_id',
                'category_id',
            ])
            ->selectRaw('count(*) as transactions')
            ->groupBy([
                'payee_id', 'category_id',
            ])
            ->get();

        // Calculate total by payees
        $payees = $data
            ->groupBy('payee_id')
            ->map(function ($payee) {
                return [
                    'payee_id' => $payee->first()->payee_id,
                    'sum' => $payee->sum('transactions'),
                    'max' => $payee->max('transactions'),
                    'max_category_id' => $payee->firstWhere('transactions', $payee->max('transactions'))->category_id,
                ];
            })
            // Minimum required transactions to calculate with payee
            // TODO: make this dynamic, e.g based on average or mean
            ->filter(function ($value) {
                return $value['sum'] > 5;
            })
            // Only where maximum is significant (at least half of all items)
            // TODO: make this dynamic
            ->filter(function ($value) {
                return $value['max'] / $value['sum'] > .5;
            });

        $payee = $payees->random();

        $payee['payee'] = AccountEntity::find($payee['payee_id'])->name;
        $payee['category'] = Category::find($payee['max_category_id'])->full_name;

        return response($payee, Response::HTTP_OK);
    }

    public function acceptPayeeDefaultCategorySuggestion(AccountEntity $payee, Category $category)
    {
        $payee->load(['config']);
        $payee->config->category_id = $category->id;
        $payee->config->save();

        return Response::HTTP_OK;
    }

    public function dismissPayeeDefaultCategorySuggestion(AccountEntity $payee)
    {
        $payee->load(['config']);
        $payee->config->category_suggestion_dismissed = Carbon::now();
        $payee->config->save();

        return Response::HTTP_OK;
    }

    public function storePayee(AccountEntityRequest $request)
    {
        $validated = $request->validated();

        $newPayee = new AccountEntity($validated);

        $payeeConfig = Payee::create($validated['config']);
        $newPayee->config()->associate($payeeConfig);

        $newPayee->push();

        return $newPayee;
    }
}
