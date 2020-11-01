<?php

use Illuminate\Support\Facades\Route;

use App\Http\Requests\TransactionRequest;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

Route::resource('accountgroups', 'AccountGroupController');
Route::resource('accounts', 'AccountController');
Route::resource('categories', 'CategoryController');
Route::resource('currencies', 'CurrencyController');
Route::resource('investmentgroups', 'InvestmentGroupController');
Route::resource('investments', 'InvestmentController');
Route::resource('payees', 'PayeeController');
Route::resource('tags', 'TagController');
Route::resource('transactions', 'TransactionController');

Route::get('tt', function() {
    //$r = app('App\Http\Requests\TransactionRequest');
    $r= new TransactionRequest([
        //'date' => Carbon::now(),
        'transaction_type_id' => 1,
        'config_type' => 'transaction_detail_standard',
        'config' => [
            'account_from_id' => 1,
            'account_to_id' => 11,
            'amount_from' => 1,
            'amount_to' => 1,
        ]
    ]);
    dd($r->rules());
});