<?php

use App\Http\Controllers\API\AccountApiController;
use App\Http\Controllers\API\AccountEntityApiController;
use App\Http\Controllers\API\AccountGroupApiController;
use App\Http\Controllers\API\AiDocumentApiController;
use App\Http\Controllers\API\AiProviderConfigApiController;
use App\Http\Controllers\API\CategoryApiController;
use App\Http\Controllers\API\CurrencyRateApiController;
use App\Http\Controllers\API\GoogleDriveConfigApiController;
use App\Http\Controllers\API\InvestmentApiController;
use App\Http\Controllers\API\InvestmentGroupApiController;
use App\Http\Controllers\API\InvestmentPriceApiController;
use App\Http\Controllers\API\OnboardingApiController;
use App\Http\Controllers\API\PayeeApiController;
use App\Http\Controllers\API\PayeeStatsApiController;
use App\Http\Controllers\API\ReportApiController;
use App\Http\Controllers\API\TagApiController;
use App\Http\Controllers\API\TransactionApiController;
use App\Http\Controllers\API\UserApiController;
use Illuminate\Support\Facades\Route;

// ============================================================
// API V1 - Versioned, resource-oriented routes
// ============================================================
Route::prefix('v1')->name('api.v1.')->group(function () {
    // CurrencyRate endpoints
    Route::get('/currency-rates/{from}/{to}', [CurrencyRateApiController::class, 'index'])
        ->name('currency-rates.index');
    Route::post('/currency-rates', [CurrencyRateApiController::class, 'store'])
        ->name('currency-rates.store');
    Route::put('/currency-rates/{currencyRate}', [CurrencyRateApiController::class, 'update'])
        ->name('currency-rates.update');
    Route::delete('/currency-rates/{currencyRate}', [CurrencyRateApiController::class, 'destroy'])
        ->name('currency-rates.destroy');
    Route::post('/currency-rates/{currency}/retrieve-missing', [CurrencyRateApiController::class, 'retrieveMissingCurrencyRateToBase'])
        ->name('currency-rates.retrieve-missing');

    // InvestmentPrice endpoints
    Route::get('/investment-prices/{investment}', [InvestmentPriceApiController::class, 'index'])
        ->name('investment-prices.index');
    Route::post('/investment-prices', [InvestmentPriceApiController::class, 'store'])
        ->name('investment-prices.store');
    Route::put('/investment-prices/{investmentPrice}', [InvestmentPriceApiController::class, 'update'])
        ->name('investment-prices.update');
    Route::delete('/investment-prices/{investmentPrice}', [InvestmentPriceApiController::class, 'destroy'])
        ->name('investment-prices.destroy');
    Route::post('/investment-prices/{investment}/retrieve-missing', [InvestmentPriceApiController::class, 'retrieveMissingPrices'])
        ->name('investment-prices.retrieve-missing');
    Route::get('/investment-prices/{investment}/check', [InvestmentPriceApiController::class, 'checkPrice'])
        ->name('investment-prices.check');

    // AiProviderConfig endpoints
    Route::get('/ai/config', [AiProviderConfigApiController::class, 'show'])
        ->name('ai.config.show');
    Route::post('/ai/config', [AiProviderConfigApiController::class, 'store'])
        ->name('ai.config.store');
    Route::patch('/ai/config/{aiProviderConfig}', [AiProviderConfigApiController::class, 'update'])
        ->name('ai.config.update');
    Route::delete('/ai/config/{aiProviderConfig}', [AiProviderConfigApiController::class, 'destroy'])
        ->name('ai.config.destroy');
    Route::post('/ai/config/test', [AiProviderConfigApiController::class, 'test'])
        ->name('ai.config.test');

    // GoogleDriveConfig endpoints
    Route::get('/google-drive/config', [GoogleDriveConfigApiController::class, 'show'])
        ->name('google-drive.config.show');
    Route::post('/google-drive/config', [GoogleDriveConfigApiController::class, 'store'])
        ->name('google-drive.config.store');
    Route::patch('/google-drive/config/{googleDriveConfig}', [GoogleDriveConfigApiController::class, 'update'])
        ->name('google-drive.config.update');
    Route::delete('/google-drive/config/{googleDriveConfig}', [GoogleDriveConfigApiController::class, 'destroy'])
        ->name('google-drive.config.destroy');
    Route::post('/google-drive/config/test', [GoogleDriveConfigApiController::class, 'test'])
        ->name('google-drive.config.test');
    Route::post('/google-drive/config/{googleDriveConfig}/sync', [GoogleDriveConfigApiController::class, 'sync'])
        ->name('google-drive.config.sync');
});

