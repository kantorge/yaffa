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
use App\Http\Controllers\UserController;
use App\Http\Controllers\VerificationController;
use App\Mail\TransactionCreatedFromEmail;
use App\Models\ReceivedMail;
use Illuminate\Support\Facades\Route;

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

Route::get('/account/history/{account}/{withForecast?}', [MainController::class, 'account_details'])
    ->name('account.history');

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

Route::get('/currencyrates/{from}/{to}', [CurrencyRateController::class, 'index'])
    ->name('currency-rate.index');

/*********************
 * Investment related routes
 ********************/
Route::resource('investment-group', InvestmentGroupController::class)->except(['show']);
Route::resource('investment', InvestmentController::class);

Route::get('/investment-price/list/{investment}', [InvestmentPriceController::class, 'list'])
    ->name('investment-price.list');

/*********************
 * Tag related routes
 ********************/
Route::resource('tag', TagController::class)
    ->except(['show']);

/*******************
 * Transaction related routes
 ******************/
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

/*******************
 * Miscellanous routes
 *******************/

// Received emails
Route::resource('received-mail', ReceivedMailController::class)
    ->only(['index', 'show', 'destroy']);

// Route(s) for search related functionality
Route::get('/search', [SearchController::class, 'search'])->name('search');

// Route for the CSV import functionality
Route::get('/import/csv', [ImportController::class, 'importCsv'])->middleware(['auth', 'verified'])->name('import.csv');

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
