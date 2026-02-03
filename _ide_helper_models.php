<?php

// @formatter:off
// phpcs:ignoreFile
/**
 * A helper file for your Eloquent Models
 * Copy the phpDocs from this file to the correct Model,
 * And remove them from this file, to prevent double declarations.
 *
 * @author Barry vd. Heuvel <barryvdh@gmail.com>
 */


namespace App\Models{
/**
 * App\Models\Account
 *
 * @property int $id
 * @property float $opening_balance
 * @property int $account_group_id
 * @property int $currency_id
 * @property string|null $default_date_range
 * @property-read AccountGroup $accountGroup
 * @property-read Collection|Category[] $categoryPreference
 * @property-read int|null $category_preference_count
 * @property-read AccountEntity|null $config
 * @property-read Currency $currency
 * @property-read Collection|Category[] $deferredCategories
 * @property-read int|null $deferred_categories_count
 * @property-read Collection|Category[] $preferredCategories
 * @property-read int|null $preferred_categories_count
 * @property-read Collection|TransactionDetailStandard[] $transactionDetailStandardFrom
 * @property-read int|null $transaction_detail_standard_from_count
 * @property-read Collection|TransactionDetailStandard[] $transactionDetailStandardTo
 * @property-read int|null $transaction_detail_standard_to_count
 * @property-read Collection|Transaction[] $transactionsFrom
 * @property-read int|null $transactions_from_count
 * @property-read Collection|Transaction[] $transactionsTo
 * @property-read int|null $transactions_to_count
 * @property-read User|null $user
 * @method static Builder|AccountEntity accounts()
 * @method static Builder|AccountEntity active()
 * @method static AccountFactory factory(...$parameters)
 * @method static Builder|Account newModelQuery()
 * @method static Builder|Account newQuery()
 * @method static Builder|AccountEntity payees()
 * @method static Builder|Account query()
 * @method static Builder|Account whereAccountGroupId($value)
 * @method static Builder|Account whereCurrencyId($value)
 * @method static Builder|Account whereId($value)
 * @method static Builder|Account whereOpeningBalance($value)
 * @mixin Eloquent
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Account whereDefaultDateRange($value)
 */
	class Account extends \Eloquent {}
}

namespace App\Models{
/**
 * App\Models\AccountEntity
 *
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property bool $active
 * @property string $config_type
 * @property int $config_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection|Category[] $categoryPreference
 * @property-read int|null $category_preference_count
 * @property-read Model|Eloquent $config
 * @property-read Collection|Category[] $deferredCategories
 * @property-read int|null $deferred_categories_count
 * @property-read Collection|Category[] $preferredCategories
 * @property-read int|null $preferred_categories_count
 * @property-read Collection|TransactionDetailStandard[] $transactionDetailStandardFrom
 * @property-read int|null $transaction_detail_standard_from_count
 * @property-read Collection|TransactionDetailStandard[] $transactionDetailStandardTo
 * @property-read int|null $transaction_detail_standard_to_count
 * @property-read Collection|Transaction[] $transactionsFrom
 * @property-read int|null $transactions_from_count
 * @property-read Collection|Transaction[] $transactionsTo
 * @property-read int|null $transactions_to_count
 * @property-read User $user
 * @method static Builder|AccountEntity accounts()
 * @method static Builder|AccountEntity active()
 * @method static AccountEntityFactory factory(...$parameters)
 * @method static Builder|AccountEntity newModelQuery()
 * @method static Builder|AccountEntity newQuery()
 * @method static Builder|AccountEntity payees()
 * @method static Builder|AccountEntity query()
 * @method static Builder|AccountEntity whereActive($value)
 * @method static Builder|AccountEntity whereConfigId($value)
 * @method static Builder|AccountEntity whereConfigType($value)
 * @method static Builder|AccountEntity whereCreatedAt($value)
 * @method static Builder|AccountEntity whereId($value)
 * @method static Builder|AccountEntity whereName($value)
 * @method static Builder|AccountEntity whereUpdatedAt($value)
 * @method static Builder|AccountEntity whereUserId($value)
 * @mixin Eloquent
 * @property string|null $alias
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Transaction> $transactionsInvestment
 * @property-read int|null $transactions_investment_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Transaction> $transactionsStandardFrom
 * @property-read int|null $transactions_standard_from_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Transaction> $transactionsStandardTo
 * @property-read int|null $transactions_standard_to_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AccountEntity whereAlias($value)
 */
	class AccountEntity extends \Eloquent {}
}