// ============================================================
// Legacy routes (kept for backward compatibility during migration)
// ============================================================

Route::get('/assets/account', [AccountApiController::class, 'getList']);
Route::get('/assets/account/investment', [AccountApiController::class, 'getAccountListForInvestments']);
Route::get('/assets/account/{accountEntity}', [AccountApiController::class, 'getItem']);
Route::get('/account/balance/{accountEntity?}', [AccountApiController::class, 'getAccountBalance']);
Route::put('/account/monthlySummary/{accountEntity}', [AccountApiController::class, 'updateMonthlySummary'])
    ->name('api.account.updateMonthlySummary');

Route::put('/assets/accountentity/{accountEntity}/active/{active}', [AccountEntityApiController::class, 'updateActive'])
    ->name('api.accountentity.updateActive');
Route::delete('/assets/accountentity/{accountEntity}', [AccountEntityApiController::class, 'destroy'])
    ->name('api.accountentity.destroy');

Route::delete('/assets/accountgroup/{accountGroup}', [AccountGroupApiController::class, 'destroy'])
    ->name('api.accountgroup.destroy');

Route::get('/assets/category', [CategoryApiController::class, 'getList']);
Route::get('/assets/categories', [CategoryApiController::class, 'getFullList']);
Route::post('/assets/category', [CategoryApiController::class, 'store'])->name('api.category.store');
Route::put('/assets/category/{category}/active/{active}', [CategoryApiController::class, 'updateActive'])
    ->name('api.category.updateActive');
Route::get('/assets/category/{category}', [CategoryApiController::class, 'getItem']);
Route::delete('/assets/category/{category}', [CategoryApiController::class, 'destroy'])
    ->name('api.category.destroy');

Route::get('/currency-rates/{from}/{to}', [CurrencyRateApiController::class, 'index'])
    ->name('api.currency-rate.index');
Route::post('/currency-rates', [CurrencyRateApiController::class, 'store'])
    ->name('api.currency-rate.store');
Route::put('/currency-rates/{currency_rate}', [CurrencyRateApiController::class, 'update'])
    ->name('api.currency-rate.update');
Route::delete('/currency-rates/{currency_rate}', [CurrencyRateApiController::class, 'destroy'])
    ->name('api.currency-rate.destroy');
Route::get('/currencyrates/missing/{currency}', [CurrencyRateApiController::class, 'retrieveMissingCurrencyRateToBase'])
    ->name('api.currency-rate.retrieveMissing');

Route::get('/assets/investment', [InvestmentApiController::class, 'index']);
Route::get('/assets/investment/timeline', [InvestmentApiController::class, 'getInvestmentsWithTimeline']);
Route::get('/assets/investment/{investment}', [InvestmentApiController::class, 'getInvestmentDetails'])
    ->name('investment.getDetails');
Route::delete('/assets/investment/{investment}', [InvestmentApiController::class, 'destroy'])
    ->name('api.investment.destroy');
Route::get('/assets/investment/price/{investment}', [InvestmentApiController::class, 'getPriceHistory']);
Route::put('/assets/investment/{investment}/active/{active}', [InvestmentApiController::class, 'updateActive'])
    ->name('api.investment.updateActive');

