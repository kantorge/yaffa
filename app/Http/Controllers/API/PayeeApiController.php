<?php

namespace App\Http\Controllers\API;

use App\Account;
use App\AccountEntity;
use App\Http\Controllers\Controller;
use App\Payee;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PayeeApiController extends Controller
{
    protected $payee;

    public function __construct(AccountEntity $payee)
    {
        $this->payee = $payee->where('config_type', 'payee');
    }

    public function getList(Request $request)
    {
        $payees = $this->payee
            ->select(['id', 'name AS text'])
            ->when($request->get('q'), function($query) use ($request) {
                $query->where('name', 'LIKE', '%' . $request->get('q') . '%');
            })
            ->where('active', 1)
            ->orderBy('name')
            ->take(10)
            ->get();

        //return data
        return response()->json($payees, Response::HTTP_OK);
    }
            //get top active payees, considering selected account as well
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


    public function getDefaultCategoryForPayee(Request $request) {
        if (empty($request->get('payee_id'))) {
			return response("", Response::HTTP_OK);
        }

        $payee = AccountEntity::with(['config', 'config.categories'])->find($request->get('payee_id'));

        if(!$payee->config->categories) {
            return response("", Response::HTTP_OK);
        }

        return response($payee->config->categories->only(['id', 'full_name']), Response::HTTP_OK);
	}
}
