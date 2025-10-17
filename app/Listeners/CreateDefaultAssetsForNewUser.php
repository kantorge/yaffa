<?php

namespace App\Listeners;

use App\Events\Registered;
use App\Models\Account;
use App\Models\AccountEntity;
use App\Models\AccountGroup;
use App\Models\Category;
use App\Models\Currency;
use App\Models\InvestmentGroup;
use App\Models\Tag;
use App\Models\User;
use App\Providers\Faker\CurrencyData;

class CreateDefaultAssetsForNewUser
{
    private User $user;

    private array $configBasic = [
        // Accounts are not children of account groups in the model, but it is more efficient to seed the data this way
        'accountGroups' => [
            [
                'name' => 'default_assets.account_groups.cash',
                'accounts' => [
                    ['name' => 'default_assets.accounts.wallet'],
                    ['name' => 'default_assets.accounts.cash_reserve'],
                ],
            ],
            [
                'name' => 'default_assets.account_groups.bank_accounts',
                'accounts' => [
                    ['name' => 'default_assets.accounts.primary_bank_account'],
                    ['name' => 'default_assets.accounts.other_bank_account'],
                ],
            ],
        ],
        'categories' => [
            ['name' => 'default_assets.categories.salary'],
            ['name' => 'default_assets.categories.other_income'],
            ['name' => 'default_assets.categories.housing'],
            ['name' => 'default_assets.categories.utilities'],
            ['name' => 'default_assets.categories.groceries'],
            ['name' => 'default_assets.categories.transportation'],
            ['name' => 'default_assets.categories.healthcare'],
            ['name' => 'default_assets.categories.insurance'],
            ['name' => 'default_assets.categories.entertainment'],
            ['name' => 'default_assets.categories.clothing'],
        ],
        'investmentGroups' => [
            ['name' => 'default_assets.investment_groups.bonds'],
            ['name' => 'default_assets.investment_groups.stocks'],
            ['name' => 'default_assets.investment_groups.other'],
        ],
        'tags' => [
            ['name' => 'default_assets.tags.todo'],
        ],
    ];

    private array $configDefault = [
        'accountGroups' => [
            [
                'name' => 'default_assets.account_groups.cash',
                'accounts' => [
                    ['name' => 'default_assets.accounts.wallet'],
                    ['name' => 'default_assets.accounts.cash_reserve'],
                ],
            ],
            [
                'name' => 'default_assets.account_groups.bank_accounts',
                'accounts' => [
                    ['name' => 'default_assets.accounts.primary_bank_account'],
                    ['name' => 'default_assets.accounts.other_bank_account'],
                ],
            ],
            [
                'name' => 'default_assets.account_groups.savings_and_investments',
                'accounts' => [
                    ['name' => 'default_assets.accounts.investment_account'],
                ],
            ],
            ['name' => 'default_assets.account_groups.credits'],
        ],

        // The concept of child categories is not used in the model, but it is more efficient to seed the data this way
        'categories' => [
            [
                'name' => 'default_assets.categories.salary',
                'children' => [
                    ['name' => 'default_assets.categories.main_job'],
                    ['name' => 'default_assets.categories.bonuses'],
                ],
            ],
            [
                'name' => 'default_assets.categories.other_income',
                'children' => [
                    ['name' => 'default_assets.categories.side_job'],
                    ['name' => 'default_assets.categories.rental_income'],
                ],
            ],
            [
                'name' => 'default_assets.categories.housing',
                'children' => [
                    ['name' => 'default_assets.categories.rent_and_mortgage'],
                    ['name' => 'default_assets.categories.maintenance_and_repairs'],
                ],
            ],
            [
                'name' => 'default_assets.categories.utilities',
                'children' => [
                    ['name' => 'default_assets.categories.electricity'],
                    ['name' => 'default_assets.categories.water'],
                    ['name' => 'default_assets.categories.gas_and_heating'],
                ],
            ],
            [
                'name' => 'default_assets.categories.groceries',
                'children' => [
                    ['name' => 'default_assets.categories.food'],
                    ['name' => 'default_assets.categories.household_products'],
                ],
            ],
            [
                'name' => 'default_assets.categories.transportation',
                'children' => [
                    ['name' => 'default_assets.categories.public_transport'],
                    ['name' => 'default_assets.categories.fuel'],
                    ['name' => 'default_assets.categories.vehicle_maintenance'],
                ],
            ],
            [
                'name' => 'default_assets.categories.entertainment',
                'children' => [
                    ['name' => 'default_assets.categories.dining_out'],
                    ['name' => 'default_assets.categories.subscriptions'],
                    ['name' => 'default_assets.categories.events'],
                ],
            ],
            [
                'name' => 'default_assets.categories.healthcare',
                'children' => [
                    ['name' => 'default_assets.categories.doctor_visits'],
                    ['name' => 'default_assets.categories.medications'],
                    ['name' => 'default_assets.categories.health_insurance'],
                ],
            ],
            [
                'name' => 'default_assets.categories.insurance',
                'children' => [
                    ['name' => 'default_assets.categories.home_insurance'],
                    ['name' => 'default_assets.categories.car_insurance'],
                ],
            ],
            [
                'name' => 'default_assets.categories.debt_repayment',
                'children' => [
                    ['name' => 'default_assets.categories.credit_card'],
                    ['name' => 'default_assets.categories.loans'],
                ],
            ],
        ],
        'investmentGroups' => [
            ['name' => 'default_assets.investment_groups.bonds'],
            ['name' => 'default_assets.investment_groups.stocks'],
            ['name' => 'default_assets.investment_groups.other'],
        ],
        'tags' => [
            ['name' => 'default_assets.tags.todo'],
        ],
    ];

