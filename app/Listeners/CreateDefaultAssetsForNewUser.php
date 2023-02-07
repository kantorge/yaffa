<?php

namespace App\Listeners;

use App\Models\Account;
use App\Models\AccountEntity;
use App\Models\AccountGroup;
use App\Models\Category;
use App\Models\Currency;
use App\Models\InvestmentGroup;
use App\Models\Tag;
use App\Models\User;
use App\Providers\Faker\CurrencyData;
use Illuminate\Auth\Events\Registered;

class CreateDefaultAssetsForNewUser
{
    private User $user;

    private array $configBasic = [
        'accountGroups' => [
            ['name' => 'Cash'],
            ['name' => 'Bank accounts'],
        ],
        'accounts' => [
            [
                'name' => 'Wallet',
                'accountGroup' => 'Cash',
            ],
            [
                'name' => 'Cash reserve',
                'accountGroup' => 'Cash',
            ],
            [
                'name' => 'Primary bank account',
                'accountGroup' => 'Bank accounts',
            ],
            [
                'name' => 'Other bank account',
                'accountGroup' => 'Bank accounts',
            ],
        ],
        'categories' => [
            ['name' => 'Bills'],
            ['name' => 'Groceries'],
            ['name' => 'Eating out'],
            ['name' => 'Household'],
            ['name' => 'Transportation'],
            ['name' => 'Other'],
        ],
        'investmentGroups' => [
            ['name' => 'Bonds'],
            ['name' => 'Other'],
            ['name' => 'Stocks'],
        ],
        'tags' => [
            ['name' => 'TODO'],
        ],
    ];

    private array $configDefault = [
        'accountGroups' => [
            ['name' => 'Cash'],
            ['name' => 'Bank accounts'],
            ['name' => 'Savings and investments'],
            ['name' => 'Credits'],
        ],
        'accounts' => [
            [
                'name' => 'Wallet',
                'accountGroup' => 'Cash',
            ],
            [
                'name' => 'Cash reserve',
                'accountGroup' => 'Cash',
            ],
            [
                'name' => 'Primary bank account',
                'accountGroup' => 'Bank accounts',
            ],
            [
                'name' => 'Other bank account',
                'accountGroup' => 'Bank accounts',
            ],
            [
                'name' => 'Investment account',
                'accountGroup' => 'Savings and investments',
            ],
        ],
        'categories' => [
            ['name' => 'Bills'],
            [
                'name' => 'Gas',
                'parent' => 'Bills',
            ],
            [
                'name' => 'Electricity',
                'parent' => 'Bills',
            ],
            [
                'name' => 'Water',
                'parent' => 'Bills',
            ],
            [
                'name' => 'Heating',
                'parent' => 'Bills',
            ],
            [
                'name' => 'Internet',
                'parent' => 'Bills',
            ],
            [
                'name' => 'Phone',
                'parent' => 'Bills',
            ],
            ['name' => 'Food'],
            [
                'name' => 'Groceries',
                'parent' => 'Food',
            ],
            [
                'name' => 'Restaurants',
                'parent' => 'Food',
            ],
            ['name' => 'Household'],
            ['name' => 'Transportation'],
            ['name' => 'Other'],
        ],
        'investmentGroups' => [
            ['name' => 'Bonds'],
            ['name' => 'Other'],
            ['name' => 'Stocks'],
        ],
        'tags' => [
            ['name' => 'TODO'],
        ],
    ];

    private function createCurrency(string $isoCode, bool $base = false): Currency
    {
        $currencyData = CurrencyData::getCurrencyByIsoCode($isoCode);

        return Currency::forceCreate([
            'name' => $currencyData['name'],
            'iso_code' => $currencyData['iso_code'],
            'num_digits' => $currencyData['num_digits'],
            'suffix' => $currencyData['suffix'],
            'base' => ($base ? true : null),
            'auto_update' => true,
            'user_id' => $this->user->id,
        ]);
    }

    /**
     * Handle the event.
     *
     * @param Registered $event
     * @return void
     */
    public function handle(Registered $event): void
    {
        // Abort if no new resources are requested
        if ($event->context['defaultData'] === 'basic') {
            $config = $this->configBasic;
        } elseif ($event->context['defaultData'] === 'default') {
            $config = $this->configDefault;
        } else {
            return;
        }

        $this->user = $event->user;

        // Currency
        $currency = $this->createCurrency($event->context['baseCurrency'], true);

        // Account groups
        foreach ($config['accountGroups'] as $accountGroup) {
            AccountGroup::forceCreate([
                'name' => __($accountGroup['name']),
                'user_id' => $this->user->id,
            ]);
        }

        // Accounts
        foreach ($config['accounts'] as $account) {
            $accountGroup = AccountGroup::firstOrNew([
                'name' => __($account['accountGroup']),
                'user_id' => $this->user->id,
            ]);

            if (!$accountGroup->id) {
                $accountGroup->user_id = $this->user->id;
                $accountGroup->save();
            }

            $accountConfig = Account::forceCreate([
                'opening_balance' => 0,
                'account_group_id' => $accountGroup->id,
                'currency_id' => $currency->id,
            ]);

            AccountEntity::forceCreate(
                [
                    'name' => __($account['name']),
                    'active' => 1,
                    'config_type' => 'account',
                    'config_id' => $accountConfig->id,
                    'user_id' => $this->user->id,
                ]
            );
        }

        // Categories
        foreach ($config['categories'] as $category) {
            $parent = null;
            if ($category['parent'] ?? false) {
                $parent = Category::firstOrNew([
                    'name' => __($category['parent']),
                    'active' => true,
                    'parent_id' => null,
                    'user_id' => $this->user->id,
                ]);

                if (!$parent->id) {
                    $parent->user_id = $this->user->id;
                    $parent->save();
                }
            }

            Category::forceCreate([
                'name' => __($category['name']),
                'active' => true,
                'parent_id' => $parent?->id,
                'user_id' => $this->user->id,
            ]);
        }

        // Investment groups
        foreach ($config['investmentGroups'] as $investmentGroup) {
            InvestmentGroup::forceCreate([
                'name' => __($investmentGroup['name']),
                'user_id' => $this->user->id,
            ]);
        }

        // Tags
        foreach ($config['tags'] as $tag) {
            Tag::forceCreate([
                'name' => __($tag['name']),
                'active' => true,
                'user_id' => $this->user->id,
            ]);
        }
    }
}
