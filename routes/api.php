<?php

use Illuminate\Support\Facades\Route;

Route::get('/assets/account/standard', 'App\Http\Controllers\API\AccountApiController@getStandardList');
Route::get('/assets/account/investment', 'App\Http\Controllers\API\AccountApiController@getInvestmentList');
Route::get('/assets/account/{account}', 'App\Http\Controllers\API\AccountApiController@getItem');
Route::get('/assets/account/currency/{account}', 'App\Http\Controllers\API\AccountApiController@getAccountCurrencyLabel');

Route::get('/assets/category', 'App\Http\Controllers\API\CategoryApiController@getList');
Route::get('/assets/category/{category}', 'App\Http\Controllers\API\CategoryApiController@getItem');

Route::get('/assets/investment', 'App\Http\Controllers\API\InvestmentApiController@getList');
Route::get('/assets/investment/{investment}', 'App\Http\Controllers\API\InvestmentApiController@getInvestmentDetails')->name('investment.getDetails');
Route::get('/assets/investment/suffix/{investment}', 'App\Http\Controllers\API\InvestmentApiController@getCurrencySuffix');
Route::get('/assets/investment/price/{investment}', 'App\Http\Controllers\API\InvestmentApiController@getPriceHistory');

Route::get('/assets/payee', 'App\Http\Controllers\API\PayeeApiController@getList');
Route::get('/assets/get_default_category_for_payee', 'App\Http\Controllers\API\PayeeApiController@getDefaultCategoryForPayee');

Route::get('/assets/tag', 'App\Http\Controllers\API\TagApiController@getList');

Route::put('/transaction/{transaction}/reconciled/{newState}', 'App\Http\Controllers\API\TransactionApiController@reconcile');
