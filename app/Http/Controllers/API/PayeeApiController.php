<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\AccountEntity;
use App\Models\TransactionType;
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
                    $query->where('name', 'LIKE', '%' . $request->get('q') . '%');
                })
                ->where('active', 1)
                ->orderBy('name')
                ->take(10)
                ->get();
        } else {
            $accountId = $request->get('account_id');
            $accountDirection = ($request->get('type') === 'to' ? 'to' : 'from');
            // Reverse selection for main selection
            $payeeDirection = ($request->get('type') === 'to' ? 'from' : 'to');

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
			return response("", Response::HTTP_OK);
        }

        $payee = AccountEntity::
            with(['config', 'config.category'])
            ->find($request->get('payee_id'));

        if (!$payee->config->category_id) {
            return response("", Response::HTTP_OK);
        }

        return response($payee->config->category->only(['id', 'full_name']), Response::HTTP_OK);
	}
}
