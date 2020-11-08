<?php

namespace App\Http\Controllers;

use App\AccountEntity;
use App\Transaction;
use Illuminate\Http\Request;

class MainController extends Controller
{
    public function index() {
        $accounts = AccountEntity::where('config_type', 'account')->get();

        $transactions = Transaction::with(
            [
                'config',
                'transactionType',
            ])->get();

        $withdrawals = $transactions->filter(function ($value, $key) {
            return $value->transactionType->name == 'withdrawal';
        });

        dd($withdrawals);

        return view('main.index');
    }
}
