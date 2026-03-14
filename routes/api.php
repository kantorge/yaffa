<?php

use App\Http\Controllers\API\AccountApiController;
use App\Http\Controllers\API\AccountEntityApiController;
use App\Http\Controllers\API\AccountGroupApiController;
use App\Http\Controllers\API\AiDocumentApiController;
use App\Http\Controllers\API\AiProviderConfigApiController;
use App\Http\Controllers\API\AiUserSettingsApiController;
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

    // AiUserSettings endpoints
    Route::get('/ai/settings', [AiUserSettingsApiController::class, 'show'])
        ->name('ai.settings.show');
    Route::patch('/ai/settings', [AiUserSettingsApiController::class, 'update'])
        ->name('ai.settings.update');

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

    // AI Document endpoints
    Route::post('/documents', [AiDocumentApiController::class, 'store'])
        ->name('documents.store');
    Route::get('/documents', [AiDocumentApiController::class, 'index'])
        ->name('documents.index');
    Route::get('/documents/{aiDocument}', [AiDocumentApiController::class, 'show'])
        ->name('documents.show');
    Route::patch('/documents/{aiDocument}', [AiDocumentApiController::class, 'update'])
        ->name('documents.update');
    Route::post('/documents/{aiDocument}/reprocess', [AiDocumentApiController::class, 'reprocess'])
        ->name('documents.reprocess');
    Route::post('/documents/{aiDocument}/check-duplicates', [AiDocumentApiController::class, 'checkDuplicates'])
        ->name('documents.checkDuplicates');
    Route::delete('/documents/{aiDocument}', [AiDocumentApiController::class, 'destroy'])
        ->name('documents.destroy');

    // Account endpoints
    Route::get('/accounts', [AccountApiController::class, 'getList'])
        ->name('accounts.index');
    Route::get('/accounts/investment', [AccountApiController::class, 'getAccountListForInvestments'])
        ->name('accounts.investment');
    Route::get('/accounts/balance', [AccountApiController::class, 'getAccountBalance'])
        ->name('accounts.balance');
    Route::get('/accounts/{accountEntity}/balance', [AccountApiController::class, 'getAccountBalance'])
        ->whereNumber('accountEntity')
        ->name('accounts.balance.show');
    Route::get('/accounts/{accountEntity}', [AccountApiController::class, 'getItem'])
        ->whereNumber('accountEntity')
        ->name('accounts.show');
    Route::post('/accounts/{accountEntity}/monthly-summary', [AccountApiController::class, 'recalculateMonthlySummary'])
        ->whereNumber('accountEntity')
        ->name('accounts.monthly-summary');

    // AccountEntity endpoints
    Route::patch('/account-entities/{accountEntity}', [AccountEntityApiController::class, 'patchActive'])
        ->name('account-entities.patch-active');
    Route::delete('/account-entities/{accountEntity}', [AccountEntityApiController::class, 'destroy'])
        ->name('account-entities.destroy');

    // AccountGroup endpoints
    Route::delete('/account-groups/{accountGroup}', [AccountGroupApiController::class, 'destroy'])
        ->name('account-groups.destroy');

    // Category endpoints
    Route::get('/categories', [CategoryApiController::class, 'getList'])
        ->name('categories.index');
    Route::post('/categories', [CategoryApiController::class, 'store'])
        ->name('categories.store');
    Route::get('/categories/{category}', [CategoryApiController::class, 'getItem'])
        ->name('categories.show');
    Route::patch('/categories/{category}', [CategoryApiController::class, 'patchActive'])
        ->name('categories.patch-active');
    Route::delete('/categories/{category}', [CategoryApiController::class, 'destroy'])
        ->name('categories.destroy');

    // Investment endpoints
    Route::get('/investments', [InvestmentApiController::class, 'index'])
        ->name('investments.index');
    Route::get('/investments/timeline', [InvestmentApiController::class, 'getInvestmentsWithTimeline'])
        ->name('investments.timeline');
    Route::get('/investments/{investment}', [InvestmentApiController::class, 'getInvestmentDetails'])
        ->name('investments.show');
    Route::patch('/investments/{investment}', [InvestmentApiController::class, 'patchActive'])
        ->name('investments.patch-active');
    Route::get('/investments/{investment}/price-history', [InvestmentApiController::class, 'getPriceHistory'])
        ->name('investments.price-history');
    Route::delete('/investments/{investment}', [InvestmentApiController::class, 'destroy'])
        ->name('investments.destroy');

    // InvestmentGroup endpoints
    Route::delete('/investment-groups/{investmentGroup}', [InvestmentGroupApiController::class, 'destroy'])
        ->name('investment-groups.destroy');

    // Payee endpoints
    Route::get('/payees', [PayeeApiController::class, 'getList'])
        ->name('payees.index');
    Route::post('/payees', [PayeeApiController::class, 'storePayee'])
        ->name('payees.store');
    Route::get('/payees/similar', [PayeeApiController::class, 'getSimilarPayees'])
        ->name('payees.similar');
    Route::get('/payees/category-suggestions/default', [PayeeApiController::class, 'getPayeeDefaultSuggestion'])
        ->name('payees.category-suggestions.default');
    Route::get('/payees/{accountEntity}', [PayeeApiController::class, 'getItem'])
        ->name('payees.show');
    Route::post('/payees/{accountEntity}/category-suggestions/accept/{category}', [PayeeApiController::class, 'acceptPayeeDefaultCategorySuggestion'])
        ->name('payees.category-suggestions.accept');
    Route::post('/payees/{accountEntity}/category-suggestions/dismiss', [PayeeApiController::class, 'dismissPayeeDefaultCategorySuggestion'])
        ->name('payees.category-suggestions.dismiss');
    Route::get('/payees/{accountEntity}/category-stats', [PayeeStatsApiController::class, 'categoryStats'])
        ->name('payees.category-stats');

    // Tag endpoints
    Route::get('/tags', [TagApiController::class, 'getList'])
        ->name('tags.index');
    Route::get('/tags/{tag}', [TagApiController::class, 'getItem'])
        ->name('tags.show');
    Route::patch('/tags/{tag}', [TagApiController::class, 'patchActive'])
        ->name('tags.patch-active');

    // Transaction endpoints
    Route::get('/transactions', [TransactionApiController::class, 'findTransactions'])
        ->name('transactions.index');
    Route::get('/transactions/scheduled-items', [TransactionApiController::class, 'getScheduledItems'])
        ->name('transactions.scheduled-items');
    Route::post('/transactions/standard', [TransactionApiController::class, 'storeStandard'])
        ->name('transactions.store-standard');
    Route::post('/transactions/investment', [TransactionApiController::class, 'storeInvestment'])
        ->name('transactions.store-investment');
    Route::patch('/transactions/standard/{transaction}', [TransactionApiController::class, 'updateStandard'])
        ->name('transactions.update-standard');
    Route::patch('/transactions/investment/{transaction}', [TransactionApiController::class, 'updateInvestment'])
        ->name('transactions.update-investment');
    Route::patch('/transactions/{transaction}/skip', [TransactionApiController::class, 'skipScheduleInstance'])
        ->name('transactions.skip');
    Route::get('/transactions/{transaction}', [TransactionApiController::class, 'getItem'])
        ->name('transactions.show');
    Route::patch('/transactions/{transaction}/reconciliation', [TransactionApiController::class, 'reconcile'])
        ->name('transactions.reconcile');
    Route::delete('/transactions/{transaction}', [TransactionApiController::class, 'destroy'])
        ->name('transactions.destroy');

    // Report endpoints
    Route::get('/reports/budget-chart', [ReportApiController::class, 'budgetChart'])
        ->name('reports.budget-chart');
    Route::get('/reports/cashflow', [ReportApiController::class, 'getCashflowData'])
        ->name('reports.cashflow');
    Route::get(
        '/reports/waterfall/{transactionType}/{dataType}/{year}/{month?}',
        [ReportApiController::class, 'getCategoryWaterfallData']
    )
        ->where('transactionType', 'standard|investment|all')
        ->where('dataType', 'budget|result|all')
        ->name('reports.waterfall');

    // Onboarding endpoints
    Route::get('/onboarding/{topic}', [OnboardingApiController::class, 'getOnboardingData'])
        ->name('onboarding.show');
    Route::post('/onboarding/{topic}/dismiss', [OnboardingApiController::class, 'setDismissedFlag'])
        ->name('onboarding.dismiss');
    Route::post('/onboarding/{topic}/complete-tour', [OnboardingApiController::class, 'setCompletedTourFlag'])
        ->name('onboarding.complete-tour');

    // User/settings endpoints
    Route::patch('/users/me/settings', [UserApiController::class, 'updateSettings'])
        ->name('users.me.settings');
    Route::patch('/users/me/password', [UserApiController::class, 'changePassword'])
        ->name('users.me.password');
    Route::get('/users/me/preferences/{key}', [UserApiController::class, 'getPreference'])
        ->name('users.me.preferences.get');
    Route::put('/users/me/preferences/{key}', [UserApiController::class, 'setPreference'])
        ->name('users.me.preferences.set');
});
