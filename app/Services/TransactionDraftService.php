<?php

namespace App\Services;

use App\Enums\TransactionType as TransactionTypeEnum;
use App\Models\AccountEntity;
use App\Models\Category;
use App\Models\Investment;
use App\Models\Tag;
use App\Models\Transaction;
use App\Models\TransactionDetailInvestment;
use App\Models\TransactionDetailStandard;
use App\Models\TransactionItem;
use App\Models\User;
use Brick\Math\BigDecimal;
use Brick\Money\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Owns the transaction draft: the partial transaction shared by AI documents and templates
 * (schema v2, see .ai/docs/specifications/fast-transaction-entry/specification.md D5).
 */
class TransactionDraftService
{
    public const int SCHEMA_VERSION = 2;

    private const array STANDARD_CONFIG_KEYS = ['amount_from', 'amount_to', 'account_from_id', 'account_to_id'];

    private const array INVESTMENT_CONFIG_KEYS = ['account_id', 'investment_id', 'quantity', 'price', 'commission', 'tax', 'dividend'];

    private const array CONFIG_AMOUNT_KEYS = ['amount_from', 'amount_to', 'quantity', 'price', 'commission', 'tax', 'dividend'];

    private const array ITEM_KEYS = [
        'category_id', 'amount', 'comment', 'tag_ids',
        'description', 'recommended_category_id', 'match_type', 'confidence_score',
    ];

    /**
     * Validate a draft, upgrade it to the current schema version, canonicalize amounts to decimal strings,
     * and remove references to records that are missing (deleted or not the user's) or inactive.
     *
     * @return array{draft: array, notices: list<array{field: string, reason: 'not_found'|'inactive'}>}
     *
     * @throws ValidationException on unknown keys or malformed values
     */
    public function normalize(array $draft, User $user): array
    {
        $this->validate($draft);

        $draft = ['schema_version' => self::SCHEMA_VERSION] + $draft;

        foreach (self::CONFIG_AMOUNT_KEYS as $key) {
            $this->canonicalizeAmount($draft, "config.{$key}");
        }
        foreach (array_keys($draft['transaction_items'] ?? []) as $index) {
            $this->canonicalizeAmount($draft, "transaction_items.{$index}.amount");
        }

        $notices = [];
        $this->blankStaleReferences($draft, AccountEntity::class, ['config.account_from_id', 'config.account_to_id', 'config.account_id'], $user, $notices);
        $this->blankStaleReferences($draft, Investment::class, ['config.investment_id'], $user, $notices);

        $itemPaths = fn (string $key): array => array_map(
            fn ($index) => "transaction_items.{$index}.{$key}",
            array_keys($draft['transaction_items'] ?? [])
        );
        $this->blankStaleReferences($draft, Category::class, [...$itemPaths('category_id'), ...$itemPaths('recommended_category_id')], $user, $notices);

        $tagPaths = [];
        foreach ($draft['transaction_items'] ?? [] as $index => $item) {
            foreach (array_keys($item['tag_ids'] ?? []) as $tagIndex) {
                $tagPaths[] = "transaction_items.{$index}.tag_ids.{$tagIndex}";
            }
        }
        $this->blankStaleReferences($draft, Tag::class, $tagPaths, $user, $notices);
        foreach ($draft['transaction_items'] ?? [] as $index => $item) {
            if (isset($item['tag_ids'])) {
                $draft['transaction_items'][$index]['tag_ids'] = array_values($item['tag_ids']);
            }
        }

        return ['draft' => $draft, 'notices' => $notices];
    }

