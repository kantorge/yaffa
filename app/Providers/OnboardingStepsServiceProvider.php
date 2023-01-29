<?php

namespace App\Providers;

use App\Models\AccountEntity;
use App\Models\AccountGroup;
use App\Models\Category;
use App\Models\Currency;
use App\Models\User;
use Illuminate\Support\ServiceProvider;
use Spatie\Onboard\Facades\Onboard;

class OnboardingStepsServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot(): void
    {
        $this->app->booted(function () {
            Onboard::addStep(__('Have at least one currency added'))
                ->link(route('currencies.create'))
                ->cta(__('Add a currency'))
                ->completeIf(function (User $model) {
                    return Currency::whereUserId($model->id)->count() > 0;
                });

            Onboard::addStep(__('Have your base currency set'))
                ->link(route('currencies.index'))
                ->cta(__('Review currency settings'))
                ->completeIf(function (User $model) {
                    return $model->baseCurrency() !== null;
                });

            Onboard::addStep(__('Have at least one account group added'))
                ->link(route('account-group.create'))
                ->cta(__('Add an account group'))
                ->completeIf(function (User $model) {
                    return AccountGroup::whereUserId($model->id)->count() > 0;
                });

            Onboard::addStep(__('Have at least one account added'))
                ->link(route('account-entity.create', ['type' => 'account']))
                ->cta(__('Add an account'))
                ->completeIf(function (User $model) {
                    return AccountEntity::whereUserId($model->id)->accounts()->count() > 0;
                });

            Onboard::addStep(__('Have some payees added'))
                ->link(route('account-entity.create', ['type' => 'payee']))
                ->cta(__('Add a payee'))
                ->completeIf(function (User $model) {
                    return AccountEntity::whereUserId($model->id)->payees()->count() > 0;
                });

            Onboard::addStep(__('Have some categories added'))
                ->link(route('categories.create'))
                ->cta(__('Add a category'))
                ->completeIf(function (User $model) {
                    return Category::whereUserId($model->id)->count() > 0;
                });

            Onboard::addStep(__('Add your first transaction'))
                ->link(route('transactions.createStandard'))
                ->cta(__('Add transaction'))
                ->completeIf(function (User $model) {
                    return $model->transactionCount() > 0;
                });
        });
    }
}
