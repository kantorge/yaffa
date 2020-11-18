<?php

namespace App\Http\Controllers\API;

use App\Account;
use App\AccountEntity;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AccountApiController extends Controller
{
    public function __construct(AccountEntity $account)
    {
        $this->account = $account->where('config_type', 'account');
    }

    public function getList(Request $request)
    {
        $accounts = $this->account
            ->select(['id', 'name AS text'])
            ->when($request->get('q'), function($query) use ($request) {
                $query->where('name', 'LIKE', '%' . $request->get('q') . '%');
            })
            ->where('active', 1)
            ->orderBy('name')
            ->take(10)
            ->get();

        //return data
        return response()->json($accounts, Response::HTTP_OK);
    }


            //TODO: get top active accounts, considering selected account as well
            /*
            $accountField = ($this->input->get('transaction_type') == 'deposit' ? 'accounts_to_id' : 'accounts_from_id');
            $query = "SELECT    `head`.`payees_id` AS `id`,
                                `p`.`name` AS `text`
                        FROM `standard_transaction_headers` AS `head`
                        LEFT JOIN `payees` AS `p` ON `p`.`id` = `head`.`payees_id`
                        WHERE   `transaction_types_id` = (SELECT `id` FROM `transaction_types` WHERE `name` = ". $this->db->escape($this->input->get('transaction_type')) .")
                                AND `p`.`active`= 1
                                AND `head`.`$accountField` = ".$this->db->escape($this->input->get('account'))."
                        GROUP BY `id`, `text`
                        ORDER BY count(`head`.`id`) DESC
                        LIMIT 5";
            $json = $this->db->query($query)->result();
            */

    public function getAccountCurrencyLabel(Request $request) {

		if (empty($request->get('account_id'))) {
			die("");
		}

        $account = Account::find($request->get('account_id'));
        $currency = \App\Currency::find($account->currencies_id);

		echo $currency->suffix;
    }

}