namespace App\Models{
/**
 * App\Models\AccountGroup
 *
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 * @method static \Database\Factories\AccountGroupFactory factory(...$parameters)
 * @method static \Illuminate\Database\Eloquent\Builder|AccountGroup newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|AccountGroup newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|AccountGroup query()
 * @method static \Illuminate\Database\Eloquent\Builder|AccountGroup whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|AccountGroup whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|AccountGroup whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|AccountGroup whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|AccountGroup whereUserId($value)
 * @mixin Eloquent
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\AccountEntity> $accountEntities
 * @property-read int|null $account_entities_count
 */
	class AccountGroup extends \Eloquent {}
}

namespace App\Models{
/**
 * @mixin Eloquent
 * @property int $id
 * @property \Illuminate\Support\Carbon $date
 * @property int $user_id
 * @property int|null $account_entity_id
 * @property string $transaction_type
 * @property string $data_type
 * @property float $amount
 * @property \Illuminate\Support\Carbon $updated_at
 * @property-read \App\Models\AccountEntity|null $accountEntity
 * @property-read \App\Models\User $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AccountMonthlySummary newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AccountMonthlySummary newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AccountMonthlySummary query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AccountMonthlySummary whereAccountEntityId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AccountMonthlySummary whereAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AccountMonthlySummary whereDataType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AccountMonthlySummary whereDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AccountMonthlySummary whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AccountMonthlySummary whereTransactionType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AccountMonthlySummary whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AccountMonthlySummary whereUserId($value)
 */
	class AccountMonthlySummary extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $user_id
 * @property string $status
 * @property string $source_type
 * @property array<array-key, mixed>|null $processed_transaction_data
 * @property string|null $google_drive_file_id
 * @property int|null $received_mail_id
 * @property string|null $custom_prompt
 * @property \Illuminate\Support\Carbon|null $processed_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\AiDocumentFile> $files
 * @property-read int|null $files_count
 * @property-read \App\Models\ReceivedMail|null $receivedMail
 * @property-read \App\Models\User $user
 * @method static \Database\Factories\AiDocumentFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiDocument newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiDocument newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiDocument query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiDocument whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiDocument whereCustomPrompt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiDocument whereGoogleDriveFileId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiDocument whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiDocument whereProcessedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiDocument whereProcessedTransactionData($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiDocument whereReceivedMailId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiDocument whereSourceType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiDocument whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiDocument whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiDocument whereUserId($value)
 */
	class AiDocument extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $ai_document_id
 * @property string $file_path
 * @property string $file_name
 * @property string $file_type
 * @property-read \App\Models\AiDocument $aiDocument
 * @method static \Database\Factories\AiDocumentFileFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiDocumentFile newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiDocumentFile newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiDocumentFile query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiDocumentFile whereAiDocumentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiDocumentFile whereFileName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiDocumentFile whereFilePath($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiDocumentFile whereFileType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiDocumentFile whereId($value)
 */
	class AiDocumentFile extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $user_id
 * @property string $provider
 * @property string $model
 * @property string $api_key
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\User $user
 * @method static \Database\Factories\AiProviderConfigFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiProviderConfig newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiProviderConfig newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiProviderConfig query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiProviderConfig whereApiKey($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiProviderConfig whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiProviderConfig whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiProviderConfig whereModel($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiProviderConfig whereProvider($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiProviderConfig whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiProviderConfig whereUserId($value)
 */
	class AiProviderConfig extends \Eloquent {}
}

namespace App\Models{
/**
 * App\Models\Category
 *
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property bool $active
 * @property int|null $parent_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read mixed $full_name
 * @property-read Category|null $parent
 * @property-read Collection|AccountEntity[] $payeesNotPreferring
 * @property-read int|null $payees_not_preferring_count
 * @property-read Collection|Transaction[] $transaction
 * @property-read int|null $transaction_count
 * @property-read Collection|TransactionItem[] $transactionItem
 * @property-read int|null $transaction_item_count
 * @property-read User $user
 * @method static Builder|Category active()
 * @method static \Database\Factories\CategoryFactory factory(...$parameters)
 * @method static Builder|Category newModelQuery()
 * @method static Builder|Category newQuery()
 * @method static Builder|Category query()
 * @method static Builder|Category topLevel()
 * @method static Builder|Category whereActive($value)
 * @method static Builder|Category whereCreatedAt($value)
 * @method static Builder|Category whereId($value)
 * @method static Builder|Category whereName($value)
 * @method static Builder|Category whereParentId($value)
 * @method static Builder|Category whereUpdatedAt($value)
 * @method static Builder|Category whereUserId($value)
 * @mixin \Eloquent
 * @property string $default_aggregation
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Category> $children
 * @property-read int|null $children_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\AccountEntity> $payeesDefaulting
 * @property-read int|null $payees_defaulting_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\AccountEntity> $payeesPreferring
 * @property-read int|null $payees_preferring_count
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Category childCategory()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Category parentCategory()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Category whereDefaultAggregation($value)
 */
	class Category extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $user_id
 * @property string $item_description
 * @property int $category_id
 * @property int $usage_count
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Category $category
 * @property-read \App\Models\User $user
 * @method static \Database\Factories\CategoryLearningFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CategoryLearning newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CategoryLearning newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CategoryLearning query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CategoryLearning whereCategoryId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CategoryLearning whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CategoryLearning whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CategoryLearning whereItemDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CategoryLearning whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CategoryLearning whereUsageCount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CategoryLearning whereUserId($value)
 */
	class CategoryLearning extends \Eloquent {}
}

namespace App\Models{
/**
 * App\Models\Currency
 *
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property string $iso_code
 * @property bool|null $base
 * @property bool $auto_update
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read User $user
 * @method static Builder|Currency autoUpdate()
 * @method static Builder|Currency base()
 * @method static CurrencyFactory factory(...$parameters)
 * @method static Builder|Currency newModelQuery()
 * @method static Builder|Currency newQuery()
 * @method static Builder|Currency notBase()
 * @method static Builder|Currency query()
 * @method static Builder|Currency whereAutoUpdate($value)
 * @method static Builder|Currency whereBase($value)
 * @method static Builder|Currency whereCreatedAt($value)
 * @method static Builder|Currency whereId($value)
 * @method static Builder|Currency whereIsoCode($value)
 * @method static Builder|Currency whereName($value)
 * @method static Builder|Currency whereNumDigits($value)
 * @method static Builder|Currency whereUpdatedAt($value)
 * @method static Builder|Currency whereUserId($value)
 * @mixin Eloquent
 */
	class Currency extends \Eloquent {}
}

namespace App\Models{
/**
 * @property int $id
 * @property int $from_id
 * @property int $to_id
 * @property \Illuminate\Support\Carbon $date
 * @property float $rate
 * @property-read \App\Models\Currency $currencyFrom
 * @property-read \App\Models\Currency $currencyTo
 * @method static \Database\Factories\CurrencyRateFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CurrencyRate newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CurrencyRate newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CurrencyRate query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CurrencyRate whereDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CurrencyRate whereFromId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CurrencyRate whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CurrencyRate whereRate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CurrencyRate whereToId($value)
 */
	class CurrencyRate extends \Eloquent {}
}

namespace App\Models{
/**
 * App\Models\Investment
 *
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property string $symbol
 * @property string|null $isin
 * @property string|null $comment
 * @property bool $active
 * @property bool $auto_update
 * @property string|null $investment_price_provider
 * @property int $investment_group_id
 * @property int $currency_id
 * @property string|null $scrape_url
 * @property string|null $scrape_selector
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Currency $currency
 * @property-read string|null $investment_price_provider_name
 * @property-read InvestmentGroup $investmentGroup
 * @property-read Collection|InvestmentPrice[] $investmentPrices
 * @property-read int|null $investment_prices_count
 * @property-read User $user
 * @method static Builder|Investment active()
 * @method static InvestmentFactory factory(...$parameters)
 * @method static Builder|Investment newModelQuery()
 * @method static Builder|Investment newQuery()
 * @method static Builder|Investment query()
 * @method static Builder|Investment whereActive($value)
 * @method static Builder|Investment whereAutoUpdate($value)
 * @method static Builder|Investment whereComment($value)
 * @method static Builder|Investment whereCreatedAt($value)
 * @method static Builder|Investment whereCurrencyId($value)
 * @method static Builder|Investment whereId($value)
 * @method static Builder|Investment whereInvestmentGroupId($value)
 * @method static Builder|Investment whereInvestmentPriceProvider($value)
 * @method static Builder|Investment whereIsin($value)
 * @method static Builder|Investment whereName($value)
 * @method static Builder|Investment whereSymbol($value)
 * @method static Builder|Investment whereUpdatedAt($value)
 * @method static Builder|Investment whereUserId($value)
 * @property-read Collection<int, TransactionDetailInvestment> $transactionDetailInvestment
 * @property-read int|null $transaction_detail_investment_count
 * @property-read Collection<int, Transaction> $transactions
 * @property-read int|null $transactions_count
 * @property-read Collection<int, Transaction> $transactionsBasic
 * @property-read int|null $transactions_basic_count
 * @property-read Collection<int, Transaction> $transactionsScheduled
 * @property-read int|null $transactions_scheduled_count
 * @mixin Eloquent
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Investment whereScrapeSelector($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Investment whereScrapeUrl($value)
 */
	class Investment extends \Eloquent {}
}

namespace App\Models{
/**
 * App\Models\InvestmentGroup
 *
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read User $user
 * @method static \Database\Factories\InvestmentGroupFactory factory(...$parameters)
 * @method static \Illuminate\Database\Eloquent\Builder|InvestmentGroup newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|InvestmentGroup newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|InvestmentGroup query()
 * @method static \Illuminate\Database\Eloquent\Builder|InvestmentGroup whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|InvestmentGroup whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|InvestmentGroup whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|InvestmentGroup whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|InvestmentGroup whereUserId($value)
 * @mixin \Eloquent
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Investment> $investments
 * @property-read int|null $investments_count
 */
	class InvestmentGroup extends \Eloquent {}
}

namespace App\Models{
/**
 * App\Models\InvestmentPrice
 *
 * @property int $id
 * @property \Illuminate\Support\Carbon $date
 * @property int $investment_id
 * @property float $price
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Investment $investment
 * @method static \Illuminate\Database\Eloquent\Builder|InvestmentPrice newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|InvestmentPrice newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|InvestmentPrice query()
 * @method static \Illuminate\Database\Eloquent\Builder|InvestmentPrice whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|InvestmentPrice whereDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder|InvestmentPrice whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|InvestmentPrice whereInvestmentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|InvestmentPrice wherePrice($value)
 * @method static \Illuminate\Database\Eloquent\Builder|InvestmentPrice whereUpdatedAt($value)
 * @mixin \Eloquent
 * @method static \Database\Factories\InvestmentPriceFactory factory($count = null, $state = [])
 */
	class InvestmentPrice extends \Eloquent {}
}

namespace App\Models{
/**
 * App\Models\Payee
 *
 * @property int $id
 * @property int|null $category_id
 * @property \Illuminate\Support\Carbon|null $category_suggestion_dismissed
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $import_alias
 * @property-read Category|null $category
 * @property-read AccountEntity|null $config
 * @property-read mixed $first_transaction_date
 * @property-read mixed $latest_transaction_date
 * @property-read mixed $transaction_count
 * @property-read \Illuminate\Database\Eloquent\Collection|Transaction[] $transactionsTo
 * @property-read int|null $transactions_to_count
 * @property-read User $user
 * @method static Builder|AccountEntity accounts()
 * @method static Builder|AccountEntity active()
 * @method static \Database\Factories\PayeeFactory factory(...$parameters)
 * @method static Builder|Payee newModelQuery()
 * @method static Builder|Payee newQuery()
 * @method static Builder|AccountEntity payees()
 * @method static Builder|Payee query()
 * @method static Builder|Payee whereCategoryId($value)
 * @method static Builder|Payee whereCategorySuggestionDismissed($value)
 * @method static Builder|Payee whereCreatedAt($value)
 * @method static Builder|Payee whereId($value)
 * @method static Builder|Payee whereImportAlias($value)
 * @method static Builder|Payee whereUpdatedAt($value)
 * @property-read \Illuminate\Database\Eloquent\Collection|Category[] $categoryPreference
 * @property-read int|null $category_preference_count
 * @property-read \Illuminate\Database\Eloquent\Collection|Category[] $deferredCategories
 * @property-read int|null $deferred_categories_count
 * @property-read \Illuminate\Database\Eloquent\Collection|Category[] $preferredCategories
 * @property-read int|null $preferred_categories_count
 * @property-read \Illuminate\Database\Eloquent\Collection|TransactionDetailStandard[] $transactionDetailStandardFrom
 * @property-read int|null $transaction_detail_standard_from_count
 * @property-read \Illuminate\Database\Eloquent\Collection|TransactionDetailStandard[] $transactionDetailStandardTo
 * @property-read int|null $transaction_detail_standard_to_count
 * @property-read \Illuminate\Database\Eloquent\Collection|Transaction[] $transactionsFrom
 * @property-read int|null $transactions_from_count
 * @mixin \Eloquent
 */
	class Payee extends \Eloquent {}
}

namespace App\Models{
/**
 * App\Models\ReceivedMail
 *
 * @property int $id
 * @property string $subject
 * @property string $html
 * @property string $text
 * @mixin Eloquent
 * @property string $message_id
 * @property int $user_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\AiDocument|null $aiDocument
 * @property-read \App\Models\User $user
 * @method static \Database\Factories\ReceivedMailFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReceivedMail newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReceivedMail newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReceivedMail query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReceivedMail whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReceivedMail whereHtml($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReceivedMail whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReceivedMail whereMessageId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReceivedMail whereSubject($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReceivedMail whereText($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReceivedMail whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ReceivedMail whereUserId($value)
 */
	class ReceivedMail extends \Eloquent {}
}

namespace App\Models{
/**
 * App\Models\Tag
 *
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property bool $active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection|TransactionItem[] $transactionItems
 * @property-read int|null $transaction_items_count
 * @property-read User $user
 * @method static Builder|Tag active()
 * @method static TagFactory factory(...$parameters)
 * @method static Builder|Tag newModelQuery()
 * @method static Builder|Tag newQuery()
 * @method static Builder|Tag query()
 * @method static Builder|Tag whereActive($value)
 * @method static Builder|Tag whereCreatedAt($value)
 * @method static Builder|Tag whereId($value)
 * @method static Builder|Tag whereName($value)
 * @method static Builder|Tag whereUpdatedAt($value)
 * @method static Builder|Tag whereUserId($value)
 * @property-read int $transaction_count
 */
	class Tag extends \Eloquent {}
}

namespace App\Models{
/**
 * App\Models\Transaction
 *
 * @property int $id
 * @property int $user_id
 * @property \Illuminate\Support\Carbon|null $date
 * @property int $transaction_type_id
 * @property bool $reconciled
 * @property bool $schedule
 * @property bool $budget
 * @property string|null $comment
 * @property string|null $config_type
 * @property int|null $config_id
 * @property int|null $currency_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Model|Eloquent $config
 * @property-read \Illuminate\Database\Eloquent\Collection|TransactionItem[] $transactionItems
 * @property-read int|null $transaction_items_count
 * @property-read TransactionSchedule|null $transactionSchedule
 * @property-read TransactionType $transactionType
 * @method static Builder|Transaction byScheduleType($type)
 * @method static Builder|Transaction byType($type)
 * @method static TransactionFactory factory(...$parameters)
 * @method static Builder|Transaction newModelQuery()
 * @method static Builder|Transaction newQuery()
 * @method static Builder|Transaction query()
 * @method static Builder|Transaction whereBudget($value)
 * @method static Builder|Transaction whereComment($value)
 * @method static Builder|Transaction whereConfigId($value)
 * @method static Builder|Transaction whereConfigType($value)
 * @method static Builder|Transaction whereCreatedAt($value)
 * @method static Builder|Transaction whereDate($value)
 * @method static Builder|Transaction whereId($value)
 * @method static Builder|Transaction whereReconciled($value)
 * @method static Builder|Transaction whereSchedule($value)
 * @method static Builder|Transaction whereTransactionTypeId($value)
 * @method static Builder|Transaction whereUpdatedAt($value)
 * @method static Builder|Transaction whereUserId($value)
 * @mixin Eloquent
 * @property float|null $cashflow_value
 * @property int|null $ai_document_id
 * @property-read \App\Models\AiDocument|null $aiDocument
 * @property-read \App\Models\Currency|null $currency
 * @property-read \App\Models\Currency|null $transaction_currency
 * @property-read \App\Models\User $user
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereAiDocumentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereCashflowValue($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereCurrencyId($value)
 */
	class Transaction extends \Eloquent {}
}

namespace App\Models{
/**
 * App\Models\TransactionDetailInvestment
 *
 * @property int $id
 * @property int $account_entity_id
 * @property int $investment_id
 * @property float|null $price
 * @property float|null $quantity
 * @property float|null $commission
 * @property float|null $tax
 * @property float|null $dividend
 * @property-read AccountEntity $account
 * @property-read Transaction|null $config
 * @property-read Investment $investment
 * @method static Builder|Transaction byScheduleType($type)
 * @method static TransactionDetailInvestmentFactory factory(...$parameters)
 * @method static Builder|TransactionDetailInvestment newModelQuery()
 * @method static Builder|TransactionDetailInvestment newQuery()
 * @method static Builder|TransactionDetailInvestment query()
 * @method static Builder|TransactionDetailInvestment whereAccountId($value)
 * @method static Builder|TransactionDetailInvestment whereCommission($value)
 * @method static Builder|TransactionDetailInvestment whereDividend($value)
 * @method static Builder|TransactionDetailInvestment whereId($value)
 * @method static Builder|TransactionDetailInvestment whereInvestmentId($value)
 * @method static Builder|TransactionDetailInvestment wherePrice($value)
 * @method static Builder|TransactionDetailInvestment whereQuantity($value)
 * @method static Builder|TransactionDetailInvestment whereTax($value)
 * @property-read Collection|TransactionItem[] $transactionItems
 * @property-read int|null $transaction_items_count
 * @property-read TransactionSchedule|null $transactionSchedule
 * @property-read TransactionType $transactionType
 * @mixin Eloquent
 * @property int $account_id
 * @property-read \App\Models\Transaction|null $transaction
 */
	class TransactionDetailInvestment extends \Eloquent {}
}

namespace App\Models{
/**
 * App\Models\TransactionDetailStandard
 *
 * @property int $id
 * @property int|null $account_from_id
 * @property int|null $account_to_id
 * @property float $amount_from
 * @property float $amount_to
 * @property-read AccountEntity|null $accountFrom
 * @property-read AccountEntity|null $accountTo
 * @property-read Transaction|null $config
 * @method static Builder|Transaction byScheduleType($type)
 * @method static TransactionDetailStandardFactory factory(...$parameters)
 * @method static Builder|TransactionDetailStandard newModelQuery()
 * @method static Builder|TransactionDetailStandard newQuery()
 * @method static Builder|TransactionDetailStandard query()
 * @method static Builder|TransactionDetailStandard whereAccountFromId($value)
 * @method static Builder|TransactionDetailStandard whereAccountToId($value)
 * @method static Builder|TransactionDetailStandard whereAmountFrom($value)
 * @method static Builder|TransactionDetailStandard whereAmountTo($value)
 * @method static Builder|TransactionDetailStandard whereId($value)
 * @property-read Collection|TransactionItem[] $transactionItems
 * @property-read int|null $transaction_items_count
 * @property-read TransactionSchedule|null $transactionSchedule
 * @property-read TransactionType $transactionType
 * @mixin Eloquent
 * @property-read \App\Models\Transaction|null $transaction
 */
	class TransactionDetailStandard extends \Eloquent {}
}

namespace App\Models{
/**
 * App\Models\TransactionItem
 *
 * @property int $id
 * @property int $transaction_id
 * @property int|null $category_id
 * @property float $amount
 * @property string|null $comment
 * @property-read Category|null $category
 * @property-read Collection|Tag[] $tags
 * @property-read int|null $tags_count
 * @property-read Transaction $transaction
 * @method static TransactionItemFactory factory(...$parameters)
 * @method static Builder|TransactionItem newModelQuery()
 * @method static Builder|TransactionItem newQuery()
 * @method static Builder|TransactionItem query()
 * @method static Builder|TransactionItem whereAmount($value)
 * @method static Builder|TransactionItem whereCategoryId($value)
 * @method static Builder|TransactionItem whereComment($value)
 * @method static Builder|TransactionItem whereId($value)
 * @method static Builder|TransactionItem whereTransactionId($value)
 * @mixin Eloquent
 */
	class TransactionItem extends \Eloquent {}
}

namespace App\Models{
/**
 * App\Models\TransactionSchedule
 *
 * @property int $id
 * @property int $transaction_id
 * @property Carbon $start_date
 * @property Carbon|null $next_date
 * @property Carbon|null $end_date
 * @property string $frequency
 * @property int $interval
 * @property int|null $count
 * @property float|null $inflation
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property bool $automatic_recording
 * @property bool $active
 * @property-read Transaction $transaction
 * @method static TransactionScheduleFactory factory(...$parameters)
 * @method static Builder|TransactionSchedule newModelQuery()
 * @method static Builder|TransactionSchedule newQuery()
 * @method static Builder|TransactionSchedule query()
 * @method static Builder|TransactionSchedule whereCount($value)
 * @method static Builder|TransactionSchedule whereCreatedAt($value)
 * @method static Builder|TransactionSchedule whereEndDate($value)
 * @method static Builder|TransactionSchedule whereFrequency($value)
 * @method static Builder|TransactionSchedule whereId($value)
 * @method static Builder|TransactionSchedule whereInflation($value)
 * @method static Builder|TransactionSchedule whereInterval($value)
 * @method static Builder|TransactionSchedule whereNextDate($value)
 * @method static Builder|TransactionSchedule whereStartDate($value)
 * @method static Builder|TransactionSchedule whereTransactionId($value)
 * @method static Builder|TransactionSchedule whereUpdatedAt($value)
 * @mixin Eloquent
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TransactionSchedule whereActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TransactionSchedule whereAutomaticRecording($value)
 */
	class TransactionSchedule extends \Eloquent {}
}

namespace App\Models{
/**
 * App\Models\TransactionType
 *
 * @property int $id
 * @property string $name
 * @property string $type
 * @property int|null $amount_multiplier
 * @property int|null $quantity_multiplier
 * @method static \Illuminate\Database\Eloquent\Builder|TransactionType newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|TransactionType newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|TransactionType query()
 * @method static \Illuminate\Database\Eloquent\Builder|TransactionType whereAmountOperator($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TransactionType whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TransactionType whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TransactionType whereQuantityOperator($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TransactionType whereType($value)
 * @mixin \Eloquent
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TransactionType whereAmountMultiplier($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|TransactionType whereQuantityMultiplier($value)
 */
	class TransactionType extends \Eloquent {}
}

namespace App\Models{
/**
 * App\Models\User
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $remember_token
 * @property string $language
 * @property string $locale
 * @property Carbon $start_date
 * @property Carbon $end_date
 * @property string $account_details_date_range
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, AccountGroup> $accountGroups
 * @property-read int|null $account_groups_count
 * @property-read Collection<int, AccountEntity> $accounts
 * @property-read int|null $accounts_count
 * @property-read Collection<int, Category> $categories
 * @property-read int|null $categories_count
 * @property-read Collection<int, Currency> $currencies
 * @property-read int|null $currencies_count
 * @property-read Collection<int, Flag> $flags
 * @property-read int|null $flags_count
 * @property-read Collection<int, InvestmentGroup> $investmentGroups
 * @property-read int|null $investment_groups_count
 * @property-read Collection<int, Investment> $investments
 * @property-read int|null $investments_count
 * @property-read DatabaseNotificationCollection<int, DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 * @property-read Collection<int, AccountEntity> $payees
 * @property-read int|null $payees_count
 * @property-read Collection<int, Tag> $tag
 * @property-read int|null $tags_count
 * @property-read Collection<int, PersonalAccessToken> $tokens
 * @property-read int|null $tokens_count
 * @property-read Collection<int, Transaction> $transactions
 * @property-read int|null $transactions_count
 * @method static UserFactory factory($count = null, $state = [])
 * @method static Builder|User flagged(string $name)
 * @method static Builder|User newModelQuery()
 * @method static Builder|User newQuery()
 * @method static Builder|User notFlagged(string $name)
 * @method static Builder|User query()
 * @method static Builder|User whereCreatedAt($value)
 * @method static Builder|User whereEmail($value)
 * @method static Builder|User whereEmailVerifiedAt($value)
 * @method static Builder|User whereEndDate($value)
 * @method static Builder|User whereId($value)
 * @method static Builder|User whereLanguage($value)
 * @method static Builder|User whereLocale($value)
 * @method static Builder|User whereName($value)
 * @method static Builder|User wherePassword($value)
 * @method static Builder|User whereRememberToken($value)
 * @method static Builder|User whereStartDate($value)
 * @method static Builder|User whereUpdatedAt($value)
 * @mixin Eloquent
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\AiDocument> $aiDocuments
 * @property-read int|null $ai_documents_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\AiProviderConfig> $aiProviderConfig
 * @property-read int|null $ai_provider_config_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\CategoryLearning> $categoryLearning
 * @property-read int|null $category_learning_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ReceivedMail> $receivedMails
 * @property-read int|null $received_mails_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Tag> $tags
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereAccountDetailsDateRange($value)
 */
	class User extends \Eloquent implements \Illuminate\Contracts\Auth\MustVerifyEmail, \Spatie\Onboard\Concerns\Onboardable {}
}