    /**
     * Add display data for the draft's references: recommended category full names on the items, and
     * `matched_entities` (account, payee, investment) for the AI document viewer. Only the user's own
     * records are resolved.
     */
    public function enrich(array $draft, User $user): array
    {
        if (isset($draft['transaction_items']) && is_array($draft['transaction_items'])) {
            $categoryIds = collect($draft['transaction_items'])
                ->map(fn ($item) => $item['recommended_category_id'] ?? null)
                ->filter()
                ->unique()
                ->values()
                ->toArray();

            if (! empty($categoryIds)) {
                $categories = Category::query()
                    ->with('parent')
                    ->whereIn('id', $categoryIds)
                    ->where('user_id', $user->id)
                    ->get()
                    ->keyBy('id');

                foreach ($draft['transaction_items'] as &$item) {
                    if (isset($item['recommended_category_id']) && $categories->has($item['recommended_category_id'])) {
                        $item['recommended_category_full_name'] = $categories->get($item['recommended_category_id'])->full_name;
                    }
                }
                unset($item);
            }
        }

        $config = $draft['config'] ?? [];
        $transactionType = $draft['transaction_type'] ?? null;

        $accountIds = collect([
            $config['account_id'] ?? null,
            $config['account_from_id'] ?? null,
            $config['account_to_id'] ?? null,
        ])->filter()->unique()->values()->all();

        $accountsById = AccountEntity::query()
            ->where('user_id', $user->id)
            ->whereIn('id', $accountIds)
            ->get()
            ->keyBy('id');

        $investmentIds = collect([
            $config['investment_id'] ?? null,
        ])->filter()->unique()->values()->all();

        $investmentsById = Investment::query()
            ->where('user_id', $user->id)
            ->whereIn('id', $investmentIds)
            ->get()
            ->keyBy('id');

        $matchedEntities = [];

        if ($transactionType === 'transfer') {
            $from = $accountsById->get($config['account_from_id'] ?? null);
            $to = $accountsById->get($config['account_to_id'] ?? null);

            if ($from) {
                $matchedEntities['account_from'] = $this->matchedEntity($from, route('account-entity.show', $from->id));
            }

            if ($to) {
                $matchedEntities['account_to'] = $this->matchedEntity($to, route('account-entity.show', $to->id));
            }
        } elseif (in_array($transactionType, ['withdrawal', 'deposit'], true)) {
            $accountId = $transactionType === 'withdrawal'
                ? ($config['account_from_id'] ?? null)
                : ($config['account_to_id'] ?? null);
            $payeeId = $transactionType === 'withdrawal'
                ? ($config['account_to_id'] ?? null)
                : ($config['account_from_id'] ?? null);

            $account = $accountsById->get($accountId);
            $payee = $accountsById->get($payeeId);

            if ($account) {
                $matchedEntities['account'] = $this->matchedEntity($account, route('account-entity.show', $account->id));
            }

            if ($payee) {
                $matchedEntities['payee'] = $this->matchedEntity($payee, null);
            }
        } elseif (in_array($transactionType, TransactionTypeEnum::investmentTypeValues(), true)) {
            $account = $accountsById->get($config['account_id'] ?? null);
            $investment = $investmentsById->get($config['investment_id'] ?? null);

            if ($account) {
                $matchedEntities['account'] = $this->matchedEntity($account, route('account-entity.show', $account->id));
            }

            if ($investment) {
                $matchedEntities['investment'] = $this->matchedEntity($investment, route('investments.show', ['investment' => $investment->id]));
            }
        }

        $draft['matched_entities'] = $matchedEntities;

        return $draft;
    }

    /**
     * Build a v2 draft from a transaction, without its date (used by Save as template). Money and
     * quantity values become decimal strings.
     */
    public function fromTransaction(Transaction $transaction): array
    {
        $transaction->loadMissing(['config', 'transactionItems.tags']);

        $draft = [
            'schema_version' => self::SCHEMA_VERSION,
            'config_type' => $transaction->config_type,
            'transaction_type' => $transaction->transaction_type->value,
        ];

        if ($transaction->comment !== null) {
            $draft['comment'] = $transaction->comment;
        }

        $keys = $transaction->config_type === 'investment' ? self::INVESTMENT_CONFIG_KEYS : self::STANDARD_CONFIG_KEYS;
        $draft['config'] = collect($keys)
            ->mapWithKeys(fn (string $key) => [$key => $this->decimalString($transaction->config->{$key})])
            ->all();

        if ($transaction->config_type === 'standard') {
            $draft['transaction_items'] = $transaction->transactionItems
                ->map(fn (TransactionItem $item) => array_filter([
                    'category_id' => $item->category_id,
                    'amount' => $this->decimalString($item->amount),
                    'comment' => $item->comment,
                    'tag_ids' => $item->tags->pluck('id')->all() ?: null,
                ], fn ($value) => $value !== null))
                ->values()
                ->all();
        }

        return $draft;
    }

