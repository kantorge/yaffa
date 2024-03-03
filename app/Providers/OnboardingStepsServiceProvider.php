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
     */
    public function boot(): void
    {
        $this->app->booted(function () {
            Onboard::addStep(__('Have at least one currency added'))
                ->link(route('currency.create'))
                ->cta(__('Add a currency'))
                ->completeIf(fn (User $model) => Currency::whereUserId($model->id)->count() > 0);

            Onboard::addStep(__('Have your base currency set'))
                ->link(route('currency.index'))
                ->cta(__('Review currency settings'))
                ->completeIf(fn (User $model) => $model->baseCurrency() !== null);

            Onboard::addStep(__('Have at least one account group added'))
                ->link(route('account-group.create'))
                ->cta(__('Add an account group'))
                ->completeIf(fn (User $model) => AccountGroup::whereUserId($model->id)->count() > 0);

            Onboard::addStep(__('Have at least one account added'))
                ->link(route('account-entity.create', ['type' => 'account']))
                ->cta(__('Add an account'))
                ->completeIf(fn (User $model) => AccountEntity::whereUserId($model->id)->accounts()->count() > 0);

            Onboard::addStep(__('Have some payees added'))
                ->link(route('account-entity.create', ['type' => 'payee']))
                ->cta(__('Add a payee'))
                ->completeIf(fn (User $model) => AccountEntity::whereUserId($model->id)->payees()->count() > 0);

            Onboard::addStep(__('Have some categories added'))
                ->link(route('categories.create'))
                ->cta(__('Add a category'))
                ->completeIf(fn (User $model) => Category::whereUserId($model->id)->count() > 0);

            Onboard::addStep(__('Add your first transaction'))
                ->link(route('transaction.create', ['type' => 'standard']))
                ->cta(__('Add transaction'))
                ->completeIf(fn (User $model) => $model->transactionCount() > 0);
        });
    }
}
