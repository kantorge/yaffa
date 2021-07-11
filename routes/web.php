<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AccountGroupController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CurrencyController;
use App\Http\Controllers\CurrencyRateController;
use App\Http\Controllers\InvestmentController;
use App\Http\Controllers\InvestmentGroupController;
use App\Http\Controllers\InvestmentPriceController;
use App\Http\Controllers\MainController;
use App\Http\Controllers\PayeeController;
use App\Http\Controllers\TagController;
use App\Http\Controllers\TransactionController;
use Illuminate\Support\Facades\Route;

Route::get('/', [MainController::class, 'index'])->name('home');

Route::resource('account-group', AccountGroupController::class);

Route::get(
    '/account/history/{account}/{withForecast?}',
    [
        MainController::class,
        'account_details'
    ]
)
->name('account.history');

Route::get('/account/summary/{withClosed?}', [MainController::class, 'index'])->name('account.summary');
Route::resource('account', AccountController::class);

Route::resource('categories', CategoryController::class);
Route::resource('currencies', CurrencyController::class);

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
)
->except(
    [
        'index'
    ]
);

Route::resource('investment-group', InvestmentGroupController::class);
Route::get('/investment/summary', [InvestmentController::class, 'summary'])->name('investment.summary');
Route::resource('investment', InvestmentController::class);

Route::get(
    '/investmentprices/get/{investment}/{from?}',
    [InvestmentPriceController::class, 'retreiveInvestmentPriceAlphaVantage']
)->name('investment-price.retreive');

Route::resource('payees', PayeeController::class);
Route::resource('tag', TagController::class);

Route::get('/transactions/standard/create', [TransactionController::class, 'createStandard'])->name('transactions.createStandard');
Route::get('/transactions/investment/create', [TransactionController::class, 'createInvestment'])->name('transactions.createInvestment');
Route::post('/transactions/standard', [TransactionController::class, 'storeStandard'])->name('transactions.storeStandard');
Route::post('/transactions/investment', [TransactionController::class, 'storeInvestment'])->name('transactions.storeInvestment');

Route::get(
    '/transactions/standard/{transaction}/{action}',
    [
        TransactionController::class,
        'openStandard'
    ]
)
->where('action', 'edit|clone|enter')
->name('transactions.openStandard');

Route::get(
    '/transactions/investment/{transaction}/{action}',
    [
        TransactionController::class,
        'openInvestment'
    ]
)
->where('action', 'edit|clone|enter')
->name('transactions.openInvestment');

Route::patch('/transactions/standard/{transaction}', [TransactionController::class, 'updateStandard'])->name('transactions.updateStandard');
Route::patch('/transactions/investment/{transaction}', [TransactionController::class, 'updateInvestment'])->name('transactions.updateInvestment');
Route::patch('/transactions/{transaction}/skip', [TransactionController::class, 'skipScheduleInstance'])->name('transactions.skipScheduleInstance');
Route::resource(
    'transactions',
    TransactionController::class
)
->only(
    [
        'destroy'
    ]
);
