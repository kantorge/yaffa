<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AccountGroupController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CurrencyController;
use App\Http\Controllers\CurrencyRateController;
use App\Http\Controllers\InvestmentGroupController;
use App\Http\Controllers\InvestmentController;
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
    '/currencyrates/{from}/{to}',
    [
        'as' => 'currencyrates.index',
        'uses' => [CurrencyRateController::class, 'index']
    ]
);

Route::resource(
    'currencyrates',
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

Route::resource('payees', PayeeController::class);
Route::resource('tag', TagController::class);

Route::get('/transactions/create/standard', [TransactionController::class, 'createStandard'])->name('transactions.createStandard');
Route::get('/transactions/create/investment', [TransactionController::class, 'createInvestment'])->name('transactions.createInvestment');
Route::post('/transactions/standard', [TransactionController::class, 'storeStandard'])->name('transactions.storeStandard');
Route::post('/transactions/investment', [TransactionController::class, 'storeInvestment'])->name('transactions.storeInvestment');
Route::get('/transactions/{transaction}/edit/standard', [TransactionController::class, 'editStandard'])->name('transactions.editStandard');
Route::get('/transactions/{transaction}/edit/investment', [TransactionController::class, 'editInvestment'])->name('transactions.editInvestment');
Route::get('/transactions/{transaction}/clone/standard', [TransactionController::class, 'cloneStandard'])->name('transactions.cloneStandard');
Route::get('/transactions/{transaction}/clone/investment', [TransactionController::class, 'cloneInvestment'])->name('transactions.cloneInvestment');
Route::patch('/transactions/{transaction}/standard', [TransactionController::class, 'updateStandard'])->name('transactions.updateStandard');
Route::patch('/transactions/{transaction}/investment', [TransactionController::class, 'updateInvestment'])->name('transactions.updateInvestment');
Route::patch('/transactions/{transaction}/skip', [TransactionController::class, 'skipScheduleInstance'])->name('transactions.skipScheduleInstance');
Route::get('/transactions/{transaction}/enter/standard', [TransactionController::class, 'enterWithEditStandard'])->name('transactions.enterWithEditStandard');
Route::resource(
    'transactions',
    TransactionController::class
)
->only(
    [
        'destroy'
    ]
);
