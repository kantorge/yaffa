<?php

use App\Http\Controllers\AccountEntityController;
use App\Http\Controllers\AccountGroupController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CurrencyController;
use App\Http\Controllers\CurrencyRateController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\InvestmentController;
use App\Http\Controllers\InvestmentGroupController;
use App\Http\Controllers\InvestmentPriceController;
use App\Http\Controllers\MainController;
use App\Http\Controllers\ReceivedMailController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\TagController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\TransactionImportRuleController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VerificationController;
use App\Mail\TransactionCreatedFromEmail;
use App\Models\ReceivedMail;
use Illuminate\Support\Facades\Route;
// MoneyHub Transaction Upload

// Investment CSV Upload routes
Route::get('/imports/fuel-ventures', [\App\Http\Controllers\InvestmentCsvUploadController::class, 'showForm'])->name('investment.upload_csv');
Route::post('/investment/upload-csv', [\App\Http\Controllers\InvestmentCsvUploadController::class, 'handleUpload']);
// Investment statement multi-upload (WiseAlpha)
Route::get('/imports/wisealpha', [\App\Http\Controllers\InvestmentStatementUploadController::class, 'showForm'])->name('investment.upload_statements')->middleware(['auth', 'verified']);
Route::post('/investment/upload-statements', [\App\Http\Controllers\InvestmentStatementUploadController::class, 'handleUpload'])->name('investment.upload_statements.handle')->middleware(['auth', 'verified']);
// Backwards-compatible singular path: redirect to the plural route to avoid 404s
Route::get('/investment/upload-statement', function () {
    return redirect()->route('investment.upload_statements');
})->name('investment.upload_statement')->middleware(['auth', 'verified']);
Route::post('/investment/upload-statement', function () {
    return redirect()->route('investment.upload_statements');
})->middleware(['auth', 'verified']);
// Payslip upload routes
Route::get('/imports/payslip', [\App\Http\Controllers\PayslipUploadController::class, 'showForm'])->name('payslip.upload')->middleware(['auth', 'verified']);
Route::post('/payslip/upload', [\App\Http\Controllers\PayslipUploadController::class, 'handleUpload'])->name('payslip.upload.handle')->middleware(['auth', 'verified']);
// Import job status endpoint
Route::get('/imports/{import}/status', [\App\Http\Controllers\ImportController::class, 'importStatus'])
    ->name('imports.status');
Route::get('/imports/{import}/errors', [\App\Http\Controllers\ImportController::class, 'importErrors'])
    ->name('imports.errors');

// List past imports for the user
Route::get('/imports', [\App\Http\Controllers\ImportController::class, 'index'])->name('imports.index')->middleware(['auth', 'verified']);

/*********************
 * Generic routes
 ********************/
Route::view('/', 'pages.dashboard')->middleware(['auth', 'verified'])->name('home');
Route::view('/terms', 'pages.sandbox-terms')->name('terms');

/*********************
 * Account and payee related routes
 ********************/
Route::resource('account-group', AccountGroupController::class)->except(['show']);

Route::resource('account-entity', AccountEntityController::class)
    // Destroy is expected to be handled only using the AccountEntityApiController
    ->except(['destroy']);

Route::get('/account-entity/{accountEntity}/transactions', [AccountEntityController::class, 'getTransactions'])
    ->name('account-entity.transactions');

Route::get('/account/history/{account}/{withForecast?}', [MainController::class, 'account_details'])
    ->name('account.history');

Route::get('/account/{account}/batch-entry/investment', [TransactionController::class, 'batchEntryInvestment'])
    ->name('account.batch-entry.investment');
Route::post('/account/{account}/batch-entry/investment', [TransactionController::class, 'storeBatchEntryInvestment'])
    ->name('account.batch-entry.investment.store');

Route::get('/account/{account}/batch-reconcile/investment', [TransactionController::class, 'batchReconcileInvestment'])
    ->name('account.batch-reconcile.investment');
Route::post('/account/{account}/batch-reconcile/investment', [TransactionController::class, 'storeBatchReconcileInvestment'])
    ->name('account.batch-reconcile.investment.store');
Route::post('/account/{account}/batch-reconcile/investment/quantities', [TransactionController::class, 'getBatchReconcileQuantities'])
    ->name('account.batch-reconcile.investment.quantities');

// Routes to display form to merge two payees
Route::get('/payees/merge/{payeeSource?}', [AccountEntityController::class, 'mergePayeesForm'])
    ->name('payees.merge.form');
Route::post('/payees/merge', [AccountEntityController::class, 'mergePayees'])->name('payees.merge.submit');

/*********************
 * Category related routes
 ********************/
Route::resource('categories', CategoryController::class)->except(['show']);
// Routes to display form to merge two categories
Route::get('/categories/merge/{categorySource?}', [CategoryController::class, 'mergeCategoriesForm'])
    ->name('categories.merge.form');
Route::post('/categories/merge', [CategoryController::class, 'mergeCategories'])->name('categories.merge.submit');

/*********************
 * Currency and currency rate related routes
 ********************/
Route::resource('currency', CurrencyController::class)->except(['show']);
Route::get('currency/{currency}/setDefault', [CurrencyController::class, 'setDefault'])
    ->name('currency.setDefault');

Route::get('/currencyrates/missing/{currency}', [CurrencyRateController::class, 'retrieveMissingCurrencyRateToBase'])
    ->name('currency-rate.retrieveMissing');

Route::get('/currencyrates/get/{currency}/{from?}', [CurrencyRateController::class, 'retrieveCurrencyRateToBase'])
    ->name('currency-rate.retrieveRate');

Route::get('/currencyrates/{from}/{to}', [CurrencyRateController::class, 'index'])
    ->name('currency-rate.index');

