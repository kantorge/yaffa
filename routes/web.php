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

Route::get('/accounts/history/{account}/{withForecast?}', 'MainController@account_details')->name('accounts.history');
Route::get('/accounts/summary/{withClosed?}', 'MainController@index')->name('accounts.summary');
Route::resource('accounts', 'AccountController');

Route::resource('categories', 'CategoryController');
Route::resource('currencies', 'CurrencyController');

Route::get('/currencyrates/{from}/{to}', [
    'as' => 'currencyrates.index',
    'uses' => 'CurrencyRateController@index'
]);
Route::resource('currencyrates', 'CurrencyRateController')
    ->except([
        'index'
    ]);

Route::resource('investmentgroups', 'InvestmentGroupController');
Route::get('/investments/summary', 'InvestmentController@summary')->name('investments.summary');
Route::resource('investments', 'InvestmentController');


Route::resource('payees', 'PayeeController');
Route::resource('tags', 'TagController');

Route::get('/transactions/create/standard', 'TransactionController@createStandard')->name('transactions.createStandard');
Route::get('/transactions/create/investment', 'TransactionController@createInvestment')->name('transactions.createInvestment');
Route::post('/transactions/standard', 'TransactionController@storeStandard')->name('transactions.storeStandard');
Route::post('/transactions/investment', 'TransactionController@storeInvestment')->name('transactions.storeInvestment');
Route::get('/transactions/{transaction}/edit/standard', 'TransactionController@editStandard')->name('transactions.editStandard');
Route::get('/transactions/{transaction}/edit/investment', 'TransactionController@editInvestment')->name('transactions.editInvestment');
Route::get('/transactions/{transaction}/clone/standard', 'TransactionController@cloneStandard')->name('transactions.cloneStandard');
Route::get('/transactions/{transaction}/clone/investment', 'TransactionController@cloneInvestment')->name('transactions.cloneInvestment');
Route::patch('/transactions/{transaction}/standard', 'TransactionController@updateStandard')->name('transactions.updateStandard');
Route::patch('/transactions/{transaction}/investment', 'TransactionController@updateInvestment')->name('transactions.updateInvestment');
Route::patch('/transactions/{transaction}/skip', 'TransactionController@skipScheduleInstance')->name('transactions.skipScheduleInstance');
Route::resource('transactions', 'TransactionController')
    ->only([
        'destroy'
    ]);