    /**
     * Build an unsaved transaction (with config, items and the user's own account/payee relations) from a
     * draft, for rendering the transaction form. Nothing is persisted.
     */
    public function toUnsavedTransaction(array $draft, User $user): Transaction
    {
        $configType = $draft['config_type'] ?? 'standard';

        $transaction = new Transaction($draft);

        $transaction->transaction_type = TransactionTypeEnum::tryFrom($draft['transaction_type'] ?? '')
            ?? ($configType === 'investment' ? TransactionTypeEnum::BUY : TransactionTypeEnum::WITHDRAWAL);

        // Ensure that a config relation exists, even if it's empty
        $config = $draft['config'] ?? [];
        if ($configType === 'investment') {
            $transaction->setRelation('config', new TransactionDetailInvestment($config));
        } else {
            $transaction->setRelation('config', new TransactionDetailStandard($config));
            // Inverse relation, so TransactionDetailStandard::resolveStandardCurrency()'s
            // fallback (when neither account side resolves) can reach the owning
            // transaction's currency instead of lazily querying for a non-existent row.
            $transaction->config->setRelation('transaction', $transaction);

            $draftTransactionItems = $this->buildUnsavedItems($draft, $user->id);

            // These items are manually attached rather than eager-loaded, so chaperone()
            // never fires - set the inverse relation by hand so TransactionItem::amount
            // (MoneyCast) can resolve its currency via the parent transaction instead of
            // issuing a lazy lookup for a transaction_id that doesn't exist yet (draft/unsaved).
            $draftTransactionItems->each(fn (TransactionItem $item) => $item->setRelation('transaction', $transaction));

            $transaction->setRelation('transactionItems', $draftTransactionItems);

            // Try to add relation for account and payee, if they exist.
            // Use the real (camelCase) relation names, matching TransactionDetailStandard::
            // accountFrom()/accountTo() - not just so Eloquent's snake-casing still produces
            // the same "account_from"/"account_to" JSON keys, but so relationLoaded() sees
            // these as already resolved. Otherwise resolveAmountFromCurrency() (MoneyCast)
            // would lazy-load them again with no user scope at all, undoing this scoping.
            if (($config['account_from_id'] ?? null) !== null) {
                $transaction->config->setRelation(
                    'accountFrom',
                    AccountEntity::where('user_id', $user->id)->find($config['account_from_id'])
                );
            }
            if (($config['account_to_id'] ?? null) !== null) {
                $transaction->config->setRelation(
                    'accountTo',
                    AccountEntity::where('user_id', $user->id)->find($config['account_to_id'])
                );
            }
        }

        // Ensure that the transaction is basic
        $transaction->schedule = false;
        $transaction->reconciled = false;

        return $transaction;
    }

    /**
     * @throws ValidationException
     */
    private function validate(array $draft): void
    {
        $configType = $draft['config_type'] ?? null;
        $isInvestment = $configType === 'investment';
        $transactionTypes = collect(TransactionTypeEnum::cases())
            ->filter(fn (TransactionTypeEnum $type) => $type->category() === $configType)
            ->map(fn (TransactionTypeEnum $type) => $type->value)
            ->implode(',');
        $configKeys = implode(',', $isInvestment ? self::INVESTMENT_CONFIG_KEYS : self::STANDARD_CONFIG_KEYS);

        $rules = [
            'draft' => 'required|array:schema_version,config_type,transaction_type,date,comment,config,transaction_items,raw',
            'draft.schema_version' => 'sometimes|integer|in:' . self::SCHEMA_VERSION,
            'draft.config_type' => 'required|in:standard,investment',
            'draft.transaction_type' => 'sometimes|in:' . $transactionTypes,
            'draft.date' => 'sometimes|nullable|date_format:Y-m-d',
            'draft.comment' => 'sometimes|nullable|string',
            'draft.config' => 'sometimes|array:' . $configKeys,
            'draft.config.account_from_id' => 'nullable|integer',
            'draft.config.account_to_id' => 'nullable|integer',
            'draft.config.account_id' => 'nullable|integer',
            'draft.config.investment_id' => 'nullable|integer',
            'draft.transaction_items' => 'sometimes|list',
            'draft.transaction_items.*' => 'array:' . implode(',', self::ITEM_KEYS),
            'draft.transaction_items.*.category_id' => 'nullable|integer',
            'draft.transaction_items.*.recommended_category_id' => 'nullable|integer',
            'draft.transaction_items.*.amount' => 'nullable|numeric',
            'draft.transaction_items.*.comment' => 'nullable|string',
            'draft.transaction_items.*.description' => 'nullable|string',
            'draft.transaction_items.*.tag_ids' => 'sometimes|list',
            'draft.transaction_items.*.tag_ids.*' => 'integer',
            'draft.transaction_items.*.match_type' => 'nullable|string',
            'draft.transaction_items.*.confidence_score' => 'nullable|numeric',
            'draft.raw' => 'sometimes|nullable|array',
        ];

        foreach (self::CONFIG_AMOUNT_KEYS as $key) {
            $rules["draft.config.{$key}"] = 'nullable|numeric';
        }

        Validator::make(['draft' => $draft], $rules)->validate();
    }