Route::resource('currency-rate', CurrencyRateController::class)->only(['destroy']);

/*********************
 * Investment related routes
 ********************/
Route::resource('investment-group', InvestmentGroupController::class)->except(['show']);

Route::get('/investment/transaction/upload', [InvestmentController::class, 'uploadForm'])
    ->name('investment.upload');
Route::get('/import/moneyhub', [ImportController::class, 'moneyhubUpload'])->middleware(['auth', 'verified'])->name('import.moneyhub');
Route::post('/import/moneyhub', [ImportController::class, 'handleMoneyhubUpload'])->middleware(['auth', 'verified'])->name('import.moneyhub.upload');

Route::get('/investment/{investment}/interest', [InvestmentController::class, 'interest'])
    ->name('investment.interest');

Route::resource('investment', InvestmentController::class);

Route::get('/investment-price/list/{investment}', [InvestmentPriceController::class, 'list'])
    ->name('investment-price.list');

Route::get('/investment-price/import-from-trades/{investment}', [InvestmentPriceController::class, 'importFromTrades'])
    ->name('investment-price.importFromTrades');

Route::get('/investment-price/get/{investment}/{from?}', [InvestmentPriceController::class, 'retrieveInvestmentPrice'])
    ->name('investment-price.retrieve');

Route::resource('investment-price', InvestmentPriceController::class)
    ->except(['index', 'show']);

/*********************
 * Tag related routes
 ********************/
Route::resource('tag', TagController::class)
    ->except(['show']);

/*******************
 * Transaction related routes
 ******************/
Route::resource('transaction-import-rules', TransactionImportRuleController::class)
    ->except(['show']);
Route::get('/transaction-import-rules-test', [TransactionImportRuleController::class, 'test'])
    ->name('transaction-import-rules.test');
Route::post('/transaction-import-rules-apply', [TransactionImportRuleController::class, 'applyCorrections'])
    ->name('transaction-import-rules.apply');

Route::get('/transactions/create/{type}', [TransactionController::class, 'create'])
    ->where('type', 'standard|investment')
    ->name('transaction.create');

Route::get('/transactions/{transaction}/{action}', [TransactionController::class, 'openTransaction'])
    ->where('action', 'edit|clone|enter|show|replace')
    ->name('transaction.open');

Route::patch('/transactions/{transaction}/skip', [TransactionController::class, 'skipScheduleInstance'])
    ->name('transactions.skipScheduleInstance');
Route::post('/transactions/create-from-draft', [TransactionController::class, 'createFromDraft'])
    ->name('transactions.createFromDraft');
Route::resource('transactions', TransactionController::class)
    ->only(['destroy']);

/*******************
 * Report related routes
 ******************/
Route::get('/reports/cashflow', [ReportController::class, 'cashFlow'])->name('reports.cashflow');
Route::get('/reports/budgetchart', [ReportController::class, 'budgetChart'])->name('reports.budgetchart');
Route::get('/reports/schedule', [ReportController::class, 'getSchedules'])->name('report.schedules');
Route::get('/reports/transactions', [ReportController::class, 'transactionsByCriteria'])
    ->name('reports.transactions');
Route::get('/reports/timeline', [ReportController::class, 'investmentTimeline'])->name('reports.investment_timeline');
Route::get('/reports/unrealised-interest', [ReportController::class, 'unrealisedInterest'])->name('reports.unrealised_interest');
Route::get('/reports/tax', [App\Http\Controllers\TaxReportController::class, 'index'])->name('reports.tax');
Route::get('/reports/tax/export', [App\Http\Controllers\TaxReportController::class, 'export'])->name('reports.tax.export');

/*******************
 * Miscellanous routes
 *******************/

// Received emails
Route::resource('received-mail', ReceivedMailController::class)
    ->only(['index', 'show', 'destroy']);
Route::post('/received-mail/upload-pdf', [ReceivedMailController::class, 'uploadPdf'])
    ->middleware(['auth', 'verified'])
    ->name('received-mail.upload-pdf');

// Route(s) for search related functionality
Route::get('/search', [SearchController::class, 'search'])->name('search');

// Route for the CSV import functionality
Route::get('/import/csv', [ImportController::class, 'importCsv'])->middleware(['auth', 'verified'])->name('import.csv');
Route::post('/import/csv', [ImportController::class, 'uploadCsv'])->middleware(['auth', 'verified'])->name('import.csv.upload');

// User related routes
Route::get('/user/settings', [UserController::class, 'settings'])->name('user.settings');

/*******************
 * Authentication and verification routes
 *******************/
Auth::routes();
Route::get('/email/verify', [VerificationController::class, 'notice'])
    ->middleware('auth')
    ->name('verification.notice');
Route::get('/email/verify/{id}/{hash}', [VerificationController::class, 'verify'])
    ->middleware(['auth', 'signed'])
    ->name('verification.verify');
Route::post('/email/verification-notification', [VerificationController::class, 'send'])
    ->middleware(['auth', 'throttle:6,1'])
    ->name('verification.send');

/********************
 * Test routes, only available if environment is local
 ********************/
if (app()->environment('local')) {
    Route::get('/test/email/transactioncreatedbyai', function () {
        /** @var ReceivedMail $mail */
        $mail = ReceivedMail::factory()->withTransaction()->create();
        $message = (new TransactionCreatedFromEmail($mail));
        return $message->render();
    });

    Route::get('/test/email/transactionerrorfromemail', function () {
        /** @var ReceivedMail $mail */
        $mail = ReceivedMail::factory()->create();
        $message = (new App\Mail\TransactionErrorFromEmail($mail, 'This is a test error'));
        return $message->render();
    });
}
