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