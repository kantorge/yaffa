<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

/* TODO: ez kell valamire
Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});
*/

Route::get('/assets/account', 'App\Http\Controllers\API\AccountApiController@getList');
Route::get('/assets/get_account_currency', 'App\Http\Controllers\API\AccountApiController@getAccountCurrencyLabel');

Route::get('/assets/category', 'App\Http\Controllers\API\CategoryApiController@getList');

Route::get('/assets/investment', 'App\Http\Controllers\API\InvestmentApiController@getList');

Route::get('/assets/payee', 'App\Http\Controllers\API\PayeeApiController@getList');
Route::get('/assets/get_default_category_for_payee', 'App\Http\Controllers\API\PayeeApiController@getDefaultCategoryForPayee');

Route::get('/assets/tag', 'App\Http\Controllers\API\TagApiController@getList');

Route::put('/transaction/{transaction}/reconciled/{newState}', 'App\Http\Controllers\API\TransactionApiController@reconcile');
