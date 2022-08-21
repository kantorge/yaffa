<?php

use App\Http\Controllers\API\AccountApiController;
use App\Http\Controllers\API\PayeeApiController;
use App\Http\Controllers\API\TransactionApiController;
use Illuminate\Support\Facades\Route;

Route::get('/assets/account', 'App\Http\Controllers\API\AccountApiController@getList');
Route::get('/assets/account/standard', 'App\Http\Controllers\API\AccountApiController@getStandardList');
Route::get('/assets/account/investment', [AccountApiController::class, 'getAccountListForInvestments']);
Route::get('/assets/account/{accountEntity}', [AccountApiController::class, 'getItem']);
Route::get('/assets/account/currency/{accountEntity}', 'App\Http\Controllers\API\AccountApiController@getAccountCurrency');

Route::put('/assets/accountentity/{accountEntity}/active/{active}', 'App\Http\Controllers\API\AccountEntityApiController@updateActive')->name('api.accountentity.updateActive');

Route::get('/assets/category', 'App\Http\Controllers\API\CategoryApiController@getList');
Route::get('/assets/category/{category}', 'App\Http\Controllers\API\CategoryApiController@getItem');

Route::get('/assets/investment', 'App\Http\Controllers\API\InvestmentApiController@getList');
Route::get('/assets/investment/timeline', 'App\Http\Controllers\API\InvestmentApiController@getInvestmentsWithTimeline');
Route::get('/assets/investment/{investment}', 'App\Http\Controllers\API\InvestmentApiController@getInvestmentDetails')->name('investment.getDetails');
Route::get('/assets/investment/suffix/{investment}', 'App\Http\Controllers\API\InvestmentApiController@getCurrencySuffix');
Route::get('/assets/investment/price/{investment}', 'App\Http\Controllers\API\InvestmentApiController@getPriceHistory');
Route::put('/assets/investment/{investment}/active/{active}', 'App\Http\Controllers\API\InvestmentApiController@updateActive')->name('api.investment.updateActive');

Route::get('/assets/payee', 'App\Http\Controllers\API\PayeeApiController@getList');
Route::post('/assets/payee', 'App\Http\Controllers\API\PayeeApiController@storePayee')->name('api.payee.store');
Route::get('/assets/payee/similar', 'App\Http\Controllers\API\PayeeApiController@getSimilarPayees')->name('api.payee.similar');
Route::get('/assets/payee/{accountEntity}', [PayeeApiController::class, 'getItem']);

Route::get('/assets/get_default_category_for_payee', 'App\Http\Controllers\API\PayeeApiController@getDefaultCategoryForPayee');
Route::get('/assets/get_default_category_suggestion', 'App\Http\Controllers\API\PayeeApiController@getPayeeDefaultSuggestion');
Route::get('/assets/dismiss_default_category_suggestion/{accountEntity}', 'App\Http\Controllers\API\PayeeApiController@dismissPayeeDefaultCategorySuggestion');
Route::get('/assets/accept_default_category_suggestion/{accountEntity}/{category}', 'App\Http\Controllers\API\PayeeApiController@acceptPayeeDefaultCategorySuggestion');

Route::get('/assets/tag', 'App\Http\Controllers\API\TagApiController@getList');
Route::get('/assets/tag/{tag}', 'App\Http\Controllers\API\TagApiController@getItem');

Route::get('/budgetchart', 'App\Http\Controllers\API\ReportApiController@budgetChart');
Route::get('/scheduled_transactions', 'App\Http\Controllers\API\ReportApiController@scheduledTransactions');

Route::get('/transactions', 'App\Http\Controllers\API\TransactionApiController@findTransactions');
Route::get(
    '/transactions/get_scheduled_items/{type}',
    'App\Http\Controllers\API\TransactionApiController@getScheduledItems'
)
->where('type', 'schedule|schedule_only|budget|budget_only|any|both|none');
Route::post('/transactions/standard', [TransactionApiController::class, 'storeStandard'])->name('api.transactions.storeStandard');
Route::patch('/transactions/standard/{transaction}', [TransactionApiController::class, 'updateStandard'])->name('api.transactions.updateStandard');
Route::patch('/transactions/{transaction}/skip', [TransactionApiController::class, 'skipScheduleInstance'])->name('api.transactions.skipScheduleInstance');

Route::get('/transaction/{transaction}', 'App\Http\Controllers\API\TransactionApiController@getItem');

Route::put('/transaction/{transaction}/reconciled/{newState}', 'App\Http\Controllers\API\TransactionApiController@reconcile');
