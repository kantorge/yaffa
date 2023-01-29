<?php

use App\Http\Controllers\API\AccountController;
use App\Http\Controllers\API\CategoryController;
use App\Http\Controllers\API\OnboardingApiController;
use App\Http\Controllers\API\PayeeApiController;
use App\Http\Controllers\API\ReportApiController;
use App\Http\Controllers\API\TransactionApiController;
use Illuminate\Support\Facades\Route;

Route::get('/assets/account', 'App\Http\Controllers\API\AccountController@getList');
Route::get('/assets/account/standard', 'App\Http\Controllers\API\AccountController@getStandardList');
Route::get('/assets/account/investment', [AccountController::class, 'getAccountListForInvestments']);
Route::get('/assets/account/{accountEntity}', [AccountController::class, 'getItem']);
Route::get('/assets/account/currency/{accountEntity}', 'App\Http\Controllers\API\AccountController@getAccountCurrency');
Route::get('/account/balance/{accountEntity?}', [AccountController::class, 'getAccountBalance']);

Route::put('/assets/accountentity/{accountEntity}/active/{active}', 'App\Http\Controllers\API\AccountEntityApiController@updateActive')->name('api.accountentity.updateActive');

Route::get('/assets/category', [CategoryController::class,'getList']);
Route::get('/assets/categories', [CategoryController::class,'getFullList']);
Route::put('/assets/category/{category}/active/{active}', [CategoryController::class, 'updateActive'])->name('api.category.updateActive');
Route::get('/assets/category/{category}', [CategoryController::class,'getItem']);

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

Route::get('/assets/get_default_category_suggestion', 'App\Http\Controllers\API\PayeeApiController@getPayeeDefaultSuggestion');
Route::get('/assets/dismiss_default_category_suggestion/{accountEntity}', 'App\Http\Controllers\API\PayeeApiController@dismissPayeeDefaultCategorySuggestion');
Route::get('/assets/accept_default_category_suggestion/{accountEntity}/{category}', 'App\Http\Controllers\API\PayeeApiController@acceptPayeeDefaultCategorySuggestion');

Route::get('/assets/tag', 'App\Http\Controllers\API\TagApiController@getList');
Route::get('/assets/tag/{tag}', 'App\Http\Controllers\API\TagApiController@getItem');

Route::get('/budgetchart', 'App\Http\Controllers\API\ReportApiController@budgetChart');
Route::get('/reports/waterfall/{transactionType}/{dataType}/{year}/{month?}', [ReportApiController::class, 'getCategoryWaterfallData'])
    ->where('transactionType', 'standard|investment|all')
    ->where('type', 'budget|result|all');

Route::get('/transactions', [TransactionApiController::class, 'findTransactions']);

Route::get('/transactions/get_scheduled_items/{type}', [TransactionApiController::class,'getScheduledItems'])
    ->where('type', 'schedule|schedule_only|budget|budget_only|any|both|none');

Route::post('/transactions/standard', [TransactionApiController::class, 'storeStandard'])->name('api.transactions.storeStandard');
Route::patch('/transactions/standard/{transaction}', [TransactionApiController::class, 'updateStandard'])->name('api.transactions.updateStandard');
Route::patch('/transactions/{transaction}/skip', [TransactionApiController::class, 'skipScheduleInstance'])->name('api.transactions.skipScheduleInstance');

Route::get('/transaction/{transaction}', 'App\Http\Controllers\API\TransactionApiController@getItem');

Route::put('/transaction/{transaction}/reconciled/{newState}', [TransactionApiController::class, 'reconcile']);

Route::delete('/transaction/{transaction}', [TransactionApiController::class, 'destroy'])->name('api.transactions.destroy');

Route::get('/onboarding', [OnboardingApiController::class, 'getOnboardingData']);
Route::put('/onboadding/dismiss', [OnboardingApiController::class, 'setDismissedFlag']);

/*
Route::post('/token', function (Request $request) {
    $user = \App\Models\User::first();
    $token = $user->createToken('API token');

    return ['token' => $token->plainTextToken];
});
*/
