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

Route::get('/assets/account', 'API\AccountApiController@getList');
Route::get('/assets/get_account_currency', 'API\AccountApiController@getAccountCurrencyLabel');

Route::get('/assets/category', 'API\CategoryApiController@getList');

Route::get('/assets/payee', 'API\PayeeApiController@getList');
Route::get('/assets/get_default_category_for_payee', 'API\PayeeApiController@getDefaultCategoryForPayee');

Route::get('/assets/tag', 'API\TagApiController@getList');