    private function canonicalizeAmount(array &$draft, string $path): void
    {
        $value = Arr::get($draft, $path);

        // Floats go through PHP's own string form: BigDecimal::of() does not accept fractional floats
        if ($value !== null) {
            Arr::set($draft, $path, (string) BigDecimal::of(is_float($value) ? (string) $value : $value));
        }
    }

    /**
     * Remove the draft keys at the given paths whose IDs do not resolve to an active record of the user,
     * and add a notice for each.
     *
     * @param class-string<Model> $modelClass
     * @param list<string> $paths
     * @param list<array{field: string, reason: string}> $notices
     */
    private function blankStaleReferences(array &$draft, string $modelClass, array $paths, User $user, array &$notices): void
    {
        $idsByPath = collect($paths)
            ->mapWithKeys(fn (string $path) => [$path => Arr::get($draft, $path)])
            ->filter(fn ($id) => $id !== null);

        if ($idsByPath->isEmpty()) {
            return;
        }

        // pluck() applies the model's boolean cast to `active`
        $activeById = $modelClass::query()
            ->where('user_id', $user->id)
            ->whereIn('id', $idsByPath->unique()->values())
            ->pluck('active', 'id');

        foreach ($idsByPath as $path => $id) {
            $reason = match ($activeById->get($id)) {
                null => 'not_found',
                false => 'inactive',
                default => null,
            };

            if ($reason !== null) {
                Arr::forget($draft, $path);
                $notices[] = ['field' => $path, 'reason' => $reason];
            }
        }
    }

    private function decimalString(Money|BigDecimal|string|int|null $value): string|int|null
    {
        return match (true) {
            $value instanceof Money => (string) $value->getAmount(),
            $value instanceof BigDecimal => (string) $value,
            default => $value,
        };
    }

    private function matchedEntity(AccountEntity|Investment $entity, ?string $url): array
    {
        return [
            'id' => $entity->id,
            'name' => $entity->name,
            'matched' => true,
            'url' => $url,
        ];
    }

    /**
     * @return Collection<int, TransactionItem>
     */
    private function buildUnsavedItems(array $draft, int $userId): Collection
    {
        $items = collect($draft['transaction_items'] ?? [])
            ->filter(fn ($item) => is_array($item))
            ->values();

        if ($items->isEmpty()) {
            return collect();
        }

        $categoryIds = $items
            ->flatMap(fn (array $item): array => [
                $item['category_id'] ?? null,
                $item['recommended_category_id'] ?? null,
            ])
            ->filter()
            ->unique()
            ->values();

        $categoriesById = Category::query()
            ->with('parent')
            ->where('user_id', $userId)
            ->whereIn('id', $categoryIds)
            ->get()
            ->keyBy('id');

        return $items->map(function (array $itemData) use ($categoriesById): TransactionItem {
            $categoryId = $itemData['category_id'] ?? null;
            $recommendedCategoryId = $itemData['recommended_category_id'] ?? null;

            if (! array_key_exists('category_full_name', $itemData) || empty($itemData['category_full_name'])) {
                $itemData['category_full_name'] = $categoryId
                    ? $categoriesById->get($categoryId)?->full_name
                    : null;
            }

            if (! array_key_exists('recommended_category_full_name', $itemData) || empty($itemData['recommended_category_full_name'])) {
                $itemData['recommended_category_full_name'] = $recommendedCategoryId
                    ? $categoriesById->get($recommendedCategoryId)?->full_name
                    : null;
            }

            $transactionItem = new TransactionItem([
                'category_id' => $categoryId,
                'amount' => $itemData['amount'] ?? 0,
                'comment' => $itemData['comment'] ?? null,
            ]);

            // Preserve AI-context attributes so the standalone finalize form can render AI recommendation controls.
            $transactionItem->setAttribute('category_full_name', $itemData['category_full_name'] ?? null);
            $transactionItem->setAttribute('recommended_category_id', $recommendedCategoryId);
            $transactionItem->setAttribute('recommended_category_full_name', $itemData['recommended_category_full_name'] ?? null);
            $transactionItem->setAttribute('description', $itemData['description'] ?? null);
            $transactionItem->setAttribute('match_type', $itemData['match_type'] ?? null);
            $transactionItem->setAttribute('confidence_score', $itemData['confidence_score'] ?? null);

            return $transactionItem;
        });
    }
}
