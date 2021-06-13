<?php

use Illuminate\Support\Facades\Route;

Route::get('/assets/account', 'App\Http\Controllers\API\AccountApiController@getList');
Route::get('/assets/account/currency/{account}', 'App\Http\Controllers\API\AccountApiController@getAccountCurrencyLabel');

Route::get('/assets/category', 'App\Http\Controllers\API\CategoryApiController@getList');

Route::get('/assets/investment', 'App\Http\Controllers\API\InvestmentApiController@getList');
Route::get('/assets/investment/suffix/{investment}', 'App\Http\Controllers\API\InvestmentApiController@getCurrencySuffix');

Route::get('/assets/payee', 'App\Http\Controllers\API\PayeeApiController@getList');
Route::get('/assets/get_default_category_for_payee', 'App\Http\Controllers\API\PayeeApiController@getDefaultCategoryForPayee');

Route::get('/assets/tag', 'App\Http\Controllers\API\TagApiController@getList');

Route::put('/transaction/{transaction}/reconciled/{newState}', 'App\Http\Controllers\API\TransactionApiController@reconcile');
