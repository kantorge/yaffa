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
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\TagController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'dashboard')->middleware('auth')->name('home');

Route::resource('account-group', AccountGroupController::class)->except(['show']);
Route::resource('account-entity', AccountEntityController::class)->except(['show']);

Route::get(
    '/account/history/{account}/{withForecast?}',
    [MainController::class, 'account_details']
)->name('account.history');

Route::resource('categories', CategoryController::class)->except(['show']);
Route::resource('currencies', CurrencyController::class)->except(['show']);
Route::get('currencies/{currency}/setDefault', [CurrencyController::class, 'setDefault'])->name('currencies.setDefault');

Route::get(
    '/currencyrates/missing/{currency}',
    [CurrencyRateController::class, 'retreiveMissingCurrencyRateToBase']
)->name('currencyrate.retreiveMissing');

Route::get(
    '/currencyrates/get/{currency}/{from?}',
    [CurrencyRateController::class, 'retreiveCurrencyRateToBase']
)->name('currencyrate.retreiveRate');

Route::get(
    '/currencyrates/{from}/{to}',
    [CurrencyRateController::class, 'index']
)->name('currency-rate.index');

Route::resource(
    'currency-rate',
    CurrencyRateController::class
)->only(['destroy']);

Route::resource('investment-group', InvestmentGroupController::class)->except(['show']);
Route::get('/investment/summary', [InvestmentController::class, 'summary'])->name('investment.summary');
Route::get('/investment/timeline', [InvestmentController::class, 'timeline'])->name('investment.timeline');
Route::resource('investment', InvestmentController::class);

Route::get(
    '/investment-price/list/{investment}',
    [InvestmentPriceController::class, 'list']
)->name('investment-price.list');

Route::get(
    '/investment-price/get/{investment}/{from?}',
    [InvestmentPriceController::class, 'retreiveInvestmentPriceAlphaVantage']
)->name('investment-price.retreive');

Route::resource(
    'investment-price',
    InvestmentPriceController::class
)->except(['index', 'show']);

Route::resource('tag', TagController::class)->except(['show']);

Route::get('/transactions/standard/create', [TransactionController::class, 'createStandard'])->name('transactions.createStandard');
Route::get('/transactions/investment/create', [TransactionController::class, 'createInvestment'])->name('transactions.createInvestment');
Route::post('/transactions/investment', [TransactionController::class, 'storeInvestment'])->name('transactions.storeInvestment');

Route::get(
    '/transactions/standard/{transaction}/{action}',
    [TransactionController::class, 'openStandard']
)
->where('action', 'edit|clone|enter|show|replace')
->name('transactions.open.standard');

Route::get(
    '/transactions/investment/{transaction}/{action}',
    [TransactionController::class, 'openInvestment']
)
->where('action', 'edit|clone|enter|replace')
->name('transactions.open.investment');

Route::patch('/transactions/investment/{transaction}', [TransactionController::class, 'updateInvestment'])->name('transactions.updateInvestment');
Route::patch('/transactions/{transaction}/skip', [TransactionController::class, 'skipScheduleInstance'])->name('transactions.skipScheduleInstance');
Route::resource(
    'transactions',
    TransactionController::class
)
->only(['destroy']);

Route::get('/reports/cashflow', [ReportController::class, 'cashFlow'])->name('reports.cashflow');
Route::get('/reports/budgetchart', [ReportController::class, 'budgetChart'])->name('reports.budgetchart');
Route::get('/reports/schedule', [ReportController::class, 'getSchedules'])->name('report.schedules');
Route::get('/reports/transactions', [ReportController::class, 'transactionsByCriteria'])->name('reports.transactions');

// Routes to display form to merge two payees
Route::get('/payees/merge/{payeeSource?}', [AccountEntityController::class, 'mergePayeesForm'])->name('payees.merge.form');
Route::post('/payees/merge', [AccountEntityController::class, 'mergePayees'])->name('payees.merge.submit');

// Routes to display form to merge two categories
Route::get('/categories/merge/{categorySource?}', [CategoryController::class, 'mergeCategoriesForm'])->name('categories.merge.form');
Route::post('/categories/merge', [CategoryController::class, 'mergeCategories'])->name('categories.merge.submit');

// Route(s) for search related functionality
Route::get('/search', [SearchController::class, 'search'])->name('search');

// Route for the CSV import functionality
Route::get('/import/csv', [ImportController::class, 'importCsv'])->name('import.csv');

// User related routes
Route::get('/user/settings', [UserController::class, 'settings'])->name('user.settings');
Route::patch('/user/settings', [UserController::class, 'update'])->name('user.update');

Auth::routes();
