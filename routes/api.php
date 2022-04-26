<?php

use Illuminate\Support\Facades\Route;

Route::get('/assets/account', 'App\Http\Controllers\API\AccountApiController@getList');
Route::get('/assets/account/standard', 'App\Http\Controllers\API\AccountApiController@getStandardList');
Route::get('/assets/account/investment', 'App\Http\Controllers\API\AccountApiController@getInvestmentList');
Route::get('/assets/account/{accountEntity}', 'App\Http\Controllers\API\AccountApiController@getItem');
Route::get('/assets/account/currency/{accountEntity}', 'App\Http\Controllers\API\AccountApiController@getAccountCurrencyLabel');

Route::put('/assets/accountentity/{accountEntity}/active/{active}', 'App\Http\Controllers\API\AccountEntityApiController@updateActive')->name('api.accountentity.updateActive');

Route::get('/assets/category', 'App\Http\Controllers\API\CategoryApiController@getList');
Route::get('/assets/category/{category}', 'App\Http\Controllers\API\CategoryApiController@getItem');

Route::get('/assets/investment', 'App\Http\Controllers\API\InvestmentApiController@getList');
Route::get('/assets/investment/{investment}', 'App\Http\Controllers\API\InvestmentApiController@getInvestmentDetails')->name('investment.getDetails');
Route::get('/assets/investment/suffix/{investment}', 'App\Http\Controllers\API\InvestmentApiController@getCurrencySuffix');
Route::get('/assets/investment/price/{investment}', 'App\Http\Controllers\API\InvestmentApiController@getPriceHistory');

Route::get('/assets/payee', 'App\Http\Controllers\API\PayeeApiController@getList');
Route::post('/assets/payee', 'App\Http\Controllers\API\PayeeApiController@storePayee')->name('api.payee.store');
Route::get('/assets/payee/similar', 'App\Http\Controllers\API\PayeeApiController@getSimilarPayees')->name('api.payee.similar');

Route::get('/assets/get_default_category_for_payee', 'App\Http\Controllers\API\PayeeApiController@getDefaultCategoryForPayee');
Route::get('/assets/get_default_category_suggestion', 'App\Http\Controllers\API\PayeeApiController@getPayeeDefaultSuggestion');
Route::get('/assets/dismiss_default_category_suggestion/{accountEntity}', 'App\Http\Controllers\API\PayeeApiController@dismissPayeeDefaultCategorySuggestion');
Route::get('/assets/accept_default_category_suggestion/{accountEntity}/{category}', 'App\Http\Controllers\API\PayeeApiController@acceptPayeeDefaultCategorySuggestion');

Route::get('/assets/tag', 'App\Http\Controllers\API\TagApiController@getList');

Route::get('/budgetchart', 'App\Http\Controllers\API\ReportController@budgetChart');
Route::get('/scheduled_transactions', 'App\Http\Controllers\API\ReportController@scheduledTransactions');

Route::get('/transactions', 'App\Http\Controllers\API\TransactionApiController@findTransactions');
Route::get('/transactions/get_scheduled_items', 'App\Http\Controllers\API\TransactionApiController@getScheduledItems');
Route::get('/transaction/{transaction}', 'App\Http\Controllers\API\TransactionApiController@getItem');

Route::put('/transaction/{transaction}/reconciled/{newState}', 'App\Http\Controllers\API\TransactionApiController@reconcile');