    private array $configAdvanced = [
        'accountGroups' => [
            [
                'name' => 'default_assets.account_groups.cash',
                'accounts' => [
                    ['name' => 'default_assets.accounts.wallet'],
                    ['name' => 'default_assets.accounts.cash_reserve'],
                ],
            ],
            [
                'name' => 'default_assets.account_groups.bank_accounts',
                'accounts' => [
                    ['name' => 'default_assets.accounts.primary_bank_account'],
                    ['name' => 'default_assets.accounts.other_bank_account'],
                ],
            ],
            [
                'name' => 'default_assets.account_groups.savings_and_investments',
                'accounts' => [
                    ['name' => 'default_assets.accounts.investment_account'],
                ],
            ],
            [
                'name' => 'default_assets.account_groups.credits',
                'accounts' => [
                    ['name' => 'default_assets.accounts.credit_card'],
                ],
            ],
            [
                'name' => 'default_assets.account_groups.loans',
                'accounts' => [
                    ['name' => 'default_assets.accounts.loan'],
                ],
            ],
        ],

        // The concept of child categories is not used in the model, but it is more efficient to seed the data this way
        'categories' => [
            [
                'name' => 'default_assets.categories.salary',
                'children' => [
                    ['name' => 'default_assets.categories.main_job'],
                    ['name' => 'default_assets.categories.bonuses'],
                ],
            ],
            [
                'name' => 'default_assets.categories.other_income',
                'children' => [
                    ['name' => 'default_assets.categories.freelance_work'],
                    ['name' => 'default_assets.categories.rental_income'],
                ],
            ],
            [
                'name' => 'default_assets.categories.government_benefits',
                'children' => [
                    ['name' => 'default_assets.categories.child_allowance'],
                    ['name' => 'default_assets.categories.social_assistance'],
                ],
            ],
            [
                'name' => 'default_assets.categories.housing',
                'children' => [
                    ['name' => 'default_assets.categories.rent'],
                    ['name' => 'default_assets.categories.property_taxes'],
                    ['name' => 'default_assets.categories.repairs_maintenance'],
                    ['name' => 'default_assets.categories.home_improvements'],
                ],
            ],
            [
                'name' => 'default_assets.categories.utilities',
                'children' => [
                    ['name' => 'default_assets.categories.electricity'],
                    ['name' => 'default_assets.categories.gas_and_heating'],
                    ['name' => 'default_assets.categories.water_sewer'],
                    ['name' => 'default_assets.categories.internet_cable'],
                ],
            ],
            [
                'name' => 'default_assets.categories.groceries',
                'children' => [
                    ['name' => 'default_assets.categories.food'],
                    ['name' => 'default_assets.categories.household_products'],
                    ['name' => 'default_assets.categories.personal_care'],
                    ['name' => 'default_assets.categories.clothing'],
                ],
            ],
            [
                'name' => 'default_assets.categories.transportation',
                'children' => [
                    ['name' => 'default_assets.categories.car_payments'],
                    ['name' => 'default_assets.categories.public_transport'],
                    ['name' => 'default_assets.categories.fuel'],
                    ['name' => 'default_assets.categories.parking'],
                    ['name' => 'default_assets.categories.maintenance_and_repairs'],
                ],
            ],
            [
                'name' => 'default_assets.categories.entertainment_and_leisure',
                'children' => [
                    ['name' => 'default_assets.categories.dining_out'],
                    ['name' => 'default_assets.categories.subscriptions'],
                    ['name' => 'default_assets.categories.hobbies_and_activities'],
                    ['name' => 'default_assets.categories.vacation_and_travel'],
                    ['name' => 'default_assets.categories.events'],
                ],
            ],
            [
                'name' => 'default_assets.categories.healthcare',
                'children' => [
                    ['name' => 'default_assets.categories.doctor_visits'],
                    ['name' => 'default_assets.categories.medications'],
                    ['name' => 'default_assets.categories.health_insurance'],
                    ['name' => 'default_assets.categories.vision_and_dental_care'],
                ],
            ],
            [
                'name' => 'default_assets.categories.insurance',
                'children' => [
                    ['name' => 'default_assets.categories.home_insurance'],
                    ['name' => 'default_assets.categories.car_insurance'],
                    ['name' => 'default_assets.categories.life_insurance'],
                    ['name' => 'default_assets.categories.disability_insurance'],
                ],
            ],
            [
                'name' => 'default_assets.categories.debt_repayment',
                'children' => [
                    ['name' => 'default_assets.categories.credit_card'],
                    ['name' => 'default_assets.categories.student_loans'],
                    ['name' => 'default_assets.categories.personal_loans'],
                    ['name' => 'default_assets.categories.mortgage_overpayments'],
                ],
            ],
            [
                'name' => 'default_assets.categories.education_and_development',
                'children' => [
                    ['name' => 'default_assets.categories.courses_and_training'],
                    ['name' => 'default_assets.categories.books_and_supplies'],
                    ['name' => 'default_assets.categories.school_and_university_fees'],
                ],
            ],
            [
                'name' => 'default_assets.categories.miscellaneous',
                'children' => [
                    ['name' => 'default_assets.categories.gifts_and_donations'],
                    ['name' => 'default_assets.categories.pet_care'],
                    ['name' => 'default_assets.categories.fines'],
                    ['name' => 'default_assets.categories.legal_fees'],
                ],
            ],
        ],
        'investmentGroups' => [
            ['name' => 'default_assets.investment_groups.bonds'],
            ['name' => 'default_assets.investment_groups.stocks'],
            ['name' => 'default_assets.investment_groups.other'],
        ],
        'tags' => [
            ['name' => 'default_assets.tags.todo'],
        ],
    ];