Route::delete('/assets/investmentgroup/{investmentGroup}', [InvestmentGroupApiController::class, 'destroy'])
    ->name('api.investmentgroup.destroy');

Route::get('/investment-prices/{investment}', [InvestmentPriceApiController::class, 'index'])
    ->name('api.investment-price.index');
Route::post('/investment-prices', [InvestmentPriceApiController::class, 'store'])
    ->name('api.investment-price.store');
Route::put('/investment-prices/{investment_price}', [InvestmentPriceApiController::class, 'update'])
    ->name('api.investment-price.update');
Route::delete('/investment-prices/{investment_price}', [InvestmentPriceApiController::class, 'destroy'])
    ->name('api.investment-price.destroy');
Route::get('/investment-prices/missing/{investment}', [InvestmentPriceApiController::class, 'retrieveMissingPrices'])
    ->name('api.investment-price.retrieveMissing');
Route::get('/investment-prices/check/{investment}', [InvestmentPriceApiController::class, 'checkPrice'])
    ->name('api.investment-price.checkPrice');

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
Route::put('/assets/tag/{tag}/active/{active}', [TagApiController::class, 'updateActive'])
    ->name('api.tag.updateActive');

Route::get('/budgetchart', [ReportApiController::class, 'budgetChart'])->name('api.reports.budgetchart');
Route::get(
    '/reports/waterfall/{transactionType}/{dataType}/{year}/{month?}',
    [ReportApiController::class, 'getCategoryWaterfallData']
)
    ->where('transactionType', 'standard|investment|all')
    ->where('type', 'budget|result|all');
Route::get('/reports/cashflow', [ReportApiController::class, 'getCashflowData'])->name('api.reports.cashflow');

Route::get('/transactions', [TransactionApiController::class, 'findTransactions']);

Route::get('/transactions/get_scheduled_items/{type}', [TransactionApiController::class, 'getScheduledItems'])
    ->where('type', 'schedule|schedule_only|budget|budget_only|any|both|none')
    ->name('api.transactions.getScheduledItems');

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

Route::get('/onboarding/{topic}', [OnboardingApiController::class, 'getOnboardingData']);
Route::put('/onboarding/{topic}/dismiss', [OnboardingApiController::class, 'setDismissedFlag']);
Route::put('/onboarding/{topic}/complete-tour', [OnboardingApiController::class, 'setCompletedTourFlag']);

Route::patch('/user/settings', [UserApiController::class, 'updateSettings'])
    ->name('user.settings.update');
Route::patch('/user/change_password', [UserApiController::class, 'changePassword'])
    ->name('user.change_password');
Route::get('/user/preference/{key}', [UserApiController::class, 'getPreference'])
    ->name('api.user.preference.get');
Route::put('/user/preference/{key}', [UserApiController::class, 'setPreference'])
    ->name('api.user.preference.set');
// AI Document routes
Route::post('/documents', [AiDocumentApiController::class, 'store'])
    ->name('api.documents.store');
Route::get('/documents', [AiDocumentApiController::class, 'index'])
    ->name('api.documents.index');
Route::get('/documents/{aiDocument}', [AiDocumentApiController::class, 'show'])
    ->name('api.documents.show');
Route::patch('/documents/{aiDocument}', [AiDocumentApiController::class, 'update'])
    ->name('api.documents.update');
Route::post('/documents/{aiDocument}/reprocess', [AiDocumentApiController::class, 'reprocess'])
    ->name('api.documents.reprocess');
Route::post('/documents/{aiDocument}/check-duplicates', [AiDocumentApiController::class, 'checkDuplicates'])
    ->name('api.documents.checkDuplicates');
Route::delete('/documents/{aiDocument}', [AiDocumentApiController::class, 'destroy'])
    ->name('api.documents.destroy');

// Payee stats routes
Route::get('/ai/payees/{payee}/category-stats', [PayeeStatsApiController::class, 'categoryStats'])
    ->name('api.payee-stats.category-stats');
