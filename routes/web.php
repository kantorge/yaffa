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

Route::get('/','MainController@index');

Route::resource('accountgroups', 'AccountGroupController');

Route::get('/accounts/history/{account}', 'MainController@account_details');
Route::resource('accounts', 'AccountController');

Route::resource('categories', 'CategoryController');
Route::resource('currencies', 'CurrencyController');

Route::get('/currencyrates/{from}/{to}', [
    'as' => 'currencyrates.index',
    'uses' => 'CurrencyRateController@index'
]);
Route::resource('currencyrates',
    'CurrencyRateController',
    [
        'except' => [
            'index'
        ]
    ]);

Route::resource('investmentgroups', 'InvestmentGroupController');
Route::resource('investments', 'InvestmentController');
Route::resource('payees', 'PayeeController');
Route::resource('tags', 'TagController');

Route::get('/transactions/create/standard', 'TransactionController@createStandard');
Route::get('/transactions/create/investment', 'TransactionController@createInvestment');
Route::post('/transactions/standard', 'TransactionController@storeStandard')->name('transactions.storeStandard');
Route::post('/transactions/investment', 'TransactionController@storeInvestment')->name('transactions.storeInvestment');
Route::resource('transactions',
    'TransactionController',
    [
        'only' => [
            'delete'
        ]
    ]);