    private function createCurrency(string $isoCode, bool $base = false): Currency
    {
        $currencyData = CurrencyData::getCurrencyByIsoCode($isoCode);

        return Currency::forceCreate([
            'name' => $currencyData['name'],
            'iso_code' => $currencyData['iso_code'],
            'base' => ($base ? true : null),
            'auto_update' => true,
            'user_id' => $this->user->id,
        ]);
    }

    /**
     * Handle the event.
     */
    public function handle(Registered $event): void
    {
        /**
         * @uses CreateDefaultAssetsForNewUser::configBasic
         * @uses CreateDefaultAssetsForNewUser::configDefault
         * @uses CreateDefaultAssetsForNewUser::configAdvanced
         */
        // Load default data configuration, or abort if not found or not requested
        $defaultDataOptions = ['basic', 'default', 'advanced'];
        if (in_array($event->context['defaultData'], $defaultDataOptions, true)) {
            $config = $this->{'config' . ucfirst($event->context['defaultData'])};
        } else {
            return;
        }

        $this->user = $event->user;

        // Currency
        $currency = $this->createCurrency($event->context['baseCurrency'], true);

        // Account groups
        foreach ($config['accountGroups'] as $accountGroup) {
            $accountGroup = AccountGroup::forceCreate([
                'name' => __($accountGroup['name']),
                'user_id' => $this->user->id,
            ]);

            // Loop through accounts, if any
            if (isset($accountGroup['accounts'])) {
                foreach ($accountGroup['accounts'] as $account) {
                    $accountConfig = Account::forceCreate([
                        'opening_balance' => 0,
                        'account_group_id' => $accountGroup->id,
                        // At the moment, all accounts are in the base currency
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
            }
        }

        // Categories - this loop is for parent categories
        foreach ($config['categories'] as $category) {
            $parent = Category::forceCreate([
                'name' => __($category['name']),
                'active' => true,
                'parent_id' => null,
                'user_id' => $this->user->id,
            ]);

            // Categories - this loop is for its optional child categories
            if (isset($category['children'])) {
                foreach ($category['children'] as $childCategory) {
                    Category::forceCreate([
                        'name' => __($childCategory['name']),
                        'active' => true,
                        'parent_id' => $parent->id,
                        'user_id' => $this->user->id,
                    ]);
                }
            }
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
