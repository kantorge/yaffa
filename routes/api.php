<?php

use App\Http\Controllers\API\AccountApiController;
use App\Http\Controllers\API\AccountEntityApiController;
use App\Http\Controllers\API\CategoryApiController;
use App\Http\Controllers\API\InvestmentApiController;
use App\Http\Controllers\API\OnboardingApiController;
use App\Http\Controllers\API\PayeeApiController;
use App\Http\Controllers\API\ReportApiController;
use App\Http\Controllers\API\ReceivedMailApiController;
use App\Http\Controllers\API\TagApiController;
use App\Http\Controllers\API\TransactionApiController;
use Illuminate\Support\Facades\Route;

Route::get('/assets/account', [AccountApiController::class, 'getList']);
Route::get('/assets/account/investment', [AccountApiController::class, 'getAccountListForInvestments']);
Route::get('/assets/account/{accountEntity}', [AccountApiController::class, 'getItem']);
Route::get('/account/balance/{accountEntity?}', [AccountApiController::class, 'getAccountBalance']);

Route::put('/assets/accountentity/{accountEntity}/active/{active}', [AccountEntityApiController::class, 'updateActive'])
    ->name('api.accountentity.updateActive');
Route::delete('/assets/accountentity/{accountEntity}', [AccountEntityApiController::class, 'destroy'])
    ->name('api.accountentity.destroy');

Route::get('/assets/category', [CategoryApiController::class, 'getList']);
Route::get('/assets/categories', [CategoryApiController::class, 'getFullList']);
Route::put('/assets/category/{category}/active/{active}', [CategoryApiController::class, 'updateActive'])
    ->name('api.category.updateActive');
Route::get('/assets/category/{category}', [CategoryApiController::class, 'getItem']);
Route::delete('/assets/category/{category}', [CategoryApiController::class, 'destroy'])
    ->name('api.category.destroy');

Route::get('/assets/investment', [InvestmentApiController::class, 'getList']);
Route::get('/assets/investment/timeline', [InvestmentApiController::class, 'getInvestmentsWithTimeline']);
Route::get('/assets/investment/{investment}', [InvestmentApiController::class, 'getInvestmentDetails'])
    ->name('investment.getDetails');
Route::delete('/assets/investment/{investment}', [InvestmentApiController::class, 'destroy'])
    ->name('api.investment.destroy');
Route::get('/assets/investment/price/{investment}', [InvestmentApiController::class, 'getPriceHistory']);
Route::put('/assets/investment/{investment}/active/{active}', [InvestmentApiController::class, 'updateActive'])
    ->name('api.investment.updateActive');

Route::get('/assets/payee', [PayeeApiController::class, 'getList']);
Route::post('/assets/payee', [PayeeApiController::class, 'storePayee'])->name('api.payee.store');
Route::get('/assets/payee/similar', [PayeeApiController::class, 'getSimilarPayees'])->name('api.payee.similar');
Route::get('/assets/payee/{accountEntity}', [PayeeApiController::class, 'getItem']);

Route::get('/assets/get_default_category_suggestion', [PayeeApiController::class, 'getPayeeDefaultSuggestion']);
Route::get(
    '/assets/dismiss_default_category_suggestion/{accountEntity}',
    [PayeeApiController::class, 'dismissPayeeDefaultCategorySuggestion']
);
Route::get(
    '/assets/accept_default_category_suggestion/{accountEntity}/{category}',
    [PayeeApiController::class, 'acceptPayeeDefaultCategorySuggestion']
);

Route::get('/assets/tag', [TagApiController::class, 'getList']);
Route::get('/assets/tag/{tag}', [TagApiController::class, 'getItem']);

Route::get('/budgetchart', [ReportApiController::class, 'budgetChart']);
Route::get(
    '/reports/waterfall/{transactionType}/{dataType}/{year}/{month?}',
    [ReportApiController::class, 'getCategoryWaterfallData']
)
    ->where('transactionType', 'standard|investment|all')
    ->where('type', 'budget|result|all');
Route::get('/reports/cashflow', [ReportApiController::class, 'getCashflowData'])->name('api.reports.cashflow');
Route::get(
    '/reports/account_history_by_month/{accountEntity}/{withForecast?}',
    [ReportApiController::class, 'getAccountHistoryByMonth']
);

Route::patch('/received-mail/{receivedMail}/reset-processed', [ReceivedMailApiController::class, 'resetProcessed'])
    ->name('api.received-mail.reset-processed');
Route::delete('/received-mail/{receivedMail}', [ReceivedMailApiController::class, 'destroy'])
    ->name('api.received-mail.destroy');

Route::get('/transactions', [TransactionApiController::class, 'findTransactions']);

Route::get('/transactions/get_scheduled_items/{type}', [TransactionApiController::class, 'getScheduledItems'])
    ->where('type', 'schedule|schedule_only|budget|budget_only|any|both|none');

Route::post('/transactions/standard', [TransactionApiController::class, 'storeStandard'])
    ->name('api.transactions.storeStandard');
Route::post('/transactions/investment', [TransactionApiController::class, 'storeInvestment'])
    ->name('api.transactions.storeInvestment');
Route::patch('/transactions/standard/{transaction}', [TransactionApiController::class, 'updateStandard'])
    ->name('api.transactions.updateStandard');
Route::patch('/transactions/investment/{transaction}', [TransactionApiController::class, 'updateInvestment'])
    ->name('api.transactions.updateInvestment');
Route::patch('/transactions/{transaction}/skip', [TransactionApiController::class, 'skipScheduleInstance'])
    ->name('api.transactions.skipScheduleInstance');
Route::get('/transaction/{transaction}', [TransactionApiController::class, 'getItem']);
Route::put('/transaction/{transaction}/reconciled/{newState}', [TransactionApiController::class, 'reconcile']);
Route::delete('/transaction/{transaction}', [TransactionApiController::class, 'destroy'])
    ->name('api.transactions.destroy');

Route::get('/onboarding', [OnboardingApiController::class, 'getOnboardingData']);
Route::put('/onboadding/dismiss', [OnboardingApiController::class, 'setDismissedFlag']